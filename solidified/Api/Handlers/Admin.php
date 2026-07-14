<?php
namespace Theme\Solidified\Api\Handlers;

use Theme\Solidified\Api\Auth;
use Theme\Solidified\Api\Response;
use Zotlabs\Lib\Config;
use App;

class Admin
{
    private static function cfgStr(string $k, string $default = ''): string
    {
        $v = Config::Get('system', $k);
        if ($v === false || $v === null || is_array($v)) return $default;
        return (string) $v;
    }

    private static function cfgInt(string $k, int $default = 0): int
    {
        $v = Config::Get('system', $k);
        if ($v === false || $v === null || is_array($v)) return $default;
        return intval($v);
    }

    private static function argv(int $n): string
    {
        return (string) (App::$argv[$n] ?? '');
    }

    private static function cfgBool(string $k, bool $default = false): bool
    {
        $v = Config::Get('system', $k);
        if ($v === false || $v === null || is_array($v)) return $default;
        return (bool) intval($v);
    }

    private function requireAdmin(): void
    {
        if (!local_channel() || !is_site_admin()) {
            Response::error(403, 'Permission denied');
        }
    }

    public function get(): void
    {
        $this->requireAdmin();

        $section = App::$argv[2] ?? 'summary';

        switch ($section) {
            case 'summary':        $this->getSummary();       break;
            case 'site':           $this->getSite();          break;
            case 'accounts':       $this->getAccounts();      break;
            case 'channels':       $this->getChannels();      break;
            case 'security':       $this->getSecurity();      break;
            case 'features':       $this->getFeatures();      break;
            case 'addons':         $this->getAddons();        break;
            case 'themes':
                if (($this->argv(3)) === 'options') {
                    $this->getThemeOptions();
                } else {
                    $this->getThemes();
                }
                break;
            case 'inspect-queue':  $this->getQueue();         break;
            case 'queueworker':    $this->getQueueworker();    break;
            case 'profile-fields': $this->getProfileFields(); break;
            case 'db-updates':     $this->getDbUpdates();     break;
            case 'logs':           $this->getLogs();          break;
            default:
                Response::error(404, "Unknown admin section: {$section}");
        }
    }

    public function post(): void
    {
        Auth::requireLocalJson();
        $this->requireAdmin();

        $section = App::$argv[2] ?? '';

        switch ($section) {
            case 'site':      $this->postSite();     break;
            case 'accounts':  $this->postAccounts(); break;
            case 'channels':  $this->postChannels(); break;
            case 'security':  $this->postSecurity(); break;
            case 'features':        $this->postFeatures();       break;
            case 'addons':          $this->postAddons();         break;
            case 'themes':          $this->postThemes();         break;
            case 'profile-fields':  $this->postProfileFields();  break;
            case 'logs':            $this->postLogs();           break;
            case 'queueworker':     $this->postQueueworker();    break;
            default:
                Response::error(404, "Unknown admin section: {$section}");
        }
    }

    // ── Summary ───────────────────────────────────────────────────────────────

    private function getSummary(): void
    {
        require_once('include/account.php');

        $r = q("SELECT
            COUNT(CASE WHEN account_id > 0 THEN 1 ELSE NULL END) AS total,
            COUNT(CASE WHEN account_expires > %s THEN 1 ELSE NULL END) AS expiring,
            COUNT(CASE WHEN account_expires < %s AND account_expires > '%s' THEN 1 ELSE NULL END) AS expired,
            COUNT(CASE WHEN (account_flags & %d) > 0 THEN 1 ELSE NULL END) AS blocked
            FROM account",
            db_utcnow(), db_utcnow(),
            dbesc(\DBA::$dba->get_null_date()),
            intval(ACCOUNT_BLOCKED)
        );

        $accounts = [
            'total'    => intval($r[0]['total'] ?? 0),
            'blocked'  => intval($r[0]['blocked'] ?? 0),
            'expired'  => intval($r[0]['expired'] ?? 0),
            'expiring' => intval($r[0]['expiring'] ?? 0),
        ];

        $pdg = q("SELECT COUNT(*) AS pdg FROM register WHERE reg_vital = 1 AND reg_expires > '%s'",
            dbesc(date('Y-m-d H:i:s'))
        );

        $cr = q("SELECT COUNT(*) AS total FROM channel WHERE channel_removed = 0 AND channel_system = 0");

        $qr = q("SELECT COUNT(outq_delivered) AS total FROM outq WHERE outq_delivered = 0");

        $plugins = App::$plugins;
        sort($plugins);

        Response::send([
            'accounts' => $accounts,
            'pending'  => intval($pdg[0]['pdg'] ?? 0),
            'channels' => intval($cr[0]['total'] ?? 0),
            'queue'    => intval($qr[0]['total'] ?? 0),
            'plugins'  => $plugins,
            'version'  => STD_VERSION,
        ]);
    }

    // ── Site ──────────────────────────────────────────────────────────────────

