<?php
namespace Theme\Solidified\Api\Handlers;

use Theme\Solidified\Api\Auth;
use Theme\Solidified\Api\Response;
use Zotlabs\Lib\Config;
use App;

class Admin
{
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
            case 'themes':         $this->getThemes();        break;
            case 'inspect-queue':  $this->getQueue();         break;
            case 'queueworker':    $this->getQueueworker();   break;
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
            case 'security':  $this->postSecurity(); break;
            case 'features':  $this->postFeatures(); break;
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
            'sitename'            => (string) Config::Get('system', 'sitename', ''),
            'banner'              => (string) Config::Get('system', 'banner', ''),
            'admininfo'           => (string) Config::Get('system', 'admininfo', ''),
            'siteinfo'            => (string) Config::Get('system', 'siteinfo', ''),
            'register_policy'     => intval(Config::Get('system', 'register_policy', REGISTER_CLOSED)),
            'access_policy'       => intval(Config::Get('system', 'access_policy', ACCESS_FREE)),
            'max_daily_registrations' => intval(Config::Get('system', 'max_daily_registrations', 50)),
            'abandon_days'        => intval(Config::Get('system', 'abandon_days', 0)),
            'login_on_homepage'   => (bool) Config::Get('system', 'login_on_homepage', false),
            'disable_discover_tab'=> (bool) intval(Config::Get('system', 'disable_discover_tab', 1)),
            'site_firehose'       => (bool) intval(Config::Get('system', 'site_firehose', 0)),
            'open_pubstream'      => (bool) intval(Config::Get('system', 'open_pubstream', 0)),
            'language'            => (string) Config::Get('system', 'language', 'en'),
            'theme'               => (string) Config::Get('system', 'theme', 'redbasic'),
            'directory_server'    => (string) Config::Get('system', 'directory_server', ''),
            'from_email'          => (string) Config::Get('system', 'from_email', ''),
            'from_email_name'     => (string) Config::Get('system', 'from_email_name', ''),
            'reply_address'       => (string) Config::Get('system', 'reply_address', ''),
            'maximagesize'        => intval(Config::Get('system', 'maximagesize', 0)),
            'site_location'       => (string) Config::Get('system', 'site_location', ''),
        ]);
    }

    private function postSite(): void
    {
        $data = Auth::$parsedBody;

        $str_fields  = ['sitename', 'banner', 'admininfo', 'siteinfo', 'language', 'theme',
                         'directory_server', 'from_email', 'from_email_name', 'reply_address', 'site_location'];
        $int_fields  = ['register_policy', 'access_policy', 'max_daily_registrations', 'abandon_days', 'maximagesize'];
        $bool_fields = ['login_on_homepage', 'disable_discover_tab', 'site_firehose', 'open_pubstream'];

        foreach ($str_fields as $k)
            if (isset($data[$k]))
                Config::Set('system', $k, notags(trim((string) $data[$k])));

        foreach ($int_fields as $k)
            if (isset($data[$k]))
                Config::Set('system', $k, intval($data[$k]));

        foreach ($bool_fields as $k)
            if (isset($data[$k]))
                Config::Set('system', $k, intval((bool) $data[$k]));

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
            LIMIT %d OFFSET %d",
            intval(ACCOUNT_BLOCKED),
            db_concat('ch.channel_address', ' '),
            intval(ACCOUNT_BLOCKED | ACCOUNT_PENDING),
            $limit,
            $offset
        );

        $pending_r = q("SELECT COUNT(*) AS pdg FROM register WHERE reg_vital = 1 AND reg_expires > '%s'",
            dbesc(date('Y-m-d H:i:s'))
        );

        Response::paginate(
            $users ?: [],
            $offset,
            $limit,
            $total,
        );
    }

    private function postAccounts(): void
    {
        require_once('include/account.php');

        $data   = Auth::$parsedBody;
        $action = $data['action'] ?? '';
        $uid    = intval($data['account_id'] ?? 0);

        if (!$uid)
            Response::error(400, 'account_id required');

        switch ($action) {
            case 'block':
                q("UPDATE account SET account_flags = (account_flags | %d) WHERE account_id = %d",
                    intval(ACCOUNT_BLOCKED), $uid);
                break;
            case 'unblock':
                q("UPDATE account SET account_flags = (account_flags & ~%d) WHERE account_id = %d",
                    intval(ACCOUNT_BLOCKED), $uid);
                break;
            case 'delete':
                account_remove($uid, true, false);
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

        $total_r = q("SELECT COUNT(*) AS total FROM channel WHERE channel_removed = 0 AND channel_system = 0");
        $total   = intval($total_r[0]['total'] ?? 0);

        $channels = q("SELECT channel_id, channel_name, channel_address, channel_created, channel_lastpost
            FROM channel
            WHERE channel_removed = 0 AND channel_system = 0
            ORDER BY channel_id DESC
            LIMIT %d OFFSET %d",
            $limit, $offset
        );

        Response::paginate($channels ?: [], $offset, $limit, $total);
    }

    // ── Security ──────────────────────────────────────────────────────────────

    private function getSecurity(): void
    {
        Response::send([
            'block_public'              => (bool) Config::Get('system', 'block_public', false),
            'cloud_disable_siteroot'    => (bool) Config::Get('system', 'cloud_disable_siteroot', false),
            'cloud_report_disksize'     => (bool) intval(Config::Get('system', 'cloud_report_disksize', 0)),
            'allowed_email'             => (string) Config::Get('system', 'allowed_email', ''),
            'not_allowed_email'         => (string) Config::Get('system', 'not_allowed_email', ''),
            'whitelisted_sites'         => (string) Config::Get('system', 'whitelisted_sites', ''),
            'blacklisted_sites'         => (string) Config::Get('system', 'blacklisted_sites', ''),
            'whitelisted_channels'      => (string) Config::Get('system', 'whitelisted_channels', ''),
            'blacklisted_channels'      => (string) Config::Get('system', 'blacklisted_channels', ''),
            'embed_allow'               => (string) Config::Get('system', 'embed_allow', ''),
            'embed_deny'                => (string) Config::Get('system', 'embed_deny', ''),
            'embed_sslonly'             => (bool) intval(Config::Get('system', 'embed_sslonly', 0)),
            'transport_security_header' => (bool) intval(Config::Get('system', 'transport_security_header', 0)),
            'content_security_policy'   => (bool) intval(Config::Get('system', 'content_security_policy', 0)),
            'trusted_directory_servers' => (string) Config::Get('system', 'trusted_directory_servers', ''),
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

    // ── Themes ────────────────────────────────────────────────────────────────

    private function getThemes(): void
    {
        $current = Config::Get('system', 'theme', 'redbasic');
        $themes  = [];
        $files   = glob('view/theme/*');

        if ($files) {
            foreach ($files as $file) {
                if (!is_dir($file)) continue;
                $name = basename($file);
                $info = get_theme_info($name);
                $themes[] = [
                    'name'         => $name,
                    'description'  => $info['description'] ?? '',
                    'version'      => $info['version'] ?? '',
                    'compatible'   => (bool) check_plugin_versions($info),
                    'mobile'       => file_exists($file . '/mobile'),
                    'experimental' => file_exists($file . '/experimental'),
                    'current'      => ($name === $current),
                ];
            }
        }

        Response::send(['themes' => $themes, 'current' => $current]);
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
        $jobs = q("SELECT id, priority, created, pid, argc, argv
            FROM workerqueue
            ORDER BY created DESC LIMIT 100");

        Response::send(['jobs' => $jobs ?: []]);
    }

    // ── Profile fields ────────────────────────────────────────────────────────

    private function getProfileFields(): void
    {
        $fields = q("SELECT * FROM profdef ORDER BY id");
        Response::send(['fields' => $fields ?: []]);
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
            'logfile' => $logfile ?: null,
            'entries' => $entries,
        ]);
    }
}
