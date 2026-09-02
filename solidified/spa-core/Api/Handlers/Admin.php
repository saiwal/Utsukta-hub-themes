<?php
namespace Utsukta\SpaCore\Api\Handlers;

use Utsukta\SpaCore\Api\Auth;
use Utsukta\SpaCore\Api\Response;
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

    // Some config keys (blacklisted/whitelisted sites/channels, embed_allow/deny,
    // trusted_directory_servers) are stored by core as arrays (one entry per line);
    // present them as newline-joined text like redbasic does.
    private static function cfgList(string $k): string
    {
        $v = Config::Get('system', $k);
        if (is_array($v)) return implode("\n", $v);
        if ($v === false || $v === null) return '';
        return (string) $v;
    }

    private function requireAdmin(): void
    {
        if (!local_channel() || !is_site_admin()) {
            Response::error(403, 'Permission denied');
        }
    }

    private const SERVICE_CLASS_PROPS = [
        'photo_upload_limit', 'attach_upload_limit', 'total_items', 'total_pages',
        'total_identities', 'total_channels', 'total_feeds',
        'minimum_feedcheck_minutes', 'chatrooms', 'chatters_inroom', 'access_tokens',
        'price',
    ];

    // Core has no function that lists defined service_class names — load the
    // whole config family and read its keys (same approach util/service_class
    // itself uses), skipping the 'config_loaded' load-marker and the
    // 'upgrade_link' special key (a sitewide message string, not a class).
    private function listServiceClassNames(): array
    {
        Config::Load('service_class');
        $names = array_keys(App::$config['service_class'] ?? []);
        return array_values(array_diff($names, ['config_loaded', 'upgrade_link']));
    }

    // Whitelist + coerce incoming property values; unknown keys are dropped,
    // missing/blank keys are omitted (omission = "unlimited", matching how
    // service_class_fetch() in core treats an absent property).
    private function sanitizeServiceClassProps(array $raw): array
    {
        $out = [];
        foreach (self::SERVICE_CLASS_PROPS as $k) {
            if (!isset($raw[$k]) || $raw[$k] === '' || $raw[$k] === null) continue;
            if (!is_numeric($raw[$k]) || floatval($raw[$k]) < 0)
                Response::error(400, "Property '{$k}' must be a non-negative number");
            // price is a monthly cost (e.g. 9.99); every other prop is an integer quota
            $out[$k] = $k === 'price' ? round(floatval($raw[$k]), 2) : intval($raw[$k]);
        }
        return $out;
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
            case 'addons':
                if (($slug = $this->argv(3))) {
                    $this->getAddonSettings($slug);
                } else {
                    $this->getAddons();
                }
                break;
            case 'themes':
                if (($theme = $this->argv(3))) {
                    $this->getThemeSettings($theme);
                } else {
                    $this->getThemes();
                }
                break;
            case 'inspect-queue':  $this->getQueue();         break;
            case 'queueworker':    $this->getQueueworker();    break;
            case 'profile-fields': $this->getProfileFields(); break;
            case 'service-classes': $this->getServiceClasses(); break;
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
            case 'service-classes': $this->postServiceClasses(); break;
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

            case 'set_service_class':
                $uid = intval($data['account_id'] ?? 0);
                if (!$uid)
                    Response::error(400, 'account_id required');
                $class = trim($data['service_class'] ?? ''); // '' = unrestricted/no class
                if ($class !== '' && Config::Get('service_class', $class) === false)
                    Response::error(400, "Unknown service class: {$class}");
                q("UPDATE account SET account_service_class = '%s' WHERE account_id = %d",
                    dbesc($class), $uid);
                break;

            case 'set_password':
                $uid = intval($data['account_id'] ?? 0);
                if (!$uid)
                    Response::error(400, 'account_id required');
                $account = q("SELECT account_id, account_email FROM account WHERE account_id = %d", $uid);
                if (!$account)
                    Response::error(404, 'Account not found');

                $password = (string) ($data['new_password'] ?? '');
                $pwCheck = check_account_password($password);
                if (!empty($pwCheck['error']))
                    Response::error(400, $pwCheck['message'] ?? 'Password does not meet requirements');

                $salt = random_string(32);
                $encoded = hash('whirlpool', $salt . $password);
                q("UPDATE account SET account_salt = '%s', account_password = '%s', account_reset = '' WHERE account_id = %d",
                    dbesc($salt), dbesc($encoded), $uid);

                $siteName = self::cfgStr('sitename');
                z_mail([
                    'toEmail' => $account[0]['account_email'],
                    'messageSubject' => email_header_encode("Your password has been reset at {$siteName}", 'UTF-8'),
                    'textVersion' => "An administrator at {$siteName} has just reset your account password.\n\n"
                        . "If you did not expect this change, please contact the site administrator immediately.\n",
                ]);
                break;

            case 'set_expires':
                $uid = intval($data['account_id'] ?? 0);
                if (!$uid)
                    Response::error(400, 'account_id required');
                $raw = trim($data['expires'] ?? ''); // 'YYYY-MM-DD' or '' to clear
                if ($raw === '') {
                    $expires = '0001-01-01 00:00:00';
                } else {
                    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw))
                        Response::error(400, 'Invalid date format, expected YYYY-MM-DD');
                    $ts = strtotime($raw . ' 00:00:00 UTC');
                    if ($ts === false)
                        Response::error(400, 'Invalid date');
                    $expires = gmdate('Y-m-d H:i:s', $ts);
                }
                q("UPDATE account SET account_expires = '%s' WHERE account_id = %d",
                    dbesc($expires), $uid);
                break;

            default:
                Response::error(400, "Unknown action: {$action}");
        }

        Response::send(['status' => 'ok']);
    }

    // ── Service classes ───────────────────────────────────────────────────────

    private function getServiceClasses(): void
    {
        $default = self::cfgStr('default_service_class');
        $names   = $this->listServiceClassNames();

        $classes = [];
        foreach ($names as $name) {
            $props   = Config::Get('service_class', $name);
            if (!is_array($props)) $props = [];
            $count_r = q("SELECT COUNT(*) AS c FROM account WHERE account_service_class = '%s'", dbesc($name));
            $classes[] = [
                'name'          => $name,
                'properties'    => $props,
                'account_count' => intval($count_r[0]['c'] ?? 0),
                'is_default'    => ($name === $default),
            ];
        }

        $none_r = q("SELECT COUNT(*) AS c FROM account WHERE account_service_class = ''");

        Response::send([
            'classes'               => $classes,
            'default_service_class' => $default,
            'unrestricted_count'    => intval($none_r[0]['c'] ?? 0),
        ]);
    }

    private function postServiceClasses(): void
    {
        $data   = Auth::$parsedBody;
        $action = $data['action'] ?? '';

        switch ($action) {
            case 'create':
                $name = trim($data['name'] ?? '');
                if (!preg_match('/^[a-zA-Z0-9_-]{1,32}$/', $name))
                    Response::error(400, 'Class name must be 1-32 characters: letters, digits, underscore, or hyphen');
                if (in_array($name, ['config_loaded', 'upgrade_link'], true))
                    Response::error(400, 'That name is reserved');
                if (Config::Get('service_class', $name) !== false)
                    Response::error(409, "A service class named '{$name}' already exists");

                $props = $this->sanitizeServiceClassProps($data['properties'] ?? []);
                Config::Set('service_class', $name, $props);
                Response::send(['name' => $name, 'properties' => $props, 'account_count' => 0, 'is_default' => false]);
                break;

            case 'update':
                $name = trim($data['name'] ?? '');
                if (!$name || Config::Get('service_class', $name) === false)
                    Response::error(404, 'Service class not found');
                $props = $this->sanitizeServiceClassProps($data['properties'] ?? []);
                Config::Set('service_class', $name, $props);
                Response::send(['status' => 'ok']);
                break;

            case 'delete':
                $name = trim($data['name'] ?? '');
                if (!$name || Config::Get('service_class', $name) === false)
                    Response::error(404, 'Service class not found');

                $default = self::cfgStr('default_service_class');
                if ($name === $default)
                    Response::error(409, "Cannot delete '{$name}': it is the current default service class. Set a different default first.");

                $count_r = q("SELECT COUNT(*) AS c FROM account WHERE account_service_class = '%s'", dbesc($name));
                $n = intval($count_r[0]['c'] ?? 0);
                if ($n > 0)
                    Response::error(409, "Cannot delete '{$name}': {$n} account(s) are still assigned to it. Reassign them first.");

                Config::Delete('service_class', $name);
                Response::send(['status' => 'ok']);
                break;

            case 'set_default':
                $name = trim($data['name'] ?? ''); // '' clears the default (no sitewide default class)
                if ($name !== '' && Config::Get('service_class', $name) === false)
                    Response::error(404, 'Service class not found');
                Config::Set('system', 'default_service_class', notags($name));
                Response::send(['status' => 'ok', 'default_service_class' => $name]);
                break;

            default:
                Response::error(400, "Unknown action: {$action}");
        }
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
            'whitelisted_sites'         => self::cfgList('whitelisted_sites'),
            'blacklisted_sites'         => self::cfgList('blacklisted_sites'),
            'whitelisted_channels'      => self::cfgList('whitelisted_channels'),
            'blacklisted_channels'      => self::cfgList('blacklisted_channels'),
            'embed_allow'               => self::cfgList('embed_allow'),
            'embed_deny'                => self::cfgList('embed_deny'),
            'embed_sslonly'             => self::cfgBool('embed_sslonly'),
            'transport_security_header' => self::cfgBool('transport_security_header'),
            'content_security_policy'   => self::cfgBool('content_security_policy'),
            'trusted_directory_servers' => self::cfgList('trusted_directory_servers'),
        ]);
    }

    private function postSecurity(): void
    {
        $data = Auth::$parsedBody;

        $str_fields  = ['allowed_email', 'not_allowed_email'];
        $list_fields = ['whitelisted_sites', 'blacklisted_sites', 'whitelisted_channels',
                         'blacklisted_channels', 'embed_allow', 'embed_deny', 'trusted_directory_servers'];
        $bool_fields = ['block_public', 'cloud_disable_siteroot', 'cloud_report_disksize',
                         'embed_sslonly', 'transport_security_header', 'content_security_policy'];

        foreach ($str_fields as $k)
            if (isset($data[$k]))
                Config::Set('system', $k, trim((string) $data[$k]));

        // Stored as arrays (one entry per line) to match how redbasic reads/writes them.
        foreach ($list_fields as $k)
            if (isset($data[$k])) {
                $lines = array_filter(array_map('trim', explode("\n", (string) $data[$k])), fn($l) => $l !== '');
                Config::Set('system', $k, array_values($lines));
            }

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

        // The addon table records which plugins define <name>_plugin_admin(),
        // set by install_plugin() — same gate core's Admin\Addons uses.
        $with_admin = [];
        foreach (q("SELECT aname FROM addon WHERE plugin_admin = 1") ?: [] as $row) {
            $with_admin[$row['aname']] = true;
        }

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
                    'has_settings' => isset($with_admin[$name]),
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

        if ($action === 'settings') {
            $this->postAddonSettings($data);
            return;
        }

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

    /**
     * Render an addon's own admin form (<slug>_plugin_admin()).
     *
     * The hook hands back Smarty-rendered HTML built from core's field_*.tpl
     * partials — the same markup the classic /admin/addons/:slug page wraps in
     * a <form>. There is no JSON contract for these forms, so the SPA renders
     * the HTML and posts the inputs back by name.
     */
    private function getAddonSettings(string $slug): void
    {
        Response::send($this->renderAddonSettings($slug));
    }

    private function renderAddonSettings(string $slug): array
    {
        $slug = basename($slug);

        if (!is_file("addon/$slug/$slug.php")) {
            Response::error(404, 'Unknown addon');
        }
        if (!in_array($slug, App::$plugins, true)) {
            Response::error(400, 'Addon is not enabled');
        }

        @require_once("addon/$slug/$slug.php");

        $func = $slug . '_plugin_admin';
        if (!function_exists($func)) {
            Response::error(404, 'Addon has no settings form');
        }

        $form = '';
        $func($form);

        return ['slug' => $slug, 'html' => $form];
    }

    /**
     * Save an addon's settings by calling <slug>_plugin_admin_post(), which
     * reads $_POST directly. Our request body is JSON, so the posted fields are
     * copied into $_POST/$_REQUEST first, along with the admin_addons security
     * token the stricter addons check for.
     */
    private function postAddonSettings(array $data): void
    {
        $slug = basename($data['name'] ?? '');

        if (!$slug || !is_file("addon/$slug/$slug.php")) {
            Response::error(400, 'Invalid addon');
        }
        if (!in_array($slug, App::$plugins, true)) {
            Response::error(400, 'Addon is not enabled');
        }

        @require_once("addon/$slug/$slug.php");

        $func = $slug . '_plugin_admin_post';
        if (!function_exists($func)) {
            Response::error(404, 'Addon has no settings form');
        }

        $fields = is_array($data['fields'] ?? null) ? $data['fields'] : [];

        // Scalars and flat arrays only — an addon form is field_input.tpl and
        // friends, and nested structures have no way to arrive here anyway.
        foreach ($fields as $k => $v) {
            if (is_array($v)) {
                $v = array_values(array_filter($v, 'is_scalar'));
            } elseif (!is_scalar($v) && $v !== null) {
                continue;
            }
            $_POST[$k] = $v;
        }

        $_POST['form_security_token'] = get_form_security_token('admin_addons');
        $_REQUEST = array_merge($_REQUEST, $_POST);

        $func();

        // Re-render so the client shows what was actually stored.
        Response::send($this->renderAddonSettings($slug));
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
                    'has_settings' => self::themeHasSettings($name),
                ];
            }
        }

        Response::send(['themes' => $themes, 'current' => $current]);
    }

    /**
     * A theme's settings form comes from theme_admin() in its php/config.php.
     * Those functions are plain globals (no theme prefix), so exactly one
     * theme's config.php may be loaded per request — never probe them in a
     * loop. Hence this static check rather than require_once + function_exists.
     */
    private static function themeHasSettings(string $name): bool
    {
        $cfg = "view/theme/$name/php/config.php";
        return is_file($cfg)
            && (bool) preg_match('/function\s+theme_admin\s*\(/', (string) file_get_contents($cfg));
    }

    private function getThemeSettings(string $theme): void
    {
        Response::send($this->renderThemeSettings($theme));
    }

    private function renderThemeSettings(string $theme): array
    {
        $theme = basename($theme);
        $cfg   = "view/theme/$theme/php/config.php";

        if (!is_dir("view/theme/$theme") || !is_file($cfg)) {
            Response::error(404, 'Unknown theme');
        }

        require_once($cfg);

        if (!function_exists('theme_admin')) {
            Response::error(404, 'Theme has no settings form');
        }

        // theme_admin() *returns* its markup (an addon's _plugin_admin() fills a
        // by-reference arg instead), and adminlte declares it as theme_admin(&$a),
        // so the argument has to be a variable.
        $a = null;
        $form = (string) theme_admin($a);

        return ['slug' => $theme, 'html' => $form];
    }

    private function postThemeSettings(array $data): void
    {
        $theme = basename($data['theme'] ?? $data['name'] ?? '');
        $cfg   = "view/theme/$theme/php/config.php";

        if (!$theme || !is_dir("view/theme/$theme") || !is_file($cfg)) {
            Response::error(400, 'Invalid theme');
        }

        require_once($cfg);

        if (!function_exists('theme_admin_post')) {
            Response::error(404, 'Theme has no settings form');
        }

        $fields = is_array($data['fields'] ?? null) ? $data['fields'] : [];
        foreach ($fields as $k => $v) {
            if (is_array($v)) {
                $v = array_values(array_filter($v, 'is_scalar'));
            } elseif (!is_scalar($v) && $v !== null) {
                continue;
            }
            $_POST[$k] = $v;
        }

        // The rendered form carries its own admin_themes token, but a panel left
        // open past the 3h lifetime would send a stale one — and the themes'
        // check_form_security_token_redirectOnErr() would goaway() mid-JSON.
        $_POST['form_security_token'] = get_form_security_token('admin_themes');
        $_REQUEST = array_merge($_REQUEST, $_POST);

        $a = null;
        theme_admin_post($a);

        Response::send($this->renderThemeSettings($theme));
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

        if ($action === 'settings') {
            $this->postThemeSettings($data);
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