    private function getSite(): void
    {
        Response::send([
            // Basic identity
            'sitename'                 => self::cfgStr('sitename'),
            'banner'                   => self::cfgStr('banner'),
            'sitelogo_512'             => self::cfgStr('sitelogo_512'),
            'sitelogo_192'             => self::cfgStr('sitelogo_192'),
            'sitelogo_favicon'         => self::cfgStr('sitelogo_favicon'),
            'admininfo'                => self::cfgStr('admininfo'),
            'siteinfo'                 => self::cfgStr('siteinfo'),
            'site_location'            => self::cfgStr('site_location'),
            'language'                 => self::cfgStr('language', 'en'),
            'theme'                    => self::cfgStr('theme', 'redbasic'),
            'default_permissions_role' => self::cfgStr('default_permissions_role', 'personal'),

            // Registration
            'register_policy'          => self::cfgInt('register_policy', REGISTER_CLOSED),
            'access_policy'            => self::cfgInt('access_policy', ACCESS_FREE),
            'max_daily_registrations'  => self::cfgInt('max_daily_registrations', 50),
            'register_text'            => self::cfgStr('register_text'),
            'minimum_age'              => self::cfgInt('minimum_age', 13),
            'verify_email'             => self::cfgBool('verify_email'),
            'register_wo_email'        => self::cfgBool('register_wo_email'),
            'register_sameip'          => self::cfgInt('register_sameip', 3),
            'auto_channel_create'      => self::cfgBool('auto_channel_create', true),
            'invitation_only'          => self::cfgBool('invitation_only'),
            'invitation_also'          => self::cfgBool('invitation_also'),
            'abandon_days'             => self::cfgInt('account_abandon_days'),

            // Content & visibility
            'login_on_homepage'        => self::cfgBool('login_on_homepage'),
            'disable_discover_tab'     => self::cfgBool('disable_discover_tab', true),
            'site_firehose'            => self::cfgBool('site_firehose'),
            'open_pubstream'           => self::cfgBool('open_pubstream'),
            'publish_all'              => self::cfgBool('publish_all'),
            'no_community_page'        => self::cfgBool('no_community_page'),
            'frontpage'                => self::cfgStr('frontpage'),
            'site_sellpage'            => self::cfgStr('sellpage'),
            'first_page'               => self::cfgStr('workflow_channel_next', 'profiles'),
            'mirror_frontpage'         => self::cfgBool('mirror_frontpage'),
            'allowed_sites'            => self::cfgStr('allowed_sites'),
            'pubstream_incl'           => self::cfgStr('pubstream_incl'),
            'pubstream_excl'           => self::cfgStr('pubstream_excl'),

            // Email
            'directory_server'         => self::cfgStr('directory_server'),
            'from_email'               => self::cfgStr('from_email'),
            'from_email_name'          => self::cfgStr('from_email_name'),
            'reply_address'            => self::cfgStr('reply_address'),

            // Upload limits
            'maximagesize'             => self::cfgInt('maximagesize'),

            // Behavior
            'enable_context_help'      => self::cfgBool('enable_context_help'),
            'sse_enabled'              => self::cfgBool('sse_enabled'),
            'feed_contacts'            => self::cfgBool('feed_contacts'),

            // Advanced / technical
            'verifyssl'                => self::cfgBool('verifyssl', true),
            'proxyuser'                => self::cfgStr('proxyuser'),
            'proxy'                    => self::cfgStr('proxy'),
            'curl_timeout'             => self::cfgInt('curl_timeout', 60),
            'delivery_interval'        => self::cfgInt('delivery_interval', 2),
            'delivery_batch_count'     => self::cfgInt('delivery_batch_count', 1),
            'poll_interval'            => self::cfgInt('poll_interval', 2),
            'imagick_path'             => self::cfgStr('imagick_convert_path'),
            'maxloadavg'               => self::cfgInt('maxloadavg', 50),
            'default_expire_days'      => self::cfgInt('default_expire_days', 30),
            'active_expire_days'       => self::cfgInt('active_expire_days', 7),
        ]);
    }

