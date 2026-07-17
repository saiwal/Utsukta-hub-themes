<?php
namespace Theme\Solidified\Api\Handlers;

use Theme\Solidified\Api\Auth;
use Theme\Solidified\Api\Response;
use App;
use DBA;
use URLify;
use Zotlabs\Daemon\Master;
use Zotlabs\Lib\Config;
use Zotlabs\Lib\Libzot;

class Portability
{
    private const VALID_SECTIONS = [
        'channel', 'connections', 'config', 'apps',
        'chatrooms', 'events', 'webpages', 'wikis',
    ];

    // Identity-only import for now — background daemon content/file import
    // (Content_importer/File_importer) is a deferred, separate phase.
    private const MAX_UPLOAD_BYTES = 50 * 1024 * 1024;

    public function get(): void
    {
        $uid = Auth::requireLocalGet();

        $datatype = App::$argv[2] ?? '';
        switch ($datatype) {
            case 'export':
                $this->exportChannel($uid);
                break;
            case '':
                $this->getMetadata($uid);
                break;
            default:
                Response::error(404, 'Unknown endpoint');
        }
    }

    public function post(): void
    {
        $datatype = App::$argv[2] ?? '';
        switch ($datatype) {
            case 'import':
                $this->importChannel();
                break;
            case 'migrate':
                $this->migrateChannel();
                break;
            default:
                Response::error(404, 'Unknown endpoint');
        }
    }

    // ── Metadata ─────────────────────────────────────────────────────────────

    private function getMetadata(int $uid): void
    {
        require_once('include/channel.php');

        $export_enabled = \Zotlabs\Lib\Apps::system_app_installed($uid, 'Channel Export');

        $account = App::get_account();
        $year_start = (int) datetime_convert('UTC', date_default_timezone_get(), $account['account_created'] ?? 'now', 'Y');
        $year_end = (int) datetime_convert('UTC', date_default_timezone_get(), 'now', 'Y');

        $years = [];
        for ($y = $year_start; $y <= $year_end; $y++) {
            $years[] = $y;
        }

        Response::send([
            'export_enabled' => (bool) $export_enabled,
            'sections' => self::VALID_SECTIONS,
            'default_sections' => get_default_export_sections(),
            'years' => $years,
        ]);
    }

    // ── Identity export download ────────────────────────────────────────────

    private function exportChannel(int $uid): void
    {
        require_once('include/channel.php');

        if (!\Zotlabs\Lib\Apps::system_app_installed($uid, 'Channel Export')) {
            Response::error(403, 'Channel Export app is not installed');
        }

        $raw = trim((string) ($_GET['sections'] ?? ''));
        $requested = $raw !== '' ? explode(',', $raw) : [];
        $sections = array_values(array_intersect(self::VALID_SECTIONS, $requested));

        if (!$sections) {
            Response::error(400, 'No valid sections specified');
        }

        $channel = App::get_channel();
        $export = json_encode(identity_basic_export($uid, $sections, false));

        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="' . $channel['channel_address'] . '-' . implode('-', $sections) . '.json"');
        header('Content-Length: ' . strlen($export));

        echo $export;
        exit;
    }

    // ── Manual file-upload import ───────────────────────────────────────────
    //
    // Ports Zotlabs\Module\Import::import_account()'s body rather than reflecting
    // into it: that module is Controller-based, reads $_REQUEST/$_FILES directly,
    // and reports failure via notice()+goaway() (HTML flash messages), none of
    // which is JSON-friendly or exception-safe. Keep this in sync with
    // include/import.php and Zotlabs/Module/Import.php if core changes.
    //
    // Content/file import (the Content_importer/File_importer background daemons,
    // triggered by import_posts + a remote-credentials fetch) is out of scope —
    // this only imports identity/connections/config/apps/groups from an uploaded
    // export file.