    private function postSite(): void
    {
        $data = Auth::$parsedBody;

        $str_fields  = [
            'sitename', 'banner', 'admininfo', 'siteinfo', 'site_location',
            'language', 'theme', 'default_permissions_role',
            'register_text', 'directory_server',
            'from_email', 'from_email_name', 'reply_address',
            'allowed_sites', 'pubstream_incl', 'pubstream_excl',
            'proxyuser', 'proxy',
        ];
        $int_fields  = [
            'register_policy', 'access_policy', 'max_daily_registrations',
            'minimum_age', 'register_sameip',
            'maximagesize', 'curl_timeout', 'delivery_interval',
            'delivery_batch_count', 'poll_interval', 'maxloadavg',
            'default_expire_days', 'active_expire_days',
        ];
        $bool_fields = [
            'login_on_homepage', 'disable_discover_tab', 'site_firehose', 'open_pubstream',
            'publish_all', 'no_community_page', 'mirror_frontpage',
            'verify_email', 'register_wo_email', 'auto_channel_create',
            'invitation_only', 'invitation_also',
            'enable_context_help', 'sse_enabled', 'feed_contacts', 'verifyssl',
        ];

        foreach ($str_fields as $k)
            if (isset($data[$k]))
                Config::Set('system', $k, notags(trim((string) $data[$k])));

        foreach ($int_fields as $k)
            if (isset($data[$k]))
                Config::Set('system', $k, intval($data[$k]));

        foreach ($bool_fields as $k)
            if (isset($data[$k]))
                Config::Set('system', $k, intval((bool) $data[$k]));

        // Fields where API name differs from config key
        if (isset($data['abandon_days']))
            Config::Set('system', 'account_abandon_days', intval($data['abandon_days']));
        if (isset($data['site_sellpage']))
            Config::Set('system', 'sellpage', notags(trim((string) $data['site_sellpage'])));
        if (isset($data['first_page']))
            Config::Set('system', 'workflow_channel_next', notags(trim((string) $data['first_page'])));
        if (isset($data['frontpage']))
            Config::Set('system', 'frontpage', notags(trim((string) $data['frontpage'])));
        if (isset($data['imagick_path']))
            Config::Set('system', 'imagick_convert_path', trim((string) $data['imagick_path']));

        Response::send(['status' => 'ok']);
    }

    // ── Accounts ──────────────────────────────────────────────────────────────

    private function getAccounts(): void
    {
        require_once('include/account.php');

        $page   = max(0, intval($_GET['page'] ?? 0));
        $limit  = 50;
        $offset = $page * $limit;

        $total_r = q("SELECT COUNT(*) AS total FROM account WHERE account_flags != %d",
            intval(ACCOUNT_BLOCKED | ACCOUNT_PENDING)
        );
        $total = intval($total_r[0]['total'] ?? 0);

        $users = q("SELECT account_id, account_email, account_lastlog, account_created,
            account_expires, account_service_class,
            (account_flags & %d) > 0 AS blocked,
            (SELECT %s FROM channel AS ch
             WHERE ch.channel_account_id = ac.account_id AND ch.channel_removed = 0) AS channels
            FROM account AS ac
            WHERE account_flags != %d
            ORDER BY account_id DESC
            LIMIT " . intval($limit) . " OFFSET " . intval($offset),
            intval(ACCOUNT_BLOCKED),
            db_concat('ch.channel_address', ' '),
            intval(ACCOUNT_BLOCKED | ACCOUNT_PENDING)
        );

        $raw_pending = get_pending_accounts(true);
        $pending = [];
        foreach ($raw_pending ?: [] as $p) {
            $stuff = json_decode($p['reg_stuff'] ?? '', true) ?: [];
            $pending[] = [
                'reg_id'      => intval($p['reg_id']),
                'reg_hash'    => (string) $p['reg_hash'],
                'reg_email'   => (string) $p['reg_email'],
                'reg_created' => (string) $p['reg_created'],
                'reg_expires' => (string) $p['reg_expires'],
                'reg_atip'    => (string) $p['reg_atip'],
                'msg'         => (string) ($stuff['msg'] ?? ''),
                'unverified'  => (bool) ($p['reg_flags'] & ACCOUNT_UNVERIFIED),
                'expired'     => $p['reg_expires'] < datetime_convert(),
            ];
        }

        Response::send([
            'data'    => $users ?: [],
            'meta'    => [
                'offset'     => $offset,
                'limit'      => $limit,
                'count'      => count($users ?: []),
                'root_count' => $total,
                'has_more'   => ($offset + $limit) < $total,
            ],
            'pending' => $pending,
        ]);
    }

    private function postAccounts(): void
    {
        require_once('include/account.php');

        $data   = Auth::$parsedBody;
        $action = $data['action'] ?? '';

        switch ($action) {
            case 'block':
            case 'unblock':
            case 'delete':
                $uid = intval($data['account_id'] ?? 0);
                if (!$uid)
                    Response::error(400, 'account_id required');
                if ($action === 'block')
                    q("UPDATE account SET account_flags = (account_flags | %d) WHERE account_id = %d",
                        intval(ACCOUNT_BLOCKED), $uid);
                elseif ($action === 'unblock')
                    q("UPDATE account SET account_flags = (account_flags & ~%d) WHERE account_id = %d",
                        intval(ACCOUNT_BLOCKED), $uid);
                else
                    account_remove($uid, true, false);
                break;

            case 'approve':
                $reg_id = intval($data['reg_id'] ?? 0);
                if (!$reg_id)
                    Response::error(400, 'reg_id required');

                // Clear unverified (0x01) and pending-review (0x10) flags; admin approval overrides email verification
                q("UPDATE register SET reg_flags = (reg_flags & ~17),
                    reg_vital = (CASE (reg_flags & ~48) WHEN 0 THEN 0 ELSE 1 END)
                    WHERE reg_vital = 1 AND reg_id = %d",
                    $reg_id
                );

                $rs = q("SELECT * FROM register WHERE reg_id = %d", $reg_id);
                if (!$rs)
                    Response::error(404, 'Registration not found');

                if (($rs[0]['reg_flags'] & ~48) == 0) {
                    $ac = create_account_from_register($rs[0]);
                    if (!$ac['success'])
                        Response::error(500, 'Account creation failed: ' . ($ac['message'] ?? ''));

                    $auto_create = Config::Get('system', 'auto_channel_create', 1);
                    if ($auto_create) {
                        $stuff = json_decode($rs[0]['reg_stuff'] ?? '', true) ?: [];
                        if (!empty($stuff['chan.name']))
                            set_aconfig($ac['account']['account_id'], 'register', 'channel_name', $stuff['chan.name']);
                        if (!empty($stuff['chan.did1']))
                            set_aconfig($ac['account']['account_id'], 'register', 'channel_address', $stuff['chan.did1']);
                        $role = Config::Get('system', 'default_permissions_role');
                        if ($role)
                            set_aconfig($ac['account']['account_id'], 'register', 'permissions_role', $role);
                        auto_channel_create($ac['account']['account_id']);
                    }
                }
                break;

            case 'deny':
                $reg_id = intval($data['reg_id'] ?? 0);
                if (!$reg_id)
                    Response::error(400, 'reg_id required');

                $rs = q("SELECT * FROM register WHERE reg_id = %d AND reg_vital = 1", $reg_id);
                if (!$rs)
                    Response::error(404, 'Registration not found');

                if (intval($rs[0]['reg_uid'])) {
                    q("DELETE FROM account WHERE account_id = %d", intval($rs[0]['reg_uid']));
                }
                q("UPDATE register SET reg_vital = 0 WHERE reg_id = %d AND reg_vital = 1", $reg_id);
                break;

            default:
                Response::error(400, "Unknown action: {$action}");
        }

        Response::send(['status' => 'ok']);
    }

    // ── Channels ──────────────────────────────────────────────────────────────

    private function getChannels(): void
    {
        $page   = max(0, intval($_GET['page'] ?? 0));
        $limit  = 50;
        $offset = $page * $limit;

        $total_r = q("select count(*) as total from channel where channel_removed = 0 and channel_system = 0");
        $total   = intval($total_r[0]['total'] ?? 0);

        $rows = q("select * from channel where channel_removed = 0 and channel_system = 0 order by channel_id desc limit %d offset %d",
            intval($limit),
            intval($offset)
        );

        $result = [];
        foreach ($rows ?: [] as $ch) {
            $result[] = [
                'channel_id'         => intval($ch['channel_id']),
                'channel_name'       => (string) $ch['channel_name'],
                'channel_address'    => (string) $ch['channel_address'],
                'channel_created'    => (string) ($ch['channel_active'] ?? ''),
                'channel_lastpost'   => (string) $ch['channel_lastpost'],
                'channel_account_id' => intval($ch['channel_account_id']),
                'blocked'            => (bool) (intval($ch['channel_pageflags']) & PAGE_CENSORED),
                'allowcode'          => (bool) (intval($ch['channel_pageflags']) & PAGE_ALLOWCODE),
            ];
        }

        Response::paginate($result, $offset, $limit, $total);
    }

    private function postChannels(): void
    {
        require_once('include/channel.php');

        $data   = Auth::$parsedBody;
        $action = $data['action'] ?? '';
        $uid    = intval($data['channel_id'] ?? 0);

        if (!$uid)
            Response::error(400, 'channel_id required');

        $channel = channelx_by_n($uid);
        if (!$channel)
            Response::error(404, 'Channel not found');

        switch ($action) {
            case 'block':
                $pflags = intval($channel['channel_pageflags']) | PAGE_CENSORED;
                q("UPDATE channel SET channel_pageflags = %d WHERE channel_id = %d", $pflags, $uid);
                q("UPDATE xchan SET xchan_censored = 1 WHERE xchan_hash = '%s'",
                    dbesc($channel['channel_hash']));
                \Zotlabs\Daemon\Master::Summon(['Directory', $uid, 'nopush']);
                break;

            case 'unblock':
                $pflags = intval($channel['channel_pageflags']) & ~PAGE_CENSORED;
                q("UPDATE channel SET channel_pageflags = %d WHERE channel_id = %d", $pflags, $uid);
                q("UPDATE xchan SET xchan_censored = 0 WHERE xchan_hash = '%s'",
                    dbesc($channel['channel_hash']));
                \Zotlabs\Daemon\Master::Summon(['Directory', $uid, 'nopush']);
                break;

            case 'allowcode':
                $pflags = intval($channel['channel_pageflags']) | PAGE_ALLOWCODE;
                q("UPDATE channel SET channel_pageflags = %d WHERE channel_id = %d", $pflags, $uid);
                break;

            case 'disallowcode':
                $pflags = intval($channel['channel_pageflags']) & ~PAGE_ALLOWCODE;
                q("UPDATE channel SET channel_pageflags = %d WHERE channel_id = %d", $pflags, $uid);
                break;

            case 'delete':
                channel_remove($uid, true);
                break;

            default:
                Response::error(400, "Unknown action: {$action}");
        }

        Response::send(['status' => 'ok']);
    }