    private function importChannel(): void
    {
        $account = Auth::requireAccountMultipart();

        if (empty($_FILES['file']) || ($_FILES['file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            Response::error(400, 'No file uploaded');
        }

        $size = intval($_FILES['file']['size'] ?? 0);
        if ($size <= 0) {
            Response::error(400, 'Uploaded file is empty');
        }
        if ($size > self::MAX_UPLOAD_BYTES) {
            Response::error(413, 'File too large');
        }

        $makePrimary = !empty($_POST['make_primary']) ? 1 : 0;
        $newname = trim((string) ($_POST['newname'] ?? ''));

        if ($makePrimary) {
            $this->requireAccountPasswordForSeize($account, (string) ($_POST['password'] ?? ''));
        }

        $json = @file_get_contents($_FILES['file']['tmp_name']);
        @unlink($_FILES['file']['tmp_name']);

        if (!$json) {
            Response::error(400, 'Uploaded file is empty');
        }

        $result = $this->runImport($account, $json, [
            'make_primary' => $makePrimary,
            'newname' => $newname,
        ]);

        Response::send(array_merge(['status' => 'ok'], $result));
    }

    // Seizing primary status on an account that already owns a channel is a
    // quasi-destructive re-import/restore — require the account password as
    // confirmation, mirroring Settings::postDangerSettings()'s remove_channel
    // check (including the 48-hour post-password-change cooldown).
    private function requireAccountPasswordForSeize(array $account, string $password): void
    {
        $existing = q(
            "SELECT channel_id FROM channel WHERE channel_account_id = %d AND channel_removed = 0 LIMIT 1",
            intval($account['account_id'])
        );
        if (!$existing) {
            return;
        }

        if ($password === '') {
            Response::error(400, 'Password confirmation is required to set this as your primary location');
        }

        $x = account_verify_password($account['account_email'], $password);
        if (!$x || !$x['account']) {
            Response::error(403, 'Incorrect password');
        }

        if ($account['account_password_changed'] > DBA::$dba->get_null_date()) {
            $d1 = datetime_convert('UTC', 'UTC', 'now - 48 hours');
            if ($account['account_password_changed'] > $d1) {
                Response::error(403, 'Imports that change your primary location are not allowed within 48 hours of changing the account password.');
            }
        }
    }

    // ── Remote-credential migration ─────────────────────────────────────────
    //
    // Ports the "import channel from another server" branch of
    // Zotlabs\Module\Import::import_account() — probes the old hub's API path,
    // fetches its channel/export/basic payload via HTTP Basic Auth using the
    // *old hub's* login credentials, then feeds the result through the same
    // runImport() used by the file-upload path. HTTPS-only and rate-limited:
    // deliberately stricter than core, which also tries plain http and has no
    // throttle — this endpoint accepts a password and makes an outbound
    // request to an attacker-influenceable hostname.

    private const MIGRATE_RATE_LIMIT_WINDOW = 3600; // seconds
    private const MIGRATE_RATE_LIMIT_MAX = 5;
    private const MIGRATE_FETCH_TIMEOUT = 15; // seconds
    private const MIGRATE_CONNECT_TIMEOUT = 5; // seconds

    private function migrateChannel(): void
    {
        $account = Auth::requireAccountJson();
        $data = Auth::$parsedBody;

        $oldAddress = trim((string) ($data['old_address'] ?? ''));
        $email = (string) ($data['email'] ?? '');
        $password = (string) ($data['password'] ?? '');
        // WARNING: this is a utf-8 variant, not an ASCII '@' — matches core's
        // convenience handling for addresses copied from a profile page.
        $oldAddress = str_replace('＠', '@', $oldAddress);

        if ($oldAddress === '' || strpos($oldAddress, '@') === false || $email === '' || $password === '') {
            Response::error(400, 'Old identity address, login email, and password are all required');
        }

        $makePrimary = !empty($data['make_primary']) ? 1 : 0;
        $newname = trim((string) ($data['newname'] ?? ''));

        if ($makePrimary) {
            $this->requireAccountPasswordForSeize($account, (string) ($data['local_password'] ?? ''));
        }

        $this->enforceMigrateRateLimit(intval($account['account_id']));

        $channelname = substr($oldAddress, 0, strpos($oldAddress, '@'));
        $servername = substr($oldAddress, strpos($oldAddress, '@') + 1);

        $apiPath = $this->probeApiPathHttpsOnly($servername);
        if (!$apiPath) {
            Response::error(502, 'Unable to connect to old server');
        }

        $fetchUrl = $apiPath . 'channel/export/basic?f=&channel=' . urlencode($channelname);
        $ret = z_fetch_url($fetchUrl, false, 0, [
            'http_auth' => $email . ':' . $password,
            'timeout' => self::MIGRATE_FETCH_TIMEOUT,
            'connecttimeout' => self::MIGRATE_CONNECT_TIMEOUT,
        ]);

        // Deliberately generic — don't reveal whether this was a bad password
        // or an unreachable/misbehaving server, to avoid a credential-testing
        // oracle against arbitrary hosts.
        if (!$ret['success'] || !$ret['body']) {
            Response::error(502, 'Unable to download data from old server');
        }

        $result = $this->runImport($account, $ret['body'], [
            'make_primary' => $makePrimary,
            'newname' => $newname,
        ]);

        Response::send(array_merge(['status' => 'ok'], $result));
    }

    private function enforceMigrateRateLimit(int $account_id): void
    {
        $now = time();
        $raw = get_pconfig($account_id, 'portability_migrate', 'attempts');
        $attempts = is_array($raw) ? $raw : [];
        $attempts = array_values(array_filter($attempts, fn($t) => $t > $now - self::MIGRATE_RATE_LIMIT_WINDOW));

        if (count($attempts) >= self::MIGRATE_RATE_LIMIT_MAX) {
            Response::error(429, 'Too many migration attempts. Please try again later.');
        }

        $attempts[] = $now;
        set_pconfig($account_id, 'portability_migrate', 'attempts', $attempts);
    }

    // HTTPS-only variant of core's probe_api_path() (include/network.php) —
    // that function also falls back to plain http, which would leak the old
    // hub's login credentials (sent via HTTP Basic Auth) in plaintext.
    private function probeApiPathHttpsOnly(string $host): string
    {
        $paths = ['/api/z/1.0/version', '/api/red/version'];
        foreach ($paths as $path) {
            $url = 'https://' . $host . $path;
            $x = z_fetch_url($url, false, 0, [
                'timeout' => self::MIGRATE_FETCH_TIMEOUT,
                'connecttimeout' => self::MIGRATE_CONNECT_TIMEOUT,
            ]);
            if ($x['success'] && !strpos($x['body'], 'not implemented')) {
                return str_replace('version', '', $url);
            }
        }
        return '';
    }

    private function runImport(array $account, string $jsonPayload, array $opts): array
    {
        require_once('include/channel.php');
        require_once('include/import.php');
        require_once('include/perm_upgrade.php');

        $account_id = intval($account['account_id']);
        $seize = !empty($opts['make_primary']) ? 1 : 0;
        // 'moving' mirrors core: Zotlabs\Module\Import hardcodes this to false too
        // (the $_REQUEST['moving'] read is dead code upstream) — not exposed here.
        $moving = false;
        $newname = trim(strtolower((string) ($opts['newname'] ?? '')));

        $max_friends = account_service_class_fetch($account_id, 'total_channels');
        $max_feeds = account_service_class_fetch($account_id, 'total_feeds');

        $data = json_decode($jsonPayload, true);
        if (!is_array($data) || !$data) {
            Response::error(400, 'Imported file is empty or not valid JSON');
        }

        if (array_path_exists('compatibility/codebase', $data)) {
            Response::error(400, 'Data export format is not compatible with this software');
        }
        if (!isset($data['compatibility']['version']) || version_compare($data['compatibility']['version'], '4.7.3', '<=')) {
            Response::error(400, 'Data export format is not compatible with this software (not a zot6 channel)');
        }

        if (!array_key_exists('channel', $data)) {
            Response::error(400, 'Export file must include channel identity data');
        }

        if ($moving) {
            $seize = 1;
        }

        $relocate = $data['relocate'] ?? null;

        $max_identities = account_service_class_fetch($account_id, 'total_identities');
        if ($max_identities !== false) {
            $r = q(
                "select channel_id from channel where channel_account_id = %d and channel_removed = 0",
                intval($account_id)
            );
            if ($r && count($r) > $max_identities) {
                Response::error(403, "Your service plan only allows $max_identities channels.");
            }
        }

        if ($newname) {
            $x = false;
            if (Config::Get('system', 'unicode_usernames')) {
                $x = punify(mb_strtolower($newname));
            }
            if ((!$x) || strlen($x) > 64) {
                $x = strtolower(URLify::transliterate($newname));
            }
            $newname = $x;
        }

        $channel = import_channel($data['channel'], $account_id, $seize, $newname);

        if (!$channel) {
            Response::error(422, 'Import failed: unable to create the channel (it may already exist as a true duplicate, be marked removed, or the export data may be invalid)');
        }
        if (!empty($channel['channel_removed'])) {
            Response::error(422, 'Channel exists but has been marked removed on this hub. Import failed.');
        }

        if (is_array($data['config'] ?? null)) {
            import_config($channel, $data['config']);
        }

        if (!empty($data['photo'])) {
            require_once('include/photo/photo_driver.php');
            import_channel_photo(base64url_decode($data['photo']['data']), $data['photo']['type'], $account_id, $channel['channel_id']);
        }
        if (is_array($data['profile'] ?? null)) {
            import_profiles($channel, $data['profile']);
        }

        // Create a new zot6 hubloc for this channel at this site.
        hubloc_store_lowlevel([
            'hubloc_guid' => $channel['channel_guid'],
            'hubloc_guid_sig' => $channel['channel_guid_sig'],
            'hubloc_hash' => $channel['channel_hash'],
            'hubloc_addr' => channel_reddress($channel),
            'hubloc_network' => 'zot6',
            'hubloc_primary' => ($seize ? 1 : 0),
            'hubloc_url' => z_root(),
            'hubloc_url_sig' => Libzot::sign(z_root(), $channel['channel_prvkey']),
            'hubloc_host' => App::get_hostname(),
            'hubloc_callback' => z_root() . '/zot',
            'hubloc_sitekey' => Config::Get('system', 'pubkey'),
            'hubloc_updated' => datetime_convert(),
            'hubloc_id_url' => channel_url($channel),
            'hubloc_site_id' => Libzot::make_xchan_hash(z_root(), Config::Get('system', 'pubkey')),
        ]);

        if ($seize) {
            q(
                "update hubloc set hubloc_primary = 0 where hubloc_primary = 1 and hubloc_hash = '%s' and hubloc_url != '%s'",
                dbesc($channel['channel_hash']),
                dbesc(z_root())
            );

            // Replace any existing xchan we may have on this site if we're seizing control.
            q("delete from xchan where xchan_hash = '%s'", dbesc($channel['channel_hash']));
            xchan_store_lowlevel([
                'xchan_hash' => $channel['channel_hash'],
                'xchan_guid' => $channel['channel_guid'],
                'xchan_guid_sig' => $channel['channel_guid_sig'],
                'xchan_pubkey' => $channel['channel_pubkey'],
                'xchan_photo_l' => z_root() . '/photo/profile/l/' . $channel['channel_id'],
                'xchan_photo_m' => z_root() . '/photo/profile/m/' . $channel['channel_id'],
                'xchan_photo_s' => z_root() . '/photo/profile/s/' . $channel['channel_id'],
                'xchan_addr' => channel_reddress($channel),
                'xchan_url' => z_root() . '/channel/' . $channel['channel_address'],
                'xchan_connurl' => z_root() . '/poco/' . $channel['channel_address'],
                'xchan_follow' => z_root() . '/follow?f=&url=%s',
                'xchan_name' => $channel['channel_name'],
                'xchan_network' => 'zot6',
                'xchan_photo_date' => datetime_convert(),
                'xchan_name_date' => datetime_convert(),
            ]);
        }

        // Import xchans and contact photos.
        $xchans = $data['xchan'] ?? null;
        if ($xchans) {
            require_once('include/photo/photo_driver.php');
            foreach ($xchans as $xchan) {
                if (($xchan['xchan_network'] ?? '') === 'zot6') {
                    $zhash = Libzot::make_xchan_hash($xchan['xchan_guid'], $xchan['xchan_pubkey']);
                    if ($zhash !== $xchan['xchan_hash']) {
                        continue;
                    }
                }

                if (!array_key_exists('xchan_hidden', $xchan)) {
                    $xchan['xchan_hidden'] = (($xchan['xchan_flags'] & 0x0001) ? 1 : 0);
                    $xchan['xchan_orphan'] = (($xchan['xchan_flags'] & 0x0002) ? 1 : 0);
                    $xchan['xchan_censored'] = (($xchan['xchan_flags'] & 0x0004) ? 1 : 0);
                    $xchan['xchan_selfcensored'] = (($xchan['xchan_flags'] & 0x0008) ? 1 : 0);
                    $xchan['xchan_system'] = (($xchan['xchan_flags'] & 0x0010) ? 1 : 0);
                    $xchan['xchan_pubforum'] = (($xchan['xchan_flags'] & 0x0020) ? 1 : 0);
                    $xchan['xchan_deleted'] = (($xchan['xchan_flags'] & 0x1000) ? 1 : 0);
                }

                $r = q("select xchan_hash from xchan where xchan_hash = '%s' limit 1", dbesc($xchan['xchan_hash']));
                if ($r) {
                    continue;
                }

                create_table_from_array('xchan', $xchan);

                if ($xchan['xchan_hash'] === $channel['channel_hash']) {
                    q(
                        "update xchan set xchan_photo_l = '%s', xchan_photo_m = '%s', xchan_photo_s = '%s' where xchan_hash = '%s'",
                        dbesc(z_root() . '/photo/profile/l/' . $channel['channel_id']),
                        dbesc(z_root() . '/photo/profile/m/' . $channel['channel_id']),
                        dbesc(z_root() . '/photo/profile/s/' . $channel['channel_id']),
                        dbesc($xchan['xchan_hash'])
                    );
                } else {
                    $photos = import_xchan_photo($xchan['xchan_photo_l'], $xchan['xchan_hash']);
                    $photodate = $photos[4] ? DBA::$dba->get_null_date() : $xchan['xchan_photo_date'];
                    q(
                        "update xchan set xchan_photo_l = '%s', xchan_photo_m = '%s', xchan_photo_s = '%s', xchan_photo_mimetype = '%s', xchan_photo_date = '%s' where xchan_hash = '%s'",
                        dbesc($photos[0]),
                        dbesc($photos[1]),
                        dbesc($photos[2]),
                        dbesc($photos[3]),
                        dbesc($photodate),
                        dbesc($xchan['xchan_hash'])
                    );
                }
            }
        }

        // Must happen after xchans are imported.
        if (is_array($data['hubloc'] ?? null)) {
            import_hublocs($channel, $data['hubloc'], $seize, $moving);
        }

        $friends = 0;
        $feeds = 0;

        $abooks = $data['abook'] ?? null;
        if ($abooks) {
            foreach ($abooks as $abook) {
                $abook_copy = $abook;

                $abconfig = null;
                if (array_key_exists('abconfig', $abook) && is_array($abook['abconfig']) && count($abook['abconfig'])) {
                    $abconfig = $abook['abconfig'];
                }

                unset($abook['abook_id'], $abook['abook_rating'], $abook['abook_rating_text'], $abook['abconfig'], $abook['abook_their_perms'], $abook['abook_my_perms'], $abook['abook_not_here']);

                $abook['abook_account'] = $account_id;
                $abook['abook_channel'] = $channel['channel_id'];
                if (!array_key_exists('abook_blocked', $abook)) {
                    $abook['abook_blocked'] = (($abook['abook_flags'] & 0x0001) ? 1 : 0);
                    $abook['abook_ignored'] = (($abook['abook_flags'] & 0x0002) ? 1 : 0);
                    $abook['abook_hidden'] = (($abook['abook_flags'] & 0x0004) ? 1 : 0);
                    $abook['abook_archived'] = (($abook['abook_flags'] & 0x0008) ? 1 : 0);
                    $abook['abook_pending'] = (($abook['abook_flags'] & 0x0010) ? 1 : 0);
                    $abook['abook_unconnected'] = (($abook['abook_flags'] & 0x0020) ? 1 : 0);
                    $abook['abook_self'] = (($abook['abook_flags'] & 0x0080) ? 1 : 0);
                    $abook['abook_feed'] = (($abook['abook_flags'] & 0x0100) ? 1 : 0);
                }

                if (array_key_exists('abook_instance', $abook) && $abook['abook_instance'] && strpos($abook['abook_instance'], z_root()) === false) {
                    $abook['abook_not_here'] = 1;
                }

                if ($abook['abook_self']) {
                    $role = get_pconfig($channel['channel_id'], 'system', 'permissions_role');
                    if (($role === 'forum') || ($abook['abook_my_perms'] & PERMS_W_TAGWALL)) {
                        q("update xchan set xchan_pubforum = 1 where xchan_hash = '%s' ", dbesc($abook['abook_xchan']));
                    }
                } else {
                    if ($max_friends !== false && $friends > $max_friends) {
                        continue;
                    }
                    if ($max_feeds !== false && intval($abook['abook_feed']) && ($feeds > $max_feeds)) {
                        continue;
                    }
                }

                $r = q(
                    "select abook_id from abook where abook_xchan = '%s' and abook_channel = %d limit 1",
                    dbesc($abook['abook_xchan']),
                    intval($channel['channel_id'])
                );
                if ($r) {
                    foreach ($abook as $k => $v) {
                        q(
                            "UPDATE abook SET " . TQUOT . "%s" . TQUOT . " = '%s' WHERE abook_xchan = '%s' AND abook_channel = %d",
                            dbesc($k),
                            dbesc($v),
                            dbesc($abook['abook_xchan']),
                            intval($channel['channel_id'])
                        );
                    }
                } else {
                    abook_store_lowlevel($abook);
                    $friends++;
                    if (intval($abook['abook_feed'])) {
                        $feeds++;
                    }
                }

                translate_abook_perms_inbound($channel, $abook_copy);

                if ($abconfig) {
                    foreach ($abconfig as $abc) {
                        set_abconfig($channel['channel_id'], $abc['xchan'], $abc['cat'], $abc['k'], $abc['v']);
                    }
                }
            }
        }

        // Import privacy groups (collections) and their members.
        $saved = [];
        $groups = $data['group'] ?? null;
        if ($groups) {
            foreach ($groups as $group) {
                $saved[$group['hash']] = ['old' => $group['id']];
                if (array_key_exists('name', $group)) {
                    $group['gname'] = $group['name'];
                    unset($group['name']);
                }
                unset($group['id']);
                $group['uid'] = $channel['channel_id'];
                create_table_from_array('pgrp', $group);
            }
            $r = q("select * from pgrp where uid = %d", intval($channel['channel_id']));
            if ($r) {
                foreach ($r as $rr) {
                    $saved[$rr['hash']]['new'] = $rr['id'];
                }
            }
        }

        $group_members = $data['group_member'] ?? null;
        if ($group_members) {
            foreach ($group_members as $group_member) {
                unset($group_member['id']);
                $group_member['uid'] = $channel['channel_id'];
                foreach ($saved as $x) {
                    if ($x['old'] == $group_member['gid']) {
                        $group_member['gid'] = $x['new'];
                    }
                }
                create_table_from_array('pgrp_member', $group_member);
            }
        }

        if (is_array($data['obj'] ?? null)) {
            import_objs($channel, $data['obj']);
        }
        if (is_array($data['likes'] ?? null)) {
            import_likes($channel, $data['likes']);
        }
        if (is_array($data['app'] ?? null)) {
            import_apps($channel, $data['app']);
        }
        if (is_array($data['sysapp'] ?? null)) {
            import_sysapps($channel, $data['sysapp']);
        }
        if (is_array($data['chatroom'] ?? null)) {
            import_chatrooms($channel, $data['chatroom']);
        }
        if (is_array($data['event'] ?? null)) {
            import_events($channel, $data['event']);
        }
        if (is_array($data['event_item'] ?? null)) {
            import_items($channel, $data['event_item'], false, $relocate);
        }
        if (is_array($data['menu'] ?? null)) {
            import_menus($channel, $data['menu']);
        }
        if (is_array($data['wiki'] ?? null)) {
            import_items($channel, $data['wiki'], false, $relocate);
        }
        if (is_array($data['webpages'] ?? null)) {
            import_items($channel, $data['webpages'], false, $relocate);
        }

        $addon = ['channel' => $channel, 'data' => $data];
        call_hooks('import_channel', $addon);

        // Immediately notify the old server (and known contacts) about the new clone.
        Master::Summon(['Notifier', 'refresh_all', $channel['channel_id']]);
        // Indirectly performs a refresh_all *and* updates the directory.
        Master::Summon(['Directory', $channel['channel_id']]);

        change_channel($channel['channel_id']);

        return [
            'channel_id' => intval($channel['channel_id']),
            'nick' => $channel['channel_address'],
            'redirect' => z_root(),
        ];
    }
}