    // ── Security ──────────────────────────────────────────────────────────────

    private function getSecurity(): void
    {
        Response::send([
            'block_public'              => self::cfgBool('block_public'),
            'cloud_disable_siteroot'    => self::cfgBool('cloud_disable_siteroot'),
            'cloud_report_disksize'     => self::cfgBool('cloud_report_disksize'),
            'allowed_email'             => self::cfgStr('allowed_email'),
            'not_allowed_email'         => self::cfgStr('not_allowed_email'),
            'whitelisted_sites'         => self::cfgStr('whitelisted_sites'),
            'blacklisted_sites'         => self::cfgStr('blacklisted_sites'),
            'whitelisted_channels'      => self::cfgStr('whitelisted_channels'),
            'blacklisted_channels'      => self::cfgStr('blacklisted_channels'),
            'embed_allow'               => self::cfgStr('embed_allow'),
            'embed_deny'                => self::cfgStr('embed_deny'),
            'embed_sslonly'             => self::cfgBool('embed_sslonly'),
            'transport_security_header' => self::cfgBool('transport_security_header'),
            'content_security_policy'   => self::cfgBool('content_security_policy'),
            'trusted_directory_servers' => self::cfgStr('trusted_directory_servers'),
        ]);
    }

    private function postSecurity(): void
    {
        $data = Auth::$parsedBody;

        $str_fields  = ['allowed_email', 'not_allowed_email', 'whitelisted_sites', 'blacklisted_sites',
                         'whitelisted_channels', 'blacklisted_channels', 'embed_allow', 'embed_deny',
                         'trusted_directory_servers'];
        $bool_fields = ['block_public', 'cloud_disable_siteroot', 'cloud_report_disksize',
                         'embed_sslonly', 'transport_security_header', 'content_security_policy'];

        foreach ($str_fields as $k)
            if (isset($data[$k]))
                Config::Set('system', $k, trim((string) $data[$k]));

        foreach ($bool_fields as $k)
            if (isset($data[$k]))
                Config::Set('system', $k, intval((bool) $data[$k]));

        Response::send(['status' => 'ok']);
    }

    // ── Features ──────────────────────────────────────────────────────────────

    private function getFeatures(): void
    {
        require_once('include/features.php');

        $raw      = get_features(false);
        $sections = [];

        foreach ($raw as $cat_key => $cat_data) {
            $items = [];
            foreach (array_slice($cat_data, 1) as $f) {
                $val = Config::Get('feature', $f[0]);
                if ($val === false)
                    $val = $f[3];
                $items[] = [
                    'id'      => $f[0],
                    'label'   => $f[1],
                    'desc'    => $f[2],
                    'enabled' => (bool) $val,
                    'locked'  => ($f[4] !== false),
                ];
            }
            $sections[] = [
                'key'   => $cat_key,
                'label' => $cat_data[0],
                'items' => $items,
            ];
        }

        Response::send(['sections' => $sections]);
    }

    private function postFeatures(): void
    {
        require_once('include/features.php');

        $data     = Auth::$parsedBody;
        $raw      = get_features(false);
        $all_keys = [];

        foreach ($raw as $cat_data) {
            foreach (array_slice($cat_data, 1) as $f) {
                $all_keys[] = $f[0];
            }
        }

        foreach ($all_keys as $key) {
            if (array_key_exists($key, $data)) {
                Config::Set('feature', $key, intval((bool) $data[$key]));
            }
        }

        Response::send(['status' => 'ok']);
    }

    // ── Addons ────────────────────────────────────────────────────────────────

    private function getAddons(): void
    {
        require_once('include/plugin.php');

        $addons = [];
        $files  = glob('addon/*/*.php');

        if ($files) {
            foreach ($files as $file) {
                $name = basename($file, '.php');
                $dir  = basename(dirname($file));
                if ($name !== $dir)
                    continue;

                $info     = get_plugin_info($name);
                $addons[] = [
                    'slug'        => $name,
                    'name'        => $info['name'] ?? $name,
                    'description' => $info['description'] ?? '',
                    'version'     => $info['version'] ?? '',
                    'author'      => is_array($info['author'] ?? null)
                        ? implode(', ', array_column($info['author'], 'name'))
                        : ($info['author'] ?? ''),
                    'installed'   => plugin_is_installed($name),
                    'active'      => in_array($name, App::$plugins, true),
                ];
            }
        }

        usort($addons, fn($a, $b) => strcasecmp($a['name'], $b['name']));
        Response::send(['addons' => $addons]);
    }

    // Mirrors Zotlabs\Module\Admin\Addons::get()'s "a=t" toggle branch —
    // install_plugin()/uninstall_plugin() run the addon's own _install()/
    // _uninstall() hook functions (hook table registration), not just a
    // config flip, so we call them the same way core does rather than only
    // touching the "system.addon" list ourselves.
    private function postAddons(): void
    {
        $data   = Auth::$parsedBody;
        $action = $data['action'] ?? '';

        if ($action !== 'toggle') {
            Response::error(400, 'Unknown action');
        }

        $name = basename($data['name'] ?? '');
        if (!$name || !is_file("addon/$name/$name.php")) {
            Response::error(400, 'Invalid addon');
        }

        require_once('include/plugin.php');

        $idx = array_search($name, App::$plugins);
        if ($idx !== false) {
            unset(App::$plugins[$idx]);
            uninstall_plugin($name);
            $active = false;
        } else {
            App::$plugins[] = $name;
            install_plugin($name);
            $active = true;
        }
        Config::Set('system', 'addon', implode(', ', App::$plugins));

        Response::send(['name' => $name, 'active' => $active]);
    }

    // ── Themes ────────────────────────────────────────────────────────────────

    private function getThemes(): void
    {
        $current       = self::cfgStr('theme', 'redbasic');
        $allowed_str   = self::cfgStr('allowed_themes', '');
        $allowed_list  = array_filter(array_map('trim', explode(',', $allowed_str)));
        $themes        = [];
        $files         = glob('view/theme/*');

        if ($files) {
            foreach ($files as $file) {
                if (!is_dir($file)) continue;
                $name   = basename($file);
                $info   = get_theme_info($name);
                $themes[] = [
                    'name'         => $name,
                    'description'  => $info['description'] ?? '',
                    'version'      => $info['version'] ?? '',
                    'compatible'   => (bool) check_plugin_versions($info),
                    'mobile'       => file_exists($file . '/mobile'),
                    'experimental' => file_exists($file . '/experimental'),
                    'current'      => ($name === $current),
                    'allowed'      => in_array($name, $allowed_list),
                    'has_config'   => is_file("view/theme/$name/php/config.php"),
                ];
            }
        }

        Response::send(['themes' => $themes, 'current' => $current]);
    }

    // ── Theme Options ─────────────────────────────────────────────────────────
    //
    // Reads keys registered by the theme in the config table (cat='theme_X').
    // Themes populate these in their {theme}_theme_admin_enable() function via
    // Config::Set defaults. No theme file modifications required.

    private function getThemeOptions(): void
    {
        $theme    = basename($_GET['theme'] ?? self::cfgStr('theme', 'redbasic'));
        $category = 'theme_' . $theme;

        $rows = q("SELECT k, v FROM config WHERE cat = '%s' ORDER BY k", dbesc($category));

        if (!$rows) {
            Response::send(['theme' => $theme, 'fields' => []]);
            return;
        }

        $schema_files = glob("view/theme/$theme/schema/*.css") ?: [];
        $schema_opts  = ['---' => 'default'];
        foreach ($schema_files as $f) {
            $n = basename($f, '.css');
            $schema_opts[$n] = $n;
        }

        $fields = [];
        foreach ($rows as $row) {
            $key   = $row['k'];
            $value = (string) $row['v'];

            if ($key === 'schema') {
                $type  = 'select';
                $extra = ['options' => $schema_opts];
            } elseif (strpos($key, 'color') !== false) {
                $type  = 'color';
                $extra = [];
            } else {
                $type  = 'text';
                $extra = [];
            }

            $field = [
                'key'   => $key,
                'type'  => $type,
                'label' => ucwords(str_replace('_', ' ', $key)),
                'hint'  => '',
                'group' => 'Options',
                'value' => $value,
            ];
            if ($extra) $field = array_merge($field, $extra);
            $fields[] = $field;
        }

        Response::send(['theme' => $theme, 'fields' => $fields]);
    }

    private function postThemes(): void
    {
        $data   = Auth::$parsedBody;
        $action = $data['action'] ?? '';

        if ($action === 'toggle') {
            $name = basename($data['theme'] ?? '');
            if (!$name || !is_dir("view/theme/$name")) {
                Response::error(400, 'Invalid theme');
            }
            $allowed_str  = self::cfgStr('allowed_themes', '');
            $allowed_list = array_filter(array_map('trim', explode(',', $allowed_str)));

            if (in_array($name, $allowed_list)) {
                $allowed_list = array_values(array_filter($allowed_list, fn($t) => $t !== $name));
            } else {
                $allowed_list[] = $name;
            }

            Config::Set('system', 'allowed_themes', implode(',', $allowed_list));
            Response::send(['allowed' => $allowed_list]);
            return;
        }

        if ($action === 'options') {
            $theme     = basename($data['theme'] ?? '');
            $form_data = $data['form_data'] ?? [];

            if (!$theme || !is_array($form_data)) {
                Response::error(400, 'theme and form_data required');
            }

            $category = 'theme_' . $theme;
            $rows     = q("SELECT k FROM config WHERE cat = '%s'", dbesc($category));
            $db_keys  = array_column($rows ?: [], 'k');

            if (empty($db_keys)) {
                Response::error(400, 'Theme has no registered config keys');
            }

            foreach ($form_data as $k => $v) {
                $k = (string) $k;
                if (!in_array($k, $db_keys, true)) continue;
                if (strpos($k, 'color') !== false) {
                    $v = preg_match('/^#([A-Fa-f0-9]{3}){1,2}$|^$/', (string) $v) ? (string) $v : '';
                } else {
                    $v = (string) $v;
                }
                Config::Set($category, $k, $v);
            }

            Response::send(['status' => 'ok']);
            return;
        }

        Response::error(400, 'Unknown action');
    }

    // ── Inspect Queue ─────────────────────────────────────────────────────────

    private function getQueue(): void
    {
        $limit   = 200;
        $items   = q("SELECT outq_hash, outq_created, outq_updated, outq_posturl,
            outq_delivered, outq_priority, outq_channel
            FROM outq
            WHERE outq_delivered = 0
            ORDER BY outq_updated DESC
            LIMIT %d", $limit);

        $total_r = q("SELECT COUNT(*) AS total FROM outq WHERE outq_delivered = 0");

        Response::send([
            'items' => $items ?: [],
            'total' => intval($total_r[0]['total'] ?? 0),
        ]);
    }

    // ── Queueworker ───────────────────────────────────────────────────────────

    private function getQueueworker(): void
    {
        $total_r  = q("SELECT COUNT(*) AS total FROM workerq");
        $active_r = q("SELECT COUNT(*) AS active FROM workerq WHERE workerq_reservationid IS NOT NULL");
        $cmds_r   = q("SELECT workerq_cmd AS cmd, COUNT(*) AS total FROM workerq GROUP BY workerq_cmd ORDER BY total DESC");

        $jobs_r = q("SELECT workerq_id AS id, workerq_priority AS priority,
                            workerq_cmd AS cmd, workerq_reservationid AS reservation_id,
                            workerq_processtimeout AS timeout
                     FROM workerq ORDER BY workerq_priority DESC, workerq_id ASC LIMIT 100");

        $cfg = function (string $k, int $default): int {
            $v = Config::Get('queueworker', $k);
            return ($v !== false && $v !== null) ? intval($v) : $default;
        };

        Response::send([
            'total'          => intval($total_r[0]['total']  ?? 0),
            'active_workers' => intval($active_r[0]['active'] ?? 0),
            'by_command'     => $cmds_r ?: [],
            'jobs'           => $jobs_r  ?: [],
            'settings'       => [
                'max_queueworkers'       => $cfg('max_queueworkers',     4),
                'queueworker_max_age'    => $cfg('queueworker_max_age',  300),
                'queue_worker_sleep'     => $cfg('queue_worker_sleep',   100),
                'auto_queue_worker_sleep'=> $cfg('auto_queue_worker_sleep', 0),
            ],
        ]);
    }

    private function postQueueworker(): void
    {
        $d = Auth::$parsedBody;

        $max  = max(4,   intval($d['max_queueworkers']        ?? 4));
        $age  = max(120, intval($d['queueworker_max_age']      ?? 300));
        $sleep = max(100, intval($d['queue_worker_sleep']      ?? 100));
        $auto = intval((bool)($d['auto_queue_worker_sleep']    ?? false));

        Config::Set('queueworker', 'max_queueworkers',        $max);
        Config::Set('queueworker', 'queueworker_max_age',     $age);
        Config::Set('queueworker', 'queue_worker_sleep',      $sleep);
        Config::Set('queueworker', 'auto_queue_worker_sleep', $auto);

        Response::send(['status' => 'ok',
            'settings' => compact('max', 'age', 'sleep', 'auto')]);
    }

    // ── Profile fields ────────────────────────────────────────────────────────

    private function getProfileFields(): void
    {
        require_once('include/channel.php');

        // Basic fields (configured or system defaults)
        $basic_map  = get_profile_fields_basic();
        if (!$basic_map) $basic_map = get_profile_fields_basic(1);
        $basic_keys = array_keys($basic_map ?: []);

        // Advanced-only fields (full advanced list minus basic)
        $adv_full = get_profile_fields_advanced();
        if (!$adv_full) $adv_full = get_profile_fields_advanced(1);
        $adv_only = array_diff(array_keys($adv_full ?: []), $basic_keys);

        // All built-in field names
        $all_builtin = array_keys(get_profile_fields_advanced(1) ?: []);

        // Custom fields from profdef
        $custom = q("SELECT id, field_name, field_type, field_desc, field_help FROM profdef ORDER BY id");
        $custom_names = array_column($custom ?: [], 'field_name');

        Response::send([
            'basic'         => implode(', ', $basic_keys),
            'advanced'      => implode(', ', array_values($adv_only)),
            'all_available' => array_values(array_unique(array_merge($all_builtin, $custom_names))),
            'custom_fields' => $custom ?: [],
        ]);
    }

    private function postProfileFields(): void
    {
        require_once('include/channel.php');
        $data   = Auth::$parsedBody;
        $action = $data['action'] ?? '';

        if ($action === 'save_layout') {
            $parse = function (string $s): array {
                return array_values(array_filter(array_map('trim', explode(',', $s))));
            };
            $basic = $parse($data['basic'] ?? '');
            $adv   = $parse($data['advanced'] ?? '');
            if ($basic) Config::Set('system', 'profile_fields_basic', $basic);
            else        Config::Delete('system', 'profile_fields_basic');
            if ($adv)   Config::Set('system', 'profile_fields_advanced', $adv);
            else        Config::Delete('system', 'profile_fields_advanced');
            Response::send(['status' => 'ok']);
            return;
        }

        if ($action === 'create') {
            $name = trim($data['field_name'] ?? '');
            $type = trim($data['field_type'] ?? 'text');
            $desc = trim($data['field_desc'] ?? '');
            $help = trim($data['field_help'] ?? '');
            if (!$name) Response::error(400, 'field_name required');
            q("INSERT INTO profdef (field_name, field_type, field_desc, field_help, field_inputs) VALUES ('%s','%s','%s','%s','')",
                dbesc($name), dbesc($type), dbesc($desc), dbesc($help));
            $row = q("SELECT id, field_name, field_type, field_desc, field_help FROM profdef WHERE field_name = '%s' ORDER BY id DESC LIMIT 1", dbesc($name));
            Response::send(['field' => $row ? $row[0] : null]);
            return;
        }

        if ($action === 'update') {
            $id   = intval($data['id'] ?? 0);
            $name = trim($data['field_name'] ?? '');
            $type = trim($data['field_type'] ?? 'text');
            $desc = trim($data['field_desc'] ?? '');
            $help = trim($data['field_help'] ?? '');
            if (!$id || !$name) Response::error(400, 'id and field_name required');
            q("UPDATE profdef SET field_name='%s', field_type='%s', field_desc='%s', field_help='%s' WHERE id=%d",
                dbesc($name), dbesc($type), dbesc($desc), dbesc($help), $id);
            Response::send(['status' => 'ok']);
            return;
        }

        if ($action === 'delete') {
            $id = intval($data['id'] ?? 0);
            if (!$id) Response::error(400, 'id required');
            q("DELETE FROM profdef WHERE id = %d", $id);
            Response::send(['status' => 'ok']);
            return;
        }

        Response::error(400, 'Unknown action');
    }

    // ── DB updates ────────────────────────────────────────────────────────────

    private function getDbUpdates(): void
    {
        $updates = q("SELECT * FROM dbstructure ORDER BY dbstructure_id DESC LIMIT 100");
        Response::send(['updates' => $updates ?: []]);
    }

    // ── Logs ──────────────────────────────────────────────────────────────────

    private function getLogs(): void
    {
        $logfile = Config::Get('system', 'logfile', '');
        $entries = [];

        if ($logfile && is_readable($logfile)) {
            $fp = fopen($logfile, 'r');
            if ($fp) {
                fseek($fp, 0, SEEK_END);
                $size = ftell($fp);
                $read = min($size, 262144); // last 256 KB
                fseek($fp, -$read, SEEK_END);
                $raw  = fread($fp, $read);
                fclose($fp);

                $all  = array_filter(explode("\n", $raw));
                $tail = array_slice($all, -500);

                // Format: {ISO8601}:{LOG_LEVEL}:{logid}:{file}:{line}:{fn}: {message}
                $re = '/^(\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:[+-]\d{2}:\d{2}|Z)):(LOG_\w+):([^:]+):([^:]+):(\d+):([^:]+): (.*)$/';

                foreach (array_reverse($tail) as $line) {
                    if (!$line) continue;
                    if (preg_match($re, $line, $m)) {
                        $entries[] = [
                            'ts'      => $m[1],
                            'level'   => $m[2],
                            'logid'   => $m[3],
                            'file'    => $m[4],
                            'line'    => intval($m[5]),
                            'fn'      => $m[6],
                            'message' => $m[7],
                        ];
                    } else {
                        // Unparseable line — carry it as-is
                        $entries[] = [
                            'ts'      => null,
                            'level'   => 'LOG_UNDEFINED',
                            'logid'   => null,
                            'file'    => null,
                            'line'    => null,
                            'fn'      => null,
                            'message' => $line,
                        ];
                    }
                }
            }
        }

        Response::send([
            'logfile'    => $logfile ?: null,
            'debugging'  => (bool) Config::Get('system', 'debugging'),
            'loglevel'   => intval(Config::Get('system', 'loglevel', 0)),
            'entries'    => $entries,
        ]);
    }

    private function postLogs(): void
    {
        $data = Auth::$parsedBody;

        $logfile   = trim($data['logfile']   ?? Config::Get('system', 'logfile', ''));
        $debugging = isset($data['debugging']) ? (bool) $data['debugging'] : (bool) Config::Get('system', 'debugging');
        $loglevel  = isset($data['loglevel'])  ? intval($data['loglevel'])  : intval(Config::Get('system', 'loglevel', 0));

        Config::Set('system', 'logfile',   $logfile);
        Config::Set('system', 'debugging', $debugging);
        Config::Set('system', 'loglevel',  $loglevel);

        Response::send(['status' => 'ok', 'debugging' => $debugging, 'logfile' => $logfile, 'loglevel' => $loglevel]);
    }
}
