<?php
namespace Utsukta\SpaCore\Api\Handlers;

use Utsukta\SpaCore\Api\Auth;
use Utsukta\SpaCore\Api\Response;
use Zotlabs\Access\PermissionLimits;
use Zotlabs\Access\PermissionRoles;
use Zotlabs\Access\Permissions;
use Zotlabs\Lib\Apps;
use Zotlabs\Lib\Config;
use Zotlabs\Lib\Libsync;
use Zotlabs\Daemon\Master;
use App;

class Settings
{
    public function get(): void
    {
        $this->requireManageAccess();

        if (!local_channel()) {
            Response::error(['error' => 'Permission denied']);
        }

        $datatype = \App::$argv[2] ?? 'display';
        switch ($datatype) {
            case 'display':
                $this->getDisplaySettings();
                break;
            case 'profile':
                $this->getProfileSettings();
                break;
            case 'features':
                $this->getFeaturesSettings();
                break;
            case 'account':
                $this->getAccountSettings();
                break;
            case 'privacy':
                $this->getPrivacySettings();
                break;
            case 'channel':
                $this->getChannelSettings();
                break;
            case 'apps':
                $this->getAppsSettings();
                break;
            case 'notifications':
                $this->getNotificationSettings();
                break;
            case 'integrations':
                $this->getIntegrationsSettings();
                break;
            case 'danger':
                $this->getDangerSettings();
                break;
            case 'locations':
                $this->getLocationsSettings();
                break;
            default:
                $this->getDisplaySettings();
                break;
        }
    }

    // ── Auth ──────────────────────────────────────────────────────────────────

    private function requireManageAccess(): void
    {
        if (!get_account_id() || !empty($_SESSION['delegate'])) {
            Response::error(403, 'Permission denied');
        }
    }

    private function getDisplaySettings(): void
    {
        $uid = local_channel();

        $default_theme = \Zotlabs\Lib\Config::Get('system', 'theme') ?: 'redbasic';
        $themespec = explode(':', \App::$channel['channel_theme']);
        $theme = $themespec[0] ?: $default_theme;

        $allowed_themes_raw = explode(',', \Zotlabs\Lib\Config::Get('system', 'allowed_themes'));
        $allowed_themes = [];
        foreach ($allowed_themes_raw as $x)
            if (strlen(trim($x)) && is_dir("view/theme/$x"))
                $allowed_themes[] = trim($x);

        $valid_font_sizes    = ['small', 'medium', 'large', 'xl'];
        $valid_font_families = ['system', 'serif', 'monospace', 'nunito', 'saira', 'share-tech', 'playfair', 'libre-baskerville', 'comfortaa', 'space-mono', 'iosevka', 'righteous', 'playwrite-england', 'comic', 'opendyslexic', 'inter', 'atkinson-hyperlegible', 'literata', 'jetbrains-mono'];

        $font_size   = get_pconfig($uid, 'spa', 'font_size', 'medium');
        $font_family = get_pconfig($uid, 'spa', 'font_family', 'system');

        if (!in_array($font_size, $valid_font_sizes, true))     $font_size   = 'medium';
        if (!in_array($font_family, $valid_font_families, true)) $font_family = 'system';

        $valid_bg_fits = ['tile', 'cover'];
        $bg_url = get_pconfig($uid, 'spa', 'bg_url', '');
        $bg_fit = get_pconfig($uid, 'spa', 'bg_fit', 'cover');
        if (!in_array($bg_fit, $valid_bg_fits, true)) $bg_fit = 'cover';

        $valid_color_schemes = [
            'light', 'pastel-soft', 'warm-paper', 'mint', 'sakura', 'latte-cream',
            'dark', 'nord', 'dracula', 'monokai', 'one-dark', 'cyberpunk',
            'rose-pine', 'gruvbox-dark', 'gruvbox-light', 'catppuccin-latte',
            'catppuccin-mocha', 'solarized-light', 'solarized-dark', 'tokyo-night', 'matrix',
            'high-contrast', 'high-contrast-light', 'custom',
        ];
        $color_scheme = get_pconfig($uid, 'spa', 'color_scheme', 'light');
        if (!in_array($color_scheme, $valid_color_schemes, true)) $color_scheme = 'light';

        $custom_theme_colors = null;
        if ($color_scheme === 'custom') {
            $stored = get_pconfig($uid, 'spa', 'custom_theme_colors', '');
            if ($stored) $custom_theme_colors = $stored;
        }

        $valid_scroll_styles = ['endless', 'load_more'];
        $scroll_style = get_pconfig($uid, 'spa', 'scroll_style', 'endless');
        if (!in_array($scroll_style, $valid_scroll_styles, true)) $scroll_style = 'endless';

        // Collapsed post-body height in px; 0 = never collapse.
        $post_height = intval(get_pconfig($uid, 'spa', 'post_height', 310));
        if ($post_height > 0) $post_height = min(max($post_height, 100), 5000);
        else $post_height = 0;

        $valid_corner_radii = ['none', 'sm', 'default', 'lg', 'xl'];
        $corner_radius = get_pconfig($uid, 'spa', 'corner_radius', 'default');
        if (!in_array($corner_radius, $valid_corner_radii, true)) $corner_radius = 'default';

        $valid_comment_orders = ['oldest_first', 'newest_first'];
        $comment_order = get_pconfig($uid, 'spa', 'comment_order', 'oldest_first');
        if (!in_array($comment_order, $valid_comment_orders, true)) $comment_order = 'oldest_first';

        $valid_thread_modes = ['threaded', 'flat'];
        $thread_mode = get_pconfig($uid, 'spa', 'thread_mode', 'threaded');
        if (!in_array($thread_mode, $valid_thread_modes, true)) $thread_mode = 'threaded';

        Response::send([
            'thread_allow' => intval(get_pconfig($uid, 'system', 'thread_allow', 1)),
            'update_interval' => intval(get_pconfig($uid, 'system', 'update_interval', 80000)) / 1000,
            'itemspage' => intval(get_pconfig($uid, 'system', 'itemspage', 10)),
            'title_tosource' => intval(get_pconfig($uid, 'system', 'title_tosource', 0)),
            'start_menu' => intval(get_pconfig($uid, 'system', 'start_menu', 0)),
            'user_scalable' => intval(get_pconfig($uid, 'system', 'user_scalable', 0)),
            'theme' => $theme,
            'themes' => array_values($allowed_themes),
            'font_size' => $font_size,
            'font_family' => $font_family,
            'bg_url' => (string) $bg_url,
            'bg_fit' => $bg_fit,
            'color_scheme' => $color_scheme,
            'custom_theme_colors' => $custom_theme_colors,
            'scroll_style' => $scroll_style,
            'post_height' => $post_height,
            'corner_radius' => $corner_radius,
            'comment_order' => $comment_order,
            'thread_mode' => $thread_mode,
            'show_emoji_images' => 1 - intval(get_pconfig($uid, 'system', 'no_smilies', 0)),
        ]);
    }

    private function getProfileSettings(): void
    {
        $uid = local_channel();
        $profile = \Zotlabs\Lib\Profile::load($uid, 'default');

        if (!$profile)
            Response::error(404, 'Profile not found');

        Response::send([
            'name' => $profile['fullname'] ?? '',
            'pdesc' => $profile['pdesc'] ?? '',
            'homepage' => $profile['homepage'] ?? '',
            'hometown' => $profile['hometown'] ?? '',
            'gender' => $profile['gender'] ?? '',
            'birthday' => $profile['dob'] ?? '',
            'about' => Response::decodeEntities($profile['about'] ?? ''),
            'keywords' => $profile['keywords'] ?? '',
            'hide_friends' => intval($profile['hide_friends'] ?? 0),
            'publish' => intval($profile['publish'] ?? 0),
        ]);
    }

    private function getPrivacySettings(): void
    {
        $uid = local_channel();
        load_pconfig($uid);

        Response::send([
            'autoperms' => intval(get_pconfig($uid, 'system', 'autoperms')),
            'index_opt_out' => intval(get_pconfig($uid, 'system', 'index_opt_out')),
            'permit_all_mentions' => intval(get_pconfig($uid, 'system', 'permit_all_mentions')),
            'moderate_unsolicited_comments' => intval(get_pconfig($uid, 'system', 'moderate_unsolicited_comments')),
            'ocap_enabled' => intval(get_pconfig($uid, 'system', 'ocap_enabled')),
            'nsfw_installed' => \Zotlabs\Lib\Apps::system_app_installed($uid, 'NSFW'),
            'nsfw_words' => (string) get_pconfig($uid, 'nsfw', 'words', 'nsfw,contentwarning'),
            'local_only_posts' => intval(get_pconfig($uid, 'spa', 'local_only_posts')),
        ]);
    }

    // Per-permission access-limit rows for the custom role, shaped like core's
    // Settings/Privacy advanced section: [key, label, value, help, options]
    private function buildPermissArr(int $uid): array
    {
        $permiss = [];

        $perm_opts = [
            [t('Only me'), 0],
            [t('Only those you specifically allow'), PERMS_SPECIFIC],
            [t('Approved connections'), PERMS_CONTACTS],
            [t('Any connections'), PERMS_PENDING],
            [t('Anybody on this website'), PERMS_SITE],
            [t('Anybody in this network'), PERMS_NETWORK],
            [t('Anybody authenticated'), PERMS_AUTHED],
            [t('Anybody on the internet'), PERMS_PUBLIC]
        ];

        $help = [
            'view_stream',
            'view_wiki',
            'view_pages',
            'view_storage'
        ];

        $help_txt = t('Advise: set to "Anybody on the internet" and use privacy groups to restrict access');
        $limits = PermissionLimits::Get($uid);
        $anon_comments = Config::Get('system', 'anonymous_comments', true);

        foreach (Permissions::Perms() as $k => $perm) {
            $options = [];
            $can_be_public = (strstr($k, 'view') || ($k === 'post_comments' && $anon_comments));

            foreach ($perm_opts as $opt) {
                if ($opt[1] == PERMS_PUBLIC && (!$can_be_public))
                    continue;

                $options[$opt[1]] = $opt[0];
            }

            $permiss[] = [
                $k,
                $perm,
                intval($limits[$k] ?? 0),
                ((in_array($k, $help)) ? $help_txt : ''),
                $options
            ];
        }

        return $permiss;
    }

    private function getChannelSettings(): void
    {
        $uid = local_channel();
        load_pconfig($uid);

        $channel = App::get_channel();
        $role_options = PermissionRoles::channel_roles();

        $permissions_role = get_pconfig($uid, 'system', 'permissions_role');
        // Invalid/unset role ships as '' so the frontend forces a selection (as core does)
        if (!in_array($permissions_role, array_keys($role_options), true))
            $permissions_role = '';

        Response::send([
            'permissions_role' => $permissions_role,
            'role_options' => $role_options,
            'timezone' => $channel['channel_timezone'],
            'timezones' => get_timezones(),
            'defloc' => $channel['channel_location'],
            'allow_location' => (get_pconfig($uid, 'system', 'use_browser_location') ? 1 : 0),
            'adult' => ((intval($channel['channel_pageflags']) & PAGE_ADULT) ? 1 : 0),
            'maxreq' => intval($channel['channel_max_friend_req']),
            'photo_path' => get_pconfig($uid, 'system', 'photo_path', ''),
            'attach_path' => get_pconfig($uid, 'system', 'attach_path', ''),
            'expire' => intval($channel['channel_expire_days']),
            'expire_sys' => intval(Config::Get('system', 'default_expire_days')),
            'message_filter_incl' => get_pconfig($uid, 'system', 'message_filter_incl', ''),
            'message_filter_excl' => get_pconfig($uid, 'system', 'message_filter_excl', ''),
            'permiss_arr' => $this->buildPermissArr($uid),
            'group_actor' => intval(get_pconfig($uid, 'system', 'group_actor')),
        ]);
    }

    // Service-class limit keys shown to the account holder, alongside how to
    // compute current usage for each (mirrors the same usage queries core
    // itself uses when enforcing these limits — see e.g. New_channel.php,
    // Zotlabs/Lib/Connect.php, Zotlabs/Module/Item.php::item_check_service_class(),
    // Zotlabs/Module/Photos.php, Zotlabs/Lib/Chatroom.php, Zotlabs/Module/Tokens.php).
    // 'usage' is null for keys that aren't a running total (a floor value or a
    // per-room concurrent count) — those just show the configured limit.
    private const QUOTA_KEYS = [
        'total_identities', 'total_channels', 'total_feeds', 'total_items', 'total_pages',
        'photo_upload_limit', 'attach_upload_limit', 'chatrooms', 'chatters_inroom',
        'access_tokens', 'minimum_feedcheck_minutes',
    ];

    private function getAccountSettings(): void
    {
        require_once('include/text.php');

        $email   = \App::$account['account_email'];
        $channel = \App::get_channel();
        $channel_id = intval($channel['channel_id'] ?? 0);
        $account_id = intval(\App::$account['account_id'] ?? 0);

        $quotas = ($channel_id && $account_id) ? $this->buildQuotas($channel_id, $account_id) : [];

        Response::send([
            '$email' => $email,
            'quotas' => $quotas,
        ]);
    }

    private function buildQuotas(int $channel_id, int $account_id): array
    {
        $quotas = [];
        foreach (self::QUOTA_KEYS as $key) {
            $limit = service_class_fetch($channel_id, $key);
            if ($limit === false) continue;
            $limit = intval(engr_units_to_bytes($limit));

            $quotas[] = [
                'key'   => $key,
                'limit' => $limit,
                'usage' => $this->quotaUsage($key, $channel_id, $account_id),
            ];
        }
        return $quotas;
    }

    private function quotaUsage(string $key, int $channel_id, int $account_id): ?int
    {
        switch ($key) {
            case 'total_identities':
                $r = q("SELECT COUNT(channel_id) AS total FROM channel WHERE channel_account_id = %d AND channel_removed = 0",
                    $account_id);
                return intval($r[0]['total'] ?? 0);

            case 'total_channels':
                $r = q("SELECT COUNT(*) AS total FROM abook WHERE abook_channel = %d AND abook_self = 0",
                    $channel_id);
                return intval($r[0]['total'] ?? 0);

            case 'total_feeds':
                $r = q("SELECT COUNT(*) AS total FROM abook WHERE abook_account = %d AND abook_feed = 1",
                    $account_id);
                return intval($r[0]['total'] ?? 0);

            case 'total_items':
                require_once('include/items.php');
                $r = q("SELECT COUNT(id) AS total FROM item WHERE parent = id AND item_wall = 1 AND uid = %d " . item_normal(),
                    $channel_id);
                return intval($r[0]['total'] ?? 0);

            case 'total_pages':
                $r = q("SELECT COUNT(i.id) AS total FROM item i
                        RIGHT JOIN channel c ON (i.author_xchan = c.channel_hash AND i.uid = c.channel_id)
                        WHERE i.parent = i.id AND i.item_type = %d AND i.item_deleted = 0 AND i.uid = %d",
                    intval(ITEM_TYPE_WEBPAGE), $channel_id);
                return intval($r[0]['total'] ?? 0);

            case 'photo_upload_limit':
                $r = q("SELECT SUM(filesize) AS total FROM photo WHERE aid = %d AND imgscale = 0",
                    $account_id);
                return intval($r[0]['total'] ?? 0);

            case 'attach_upload_limit':
                $r = q("SELECT SUM(filesize) AS total FROM attach WHERE aid = %d",
                    $account_id);
                return intval($r[0]['total'] ?? 0);

            case 'chatrooms':
                $r = q("SELECT COUNT(cr_id) AS total FROM chatroom WHERE cr_aid = %d",
                    $account_id);
                return intval($r[0]['total'] ?? 0);

            case 'access_tokens':
                $r = q("SELECT COUNT(atoken_id) AS total FROM atoken WHERE atoken_uid = %d",
                    $channel_id);
                return intval($r[0]['total'] ?? 0);

            default:
                // 'chatters_inroom' (per-room concurrent count) and
                // 'minimum_feedcheck_minutes' (a floor, not a total) — limit only.
                return null;
        }
    }

    private function getAppsSettings(): void
    {
        require_once ('include/security.php');
        require_once ('include/conversation.php');

        $observer = App::get_observer();
        $ob_hash = $observer ? $observer['xchan_hash'] : '';
        $is_local = (bool) local_channel();
        $uid = local_channel() ?: 0;
        $channel = $is_local ? App::get_channel() : null;

        // A remote viewer has an observer (OWA cookie) but no local channel.
        $is_remote = (!$is_local && $ob_hash !== '');
        $is_anon = (!$is_local && $ob_hash === '');

        // ── Viewer identity ───────────────────────────────────────────────────

        $viewer = [
            'is_local' => $is_local,
            'is_remote' => $is_remote,
            'is_admin' => $is_local && is_site_admin(),
            'nick' => $channel['channel_address'] ?? '',
            'name' => Response::decodeEntities($observer['xchan_name'] ?? ''),
            'avatar' => $observer['xchan_photo_m'] ?? '',
            'url' => $observer['xchan_url'] ?? '',
            'uid' => $uid,
            'baseurl' => z_root(),
        ];

        // ── Action links ──────────────────────────────────────────────────────
        // PHP is the authority here — frontend renders whatever keys arrive.

        $actions = [];

        if ($is_local) {
            $nick = $channel['channel_address'] ?? '';
            $actions['profile'] = z_root() . '/profile/' . $nick;
            $actions['profiles'] = z_root() . '/profiles';
            $actions['settings'] = z_root() . '/settings';
            $actions['manage'] = z_root() . '/manage';
            $actions['logout'] = z_root() . '/logout';
        } elseif ($is_remote) {
            $my_url = get_my_url();
            if (!$my_url) {
                $observer = App::get_observer();
                $my_url = (($observer) ? $observer['xchan_url'] : '');
            }
            $homelink_arr = parse_url($my_url);
            $scheme = $homelink_arr['scheme'] ?? '';
            $host = $homelink_arr['host'] ?? '';
            $homelink = $scheme . '://' . $host;
            $actions['navhome'] = $homelink;
            // Remote OWA user: only logout makes sense
            $actions['logout'] = z_root() . '/logout';
        } else {
            // Anonymous
            $actions['login'] = z_root() . '/login';
            $actions['remote_login'] = z_root() . '/rmagic';
            $reg = \Zotlabs\Lib\Config::Get('system', 'register_policy');
            if ($reg == REGISTER_OPEN || $reg == REGISTER_APPROVE) {
                $actions['register'] = z_root() . '/register';
            }
        }

        // ── Pinned apps ───────────────────────────────────────────────────────
        // Local owners: their personalised pinned list.
        // Everyone else: a curated public list so the sidebar is never empty.

        $pinned = [];

        if ($is_local) {
            // Keep system apps current (mirrors core nav() logic)
            if (get_pconfig($uid, 'system', 'import_system_apps') !==
                    datetime_convert('UTC', 'UTC', 'now', 'Y-m-d')) {
                \Zotlabs\Lib\Apps::import_system_apps();
                set_pconfig($uid, 'system', 'import_system_apps',
                    datetime_convert('UTC', 'UTC', 'now', 'Y-m-d'));
            }
            if (get_pconfig($uid, 'system', 'force_import_system_apps') !== STD_VERSION) {
                \Zotlabs\Lib\Apps::import_system_apps();
                set_pconfig($uid, 'system', 'force_import_system_apps', STD_VERSION);
            }

            $list = \Zotlabs\Lib\Apps::app_list($uid, false, ['nav_pinned_app']);
            foreach (($list ?: []) as $li)
                $pinned[] = \Zotlabs\Lib\Apps::app_encode($li);

            \Zotlabs\Lib\Apps::translate_system_apps($pinned);
            usort($pinned, 'Zotlabs\Lib\Apps::app_name_compare');
            $pinned = \Zotlabs\Lib\Apps::app_order($uid, $pinned, 'nav_pinned_app');
        } else {
            // Anonymous / remote: build a minimal public nav from system apps.
            // Pull the full system list and keep only the apps we want to expose.
            $system = \Zotlabs\Lib\Apps::get_system_apps(true);
            \Zotlabs\Lib\Apps::translate_system_apps($system);

            $public_names = ['Directory', 'Help'];
            if (can_view_public_stream())
                $public_names[] = 'Network';

            foreach ($system as $app) {
                if (in_array($app['name'] ?? '', $public_names, true))
                    $pinned[] = $app;
            }

            // Preserve the preferred order defined in $public_names
            usort($pinned, function ($a, $b) use ($public_names) {
                $ia = array_search($a['name'] ?? '', $public_names);
                $ib = array_search($b['name'] ?? '', $public_names);
                return $ia - $ib;
            });
        }

        // ── Featured apps ─────────────────────────────────────────────────────
        // Used by an app drawer (if you build one). Always the full system list,
        // stripped of local_channel-only entries for non-local viewers.

        $featured = [];

        if ($is_local) {
            $list = \Zotlabs\Lib\Apps::app_list($uid, false, ['nav_featured_app']);
            foreach (($list ?: []) as $li)
                $featured[] = \Zotlabs\Lib\Apps::app_encode($li);
            \Zotlabs\Lib\Apps::translate_system_apps($featured);
        } else {
            $featured = \Zotlabs\Lib\Apps::get_system_apps(true);
            \Zotlabs\Lib\Apps::translate_system_apps($featured);
            $featured = array_values(array_filter($featured, fn($a) =>
                empty($a['requires']) ||
                strpos($a['requires'], 'local_channel') === false));
        }

        usort($featured, 'Zotlabs\Lib\Apps::app_name_compare');
        $featured = \Zotlabs\Lib\Apps::app_order($uid, $featured, 'nav_featured_app');

        // Replace lines 95-105 with:
        $app_shape = function (array $app) use ($baseurl): array {
            $url = $app['app_url'] ?? ($app['url'] ?? '');
            // Substitute placeholder and take first if comma-separated
            $url = str_replace('$baseurl', $baseurl, $url);
            $url = trim(explode(',', $url)[0]);

            return [
                'name' => $app['name'] ?? '',
                'label' => $app['label'] ?? ($app['name'] ?? ''),
                'url' => $url,
                'photo' => $app['photo'] ?? '',
                'requires' => $app['requires'] ?? '',
            ];
        };

        $pinned = array_map($app_shape, $pinned);
        $featured = array_map($app_shape, $featured);
        // ── Channel tabs ──────────────────────────────────────────────────────
        // Only built when the SPA passes ?channel_nick=<nick>.
        // Permission-gated per observer — this is the only place subject
        // context is needed, and the SPA owns that from the URL.

        $channel_tabs = [];
        $subject_nick = trim($_GET['channel_nick'] ?? '');

        if ($subject_nick !== '') {
            $subject = channelx_by_nick($subject_nick);

            if ($subject && !($subject['channel_removed'] ?? false)) {
                $puid = intval($subject['channel_id']);
                $p = get_all_perms($puid, $ob_hash);

                // Posts tab is always present if we can resolve the channel
                $channel_tabs[] = [
                    'id' => 'stream',
                    'label' => t('Channel'),
                    'url' => z_root() . '/channel/' . $subject_nick,
                    'icon' => 'home',
                ];

                /* if ($p['view_profile']) */
                /* $channel_tabs[] = [ */
                /* 'id' => 'profile', */
                /* 'label' => t('About'), */
                /* 'url' => z_root() . '/profile/' . $subject_nick, */
                /* 'icon' => 'person', */
                /* ]; */

                if (\Zotlabs\Lib\Apps::system_app_installed($puid, 'Articles')) {
                    $channel_tabs[] = [
                        'id' => 'articles-tab',
                        'label' => t('Articles'),
                        'url' => z_root() . '/articles/' . $subject_nick,
                        'icon' => 'articles',
                    ];
                }
                if ($p['view_storage']) {
                    $channel_tabs[] = [
                        'id' => 'photos',
                        'label' => t('Photos'),
                        'url' => z_root() . '/photos/' . $subject_nick,
                        'icon' => 'image',
                    ];
                    $channel_tabs[] = [
                        'id' => 'files',
                        'label' => t('Files'),
                        'url' => z_root() . '/cloud/' . $subject_nick,
                        'icon' => 'folder',
                    ];
                }

                if ($p['view_stream'])
                    $channel_tabs[] = [
                        'id' => 'calendar',
                        'label' => t('Calendar'),
                        'url' => z_root() . '/cal/' . $subject_nick,
                        'icon' => 'calendar',
                    ];

                if (!empty($p['chat']) &&
                    \Zotlabs\Lib\Apps::system_app_installed($puid, 'Chatrooms') &&
                    \Zotlabs\Lib\Chatroom::list_count($puid))
                    $channel_tabs[] = [
                        'id' => 'chat',
                        'label' => t('Chatrooms'),
                        'url' => z_root() . '/chat/' . $subject_nick,
                        'icon' => 'chat',
                    ];
                // Webpages — check subject's app installation, use subject's nick
                if (\Zotlabs\Lib\Apps::system_app_installed($puid, 'Webpages')) {
                    $channel_tabs[] = [
                        'id' => 'webpages',
                        'label' => t('Webpages'),
                        'url' => z_root() . '/page/' . $subject_nick . '/home',
                        'icon' => 'webpages',
                    ];
                }
            }
        }

        // ── Response ──────────────────────────────────────────────────────────
        Response::send([
            'viewer' => $viewer,
            'actions' => $actions,
            'pinned' => $pinned,
            'featured' => $featured,
            'channel_tabs' => $channel_tabs,
            'has_public_stream' => (bool) can_view_public_stream(),
        ]);
    }

    private function getIntegrationsSettings(): void
    {
        $uid = local_channel();
        // translate=false — 'name' is the stable identifier round-tripped to
        // postIntegrationsSettings() and must match $installed_map's keys
        // (raw DB app_name), regardless of the channel's language setting.
        // Display translation is handled client-side (src/shared/lib/app-labels.ts)
        // via the SPA's own i18n catalog, not PHP core's gettext — the latter has
        // no coverage for languages like Hindi.
        $system = \Zotlabs\Lib\Apps::get_system_apps(false);

        // Build a map of installed apps (with terms) keyed by name
        $installed_list = \Zotlabs\Lib\Apps::app_list($uid, false) ?: [];
        $installed_map  = [];
        foreach ($installed_list as $row) {
            $enc = \Zotlabs\Lib\Apps::app_encode($row);
            $installed_map[$enc['name']] = $enc;
        }

        $apps = [];
        $seen = [];

        foreach ($system as $app) {
            $name = $app['name'] ?? '';
            if (!$name) continue;
            $seen[$name] = true;

            // .apd files carry unresolved "$baseurl"/"$nick" placeholders in
            // url/photo (see app_render() in core) — substitute them here,
            // same as core does at render time, so icon URLs aren't broken.
            \Zotlabs\Lib\Apps::app_macros($uid, $app);

            $inst       = $installed_map[$name] ?? null;
            $categories = $inst['categories'] ?? '';

            $apps[] = [
                'name'        => $name,
                'description' => self::appDesc($app),
                'photo'       => $app['photo'] ?? '',
                'requires'    => $app['requires'] ?? '',
                'installed'   => $inst !== null,
                'pinned'      => $inst !== null && str_contains($categories, 'nav_pinned_app'),
                'featured'    => $inst !== null && str_contains($categories, 'nav_featured_app'),
            ];
        }

        // Include installed apps that are not in the system list (user apps, plugin apps, etc.)
        foreach ($installed_map as $name => $inst) {
            if (isset($seen[$name])) continue;
            \Zotlabs\Lib\Apps::app_macros($uid, $inst);
            $categories = $inst['categories'] ?? '';
            $apps[] = [
                'name'        => $name,
                'description' => self::appDesc($inst),
                'photo'       => $inst['photo'] ?? '',
                'requires'    => $inst['requires'] ?? '',
                'installed'   => true,
                'pinned'      => str_contains($categories, 'nav_pinned_app'),
                'featured'    => str_contains($categories, 'nav_featured_app'),
            ];
        }

        usort($apps, fn($a, $b) => strcasecmp($a['name'], $b['name']));

        $nav_order_raw = get_pconfig($uid, 'spa', 'nav_order', '');
        $nav_order = $nav_order_raw ? (json_decode($nav_order_raw, true) ?? []) : [];

        Response::send([
            'apps'      => $apps,
            'nav_order' => array_values((array) $nav_order),
            'kanban'    => intval(get_pconfig($uid, 'spa', 'kanban')),
        ]);
    }

    // .apd files and app_encode() both spell it 'desc'; core escapes quotes into
    // entities (including the typo'd '&dquot;'), so undo that for JSON consumers.
    private static function appDesc(array $app): string
    {
        $d = $app['desc'] ?? $app['description'] ?? '';
        return str_replace(['&#39;', '&dquot;', '&quot;'], ["'", '"', '"'], $d);
    }

    private function getLocationsSettings(): void
    {
        $channel = App::get_channel();

        $rows = q(
            "select * from hubloc where hubloc_hash = '%s' and hubloc_deleted = 0 order by hubloc_primary desc, hubloc_addr asc",
            dbesc($channel['channel_hash'])
        );

        $locations = [];
        foreach (($rows ?: []) as $row) {
            $locations[] = [
                'id'      => (int) $row['hubloc_id'],
                'addr'    => $row['hubloc_addr'],
                'url'     => $row['hubloc_url'],
                'primary' => (bool) $row['hubloc_primary'],
                'isLocal' => $row['hubloc_url'] === z_root(),
            ];
        }

        Response::send(['locations' => $locations]);
    }

    // Maps the SPA's notifyN / vnotifyN field names to the bitmask constants
    // used by channel_notifyflags and pconfig system.vnotify.
    private function notifyBits(): array
    {
        return [
            'notify1' => NOTIFY_INTRO,
            'notify2' => NOTIFY_CONFIRM,
            'notify3' => NOTIFY_WALL,
            'notify4' => NOTIFY_COMMENT,
            'notify5' => NOTIFY_MAIL,
            'notify6' => NOTIFY_SUGGEST,
            'notify7' => NOTIFY_TAGSELF,
            'notify8' => NOTIFY_POKE,
            'notify9' => NOTIFY_LIKE,
        ];
    }

    private function vnotifyBits(): array
    {
        return [
            'vnotify1' => VNOTIFY_NETWORK,
            'vnotify2' => VNOTIFY_CHANNEL,
            'vnotify3' => VNOTIFY_MAIL,
            'vnotify4' => VNOTIFY_EVENT,
            'vnotify5' => VNOTIFY_EVENTTODAY,
            'vnotify6' => VNOTIFY_BIRTHDAY,
            'vnotify7' => VNOTIFY_SYSTEM,
            'vnotify8' => VNOTIFY_INFO,
            'vnotify9' => VNOTIFY_ALERT,
            'vnotify10' => VNOTIFY_INTRO,
            'vnotify11' => VNOTIFY_REGISTER,
            'vnotify12' => VNOTIFY_FILES,
            'vnotify13' => VNOTIFY_PUBS,
            'vnotify14' => VNOTIFY_LIKE,
            'vnotify15' => VNOTIFY_FORUMS,
        ];
    }

    // Public-stream notifications are only meaningful when the discover tab /
    // firehose is enabled and the Public Stream app is installed.
    private function pubstreamNotifyAvailable(int $uid): bool
    {
        $disable_discover_tab = intval(Config::Get('system', 'disable_discover_tab', 1)) == 1;
        $site_firehose = intval(Config::Get('system', 'site_firehose', 0)) == 1;
        if ($disable_discover_tab && !$site_firehose)
            return false;
        return Apps::system_app_installed($uid, 'Public Stream');
    }

    private function getNotificationSettings(): void
    {
        $uid = local_channel();
        load_pconfig($uid);

        $channel = App::get_channel();
        $notify = intval($channel['channel_notifyflags']);

        $vnotify = get_pconfig($uid, 'system', 'vnotify');
        if ($vnotify === false)
            $vnotify = (-1);
        $vnotify = intval($vnotify);

        $evdays = intval(get_pconfig($uid, 'system', 'evdays'));
        if ($evdays < 1)
            $evdays = 3;

        $data = [
            'evdays' => $evdays,
            'always_show_in_notices' => intval(get_pconfig($uid, 'system', 'always_show_in_notices', 0)),
            'update_notices_per_parent' => intval(get_pconfig($uid, 'system', 'update_notices_per_parent', 1)),
            'post_newfriend' => intval(get_pconfig($uid, 'system', 'post_newfriend', 0)),
            'post_joingroup' => intval(get_pconfig($uid, 'system', 'post_joingroup', 0)),
            'post_profilechange' => intval(get_pconfig($uid, 'system', 'post_profilechange', 0)),
            'mailhost' => get_pconfig($uid, 'system', 'email_notify_host', App::get_hostname()),
        ];

        foreach ($this->notifyBits() as $k => $bit)
            $data[$k] = (($notify & $bit) ? 1 : 0);
        foreach ($this->vnotifyBits() as $k => $bit)
            $data[$k] = (($vnotify & $bit) ? 1 : 0);

        // Omitted fields hide the corresponding toggles in the UI
        if (!is_site_admin())
            unset($data['vnotify11']);
        if (!$this->pubstreamNotifyAvailable($uid))
            unset($data['vnotify13']);

        Response::send($data);
    }

    private function postNotificationSettings(int $uid, array $data): void
    {
        $channel = App::get_channel();

        $notify = intval($channel['channel_notifyflags']);
        foreach ($this->notifyBits() as $k => $bit) {
            if (isset($data[$k]))
                $notify = (intval($data[$k]) ? ($notify | $bit) : ($notify & ~$bit));
        }

        $vnotify = get_pconfig($uid, 'system', 'vnotify');
        if ($vnotify === false)
            $vnotify = (-1);
        $vnotify = intval($vnotify);

        $pubs_available = $this->pubstreamNotifyAvailable($uid);
        foreach ($this->vnotifyBits() as $k => $bit) {
            if (!isset($data[$k]))
                continue;
            // Toggles hidden from this user always arrive as 0 — keep their stored bits
            if ($k === 'vnotify11' && !is_site_admin())
                continue;
            if ($k === 'vnotify13' && !$pubs_available)
                continue;
            $vnotify = (intval($data[$k]) ? ($vnotify | $bit) : ($vnotify & ~$bit));
        }
        set_pconfig($uid, 'system', 'vnotify', $vnotify);

        $toggles = [
            'post_newfriend',
            'post_joingroup',
            'post_profilechange',
            'always_show_in_notices',
            'update_notices_per_parent',
        ];
        foreach ($toggles as $k) {
            if (isset($data[$k]))
                set_pconfig($uid, 'system', $k, (intval($data[$k]) ? 1 : 0));
        }

        if (isset($data['evdays'])) {
            $evdays = intval($data['evdays']);
            if ($evdays < 1)
                $evdays = 3;
            set_pconfig($uid, 'system', 'evdays', $evdays);
        }

        if (array_key_exists('mailhost', $data))
            set_pconfig($uid, 'system', 'email_notify_host', notags(trim((string) $data['mailhost'])));

        q("UPDATE channel SET channel_notifyflags = %d WHERE channel_id = %d",
            intval($notify), intval($uid));

        \Zotlabs\Lib\Libsync::build_sync_packet();

        Response::send(['status' => 'ok']);
    }

    private function getDangerSettings(): void
    {
        $uid = local_channel();
        $channel = App::get_channel();
        Response::send([
            'nick' => $channel['channel_address'] ?? '',
            'name' => $channel['channel_name'] ?? '',
            'account_email' => App::$account['account_email'] ?? '',
        ]);
    }

    public function post(): void
    {
        $uid = Auth::requireLocalJson();
        $data = Auth::$parsedBody;

        if (!$data) {
            Response::error(400, 'Invalid JSON body');
        }

        $datatype = \App::$argv[2] ?? 'display';
        switch ($datatype) {
            case 'display':
                $this->postDisplaySettings($uid, $data);
                break;
            case 'profile':
                $this->postProfileSettings($uid, $data);
                break;
            case 'privacy':
                $this->postPrivacySettings($uid, $data);
                break;
            case 'channel':
                $this->postChannelSettings($uid, $data);
                break;
            case 'notifications':
                $this->postNotificationSettings($uid, $data);
                break;
            case 'integrations':
                $this->postIntegrationsSettings($uid, $data);
                break;
            case 'features':
                $this->postFeaturesSettings($uid, $data);
                break;
            case 'danger':
                $this->postDangerSettings($uid, $data);
                break;
            case 'locations':
                $this->postLocationsSettings($uid, $data);
                break;
            default:
                Response::error(404, 'Unknown settings section');
        }
    }

    private function postDisplaySettings(int $uid, array $data): void
    {
        if (isset($data['thread_allow']))
            set_pconfig($uid, 'system', 'thread_allow', intval($data['thread_allow']));
        if (isset($data['update_interval']))
            set_pconfig($uid, 'system', 'update_interval', intval($data['update_interval']) * 1000);
        if (isset($data['itemspage']))
            set_pconfig($uid, 'system', 'itemspage', max(1, min(30, intval($data['itemspage']))));
        if (isset($data['title_tosource']))
            set_pconfig($uid, 'system', 'title_tosource', intval($data['title_tosource']));
        if (isset($data['start_menu']))
            set_pconfig($uid, 'system', 'start_menu', intval($data['start_menu']));
        if (isset($data['user_scalable']))
            set_pconfig($uid, 'system', 'user_scalable', intval($data['user_scalable']));
        if (isset($data['theme'])) {
            $themespec = explode(':', \App::$channel['channel_theme']);
            $newtheme  = notags(trim($data['theme']));
            $newschema = ($themespec[0] === $newtheme) ? ($themespec[1] ?? '') : '';
            $theme_val = $newtheme . ($newschema ? ':' . $newschema : '');
            q("UPDATE channel SET channel_theme = '%s' WHERE channel_id = %d",
                dbesc($theme_val), intval($uid));
            $_SESSION['theme'] = $theme_val;
        }
        $valid_font_sizes_post    = ['small', 'medium', 'large', 'xl'];
        $valid_font_families_post = ['system', 'serif', 'monospace', 'nunito', 'saira', 'share-tech', 'playfair', 'libre-baskerville', 'comfortaa', 'space-mono', 'iosevka', 'righteous', 'playwrite-england', 'comic', 'opendyslexic', 'inter', 'atkinson-hyperlegible', 'literata', 'jetbrains-mono'];
        if (isset($data['font_size']) && in_array($data['font_size'], $valid_font_sizes_post, true))
            set_pconfig($uid, 'spa', 'font_size', $data['font_size']);
        if (isset($data['font_family']) && in_array($data['font_family'], $valid_font_families_post, true))
            set_pconfig($uid, 'spa', 'font_family', $data['font_family']);

        $valid_color_schemes_post = [
            'light', 'pastel-soft', 'warm-paper', 'mint', 'sakura', 'latte-cream',
            'dark', 'nord', 'dracula', 'monokai', 'one-dark', 'cyberpunk',
            'rose-pine', 'gruvbox-dark', 'gruvbox-light', 'catppuccin-latte',
            'catppuccin-mocha', 'solarized-light', 'solarized-dark', 'tokyo-night', 'matrix',
            'high-contrast', 'high-contrast-light', 'custom',
        ];
        if (isset($data['color_scheme']) && in_array($data['color_scheme'], $valid_color_schemes_post, true))
            set_pconfig($uid, 'spa', 'color_scheme', $data['color_scheme']);

        if (isset($data['custom_theme_colors'])) {
            $raw = (string) $data['custom_theme_colors'];
            $decoded = json_decode($raw, true);
            if (is_array($decoded)
                && isset($decoded['base'], $decoded['txt'], $decoded['accent'], $decoded['isDark'])
                && preg_match('/^#[0-9a-fA-F]{6}$/', $decoded['base'])
                && preg_match('/^#[0-9a-fA-F]{6}$/', $decoded['txt'])
                && preg_match('/^#[0-9a-fA-F]{6}$/', $decoded['accent'])
                && is_bool($decoded['isDark'])
            ) {
                set_pconfig($uid, 'spa', 'custom_theme_colors', $raw);
            }
        }

        if (array_key_exists('bg_url', $data)) {
            $bg_url = notags(trim((string) $data['bg_url']));
            // Accept empty string (clear), a valid http/https URL, or a server-relative path (preset assets)
            if ($bg_url === '' || (filter_var($bg_url, FILTER_VALIDATE_URL) && preg_match('#^https?://#i', $bg_url)) || preg_match('#^/#', $bg_url))
                set_pconfig($uid, 'spa', 'bg_url', $bg_url);
        }
        if (isset($data['bg_fit']) && in_array($data['bg_fit'], ['tile', 'cover'], true))
            set_pconfig($uid, 'spa', 'bg_fit', $data['bg_fit']);

        if (isset($data['scroll_style']) && in_array($data['scroll_style'], ['endless', 'load_more'], true))
            set_pconfig($uid, 'spa', 'scroll_style', $data['scroll_style']);

        if (isset($data['post_height'])) {
            $ph = intval($data['post_height']);
            set_pconfig($uid, 'spa', 'post_height', $ph > 0 ? min(max($ph, 100), 5000) : 0);
        }

        if (isset($data['corner_radius']) && in_array($data['corner_radius'], ['none', 'sm', 'default', 'lg', 'xl'], true))
            set_pconfig($uid, 'spa', 'corner_radius', $data['corner_radius']);

        if (isset($data['comment_order']) && in_array($data['comment_order'], ['oldest_first', 'newest_first'], true))
            set_pconfig($uid, 'spa', 'comment_order', $data['comment_order']);

        if (isset($data['thread_mode']) && in_array($data['thread_mode'], ['threaded', 'flat'], true))
            set_pconfig($uid, 'spa', 'thread_mode', $data['thread_mode']);

        if (isset($data['show_emoji_images']))
            set_pconfig($uid, 'system', 'no_smilies', 1 - intval($data['show_emoji_images']));

        Response::send(['status' => 'ok']);
    }

    private function postPrivacySettings(int $uid, array $data): void
    {
        $toggles = [
            'autoperms',
            'index_opt_out',
            'permit_all_mentions',
            'moderate_unsolicited_comments',
            'ocap_enabled',
        ];
        foreach ($toggles as $t) {
            if (isset($data[$t]))
                set_pconfig($uid, 'system', $t, ((intval($data[$t]) == 1) ? 1 : 0));
        }

        if (isset($data['nsfw_words']))
            set_pconfig($uid, 'nsfw', 'words', notags(trim((string) $data['nsfw_words'])));

        // Composer opt-in: post a "wall only" item that skips Notifier
        // delivery entirely. Not a core feature, so it lives in the SPA's own
        // 'spa' pconfig cat rather than the toggles loop above.
        if (isset($data['local_only_posts']))
            set_pconfig($uid, 'spa', 'local_only_posts', ((intval($data['local_only_posts']) == 1) ? 1 : 0));

        Master::Summon(['Directory', $uid]);
        Libsync::build_sync_packet();

        Response::send(['status' => 'ok']);
    }

    private function postChannelSettings(int $uid, array $data): void
    {
        $channel = App::get_channel();

        $role = notags(trim((string) ($data['permissions_role'] ?? '')));
        if (!$role || !in_array($role, array_keys(PermissionRoles::channel_roles()), true))
            Response::error(400, 'Please select a channel role');

        if ($role !== get_pconfig($uid, 'system', 'permissions_role')) {
            $role_permissions = PermissionRoles::role_perms($role);

            if (isset($role_permissions['limits'])) {
                foreach ($role_permissions['limits'] as $k => $v) {
                    PermissionLimits::Set($uid, $k, $v);
                }
            }

            set_pconfig($uid, 'system', 'group_actor',
                ((($role_permissions['channel_type'] ?? '') === 'group') ? 1 : 0));
        }

        $timezone = notags(trim((string) ($data['timezone'] ?? '')));
        if (!in_array($timezone, \DateTimeZone::listIdentifiers(), true))
            $timezone = $channel['channel_timezone'];

        $adult = ((intval($data['adult'] ?? 0) == 1) ? 1 : 0);
        $pageflags = intval($channel['channel_pageflags']);
        if ($adult != (($pageflags & PAGE_ADULT) ? 1 : 0))
            $pageflags = ($pageflags ^ PAGE_ADULT);

        set_pconfig($uid, 'system', 'permissions_role', $role);
        set_pconfig($uid, 'system', 'use_browser_location', ((intval($data['allow_location'] ?? 0) == 1) ? 1 : 0));
        set_pconfig($uid, 'system', 'photo_path', escape_tags(trim((string) ($data['photo_path'] ?? ''))));
        set_pconfig($uid, 'system', 'attach_path', escape_tags(trim((string) ($data['attach_path'] ?? ''))));
        set_pconfig($uid, 'system', 'message_filter_incl', trim((string) ($data['message_filter_incl'] ?? '')));
        set_pconfig($uid, 'system', 'message_filter_excl', trim((string) ($data['message_filter_excl'] ?? '')));

        // Permission limits and the group-actor flag only apply to the custom role (core keeps
        // these in Settings/Privacy; the SPA surfaces them as a modal on the channel form).
        // Runs after the role-change block so explicit values win over the role preset.
        if ($role === 'custom') {
            foreach (Permissions::Perms() as $k => $perm) {
                if (isset($data[$k]))
                    PermissionLimits::Set($uid, $k, intval($data[$k]));
            }
            if (isset($data['group_actor']))
                set_pconfig($uid, 'system', 'group_actor', ((intval($data['group_actor']) == 1) ? 1 : 0));
        }

        // channel_max_friend_req: core's settings page displays maxreq but omits it from its
        // post handler; persisting it here is deliberate.
        q("UPDATE channel SET channel_pageflags = %d, channel_timezone = '%s',
            channel_location = '%s', channel_expire_days = %d, channel_max_friend_req = %d
            WHERE channel_id = %d",
            intval($pageflags),
            dbesc($timezone),
            dbesc(notags(trim((string) ($data['defloc'] ?? '')))),
            intval($data['expire'] ?? 0),
            max(0, intval($data['maxreq'] ?? 0)),
            intval($uid)
        );

        Master::Summon(['Directory', $uid]);
        Libsync::build_sync_packet();

        Response::send(['status' => 'ok']);
    }


    private function postIntegrationsSettings(int $uid, array $data): void
    {
        $action = $data['action'] ?? '';

        if (!in_array($action, ['install', 'uninstall', 'nav', 'reorder', 'toggle-frontend', 'kanban'], true))
            Response::error(400, 'Invalid request');

        // The cards module's kanban board — a plain per-user flag rather than an
        // app, since it switches a view inside an app that's already installed.
        if ($action === 'kanban') {
            set_pconfig($uid, 'spa', 'kanban', intval($data['enabled'] ?? 0) === 1 ? 1 : 0);
            Response::send(['status' => 'ok']);
        }

        if ($action === 'reorder') {
            $raw = $data['order'] ?? null;
            if (!is_array($raw)) Response::error(400, 'Invalid order');
            $order = array_values(array_filter(
                array_map(fn($n) => is_string($n) ? notags(trim($n)) : null, $raw),
                fn($n) => $n !== null && $n !== ''
            ));
            set_pconfig($uid, 'spa', 'nav_order', json_encode($order));
            Response::send(['status' => 'ok']);
        }

        if ($action === 'toggle-frontend') {
            $id = notags(trim($data['id'] ?? ''));
            if (!$id) Response::error(400, 'Invalid request');
            // The list holds modules whose state the user flipped away from the
            // module's default; only the client knows those defaults, so it sends
            // `override`. `enabled` is the pre-defaults fallback for old bundles.
            $override = array_key_exists('override', $data)
                ? !empty($data['override'])
                : empty($data['enabled']);
            $raw = get_pconfig($uid, 'spa', 'disabled_frontend_modules', '');
            $disabled = $raw ? (json_decode($raw, true) ?? []) : [];
            if (!is_array($disabled)) $disabled = [];
            $disabled = array_values(array_diff($disabled, [$id]));
            if ($override) $disabled[] = $id;
            set_pconfig($uid, 'spa', 'disabled_frontend_modules', json_encode($disabled));
            Response::send(['status' => 'ok']);
        }

        $name = notags(trim($data['name'] ?? ''));
        if (!$name) Response::error(400, 'Invalid request');

        // All operations key on the whirlpool-hash guid used by Hubzilla for system apps
        $guid = hash('whirlpool', $name);

        if ($action === 'install') {
            // translate=false — must match the canonical $name the frontend
            // sent (sourced from getIntegrationsSettings(), also canonical).
            $system = \Zotlabs\Lib\Apps::get_system_apps(false);
            $app    = null;
            foreach ($system as $s) {
                if (($s['name'] ?? '') === $name) { $app = $s; break; }
            }
            if (!$app) Response::error(404, 'App not found');

            $app['uid']    = $uid;
            $app['guid']   = $guid;
            $app['system'] = 1;
            \Zotlabs\Lib\Apps::app_install($uid, $app);

        } elseif ($action === 'uninstall') {
            \Zotlabs\Lib\Apps::app_destroy($uid, ['guid' => $guid]);

        } elseif ($action === 'nav') {
            // app must be installed first
            $installed = q(
                "SELECT id FROM app WHERE app_id = '%s' AND app_channel = %d AND app_deleted = 0 LIMIT 1",
                dbesc($guid), intval($uid)
            );
            if (!$installed) Response::error(400, 'App must be installed first');

            // app_feature() is a pure toggle (add/remove), not a set — only
            // flip a term when its current presence disagrees with the
            // desired end state. "enabled" folds pin+feature into one
            // switch: on sets pinned (sufficient alone for nav visibility),
            // off clears both so a legacy featured-only app also disappears.
            $terms = q(
                "SELECT term FROM term WHERE otype = %d AND oid = %d",
                intval(TERM_OBJ_APP), intval($installed[0]['id'])
            );
            $termNames = $terms ? array_column($terms, 'term') : [];
            $isPinned   = in_array('nav_pinned_app', $termNames, true);
            $isFeatured = in_array('nav_featured_app', $termNames, true);
            $enabled    = !empty($data['enabled']);

            if ($enabled) {
                if (!$isPinned) \Zotlabs\Lib\Apps::app_feature($uid, ['guid' => $guid], 'nav_pinned_app');
            } else {
                if ($isPinned)   \Zotlabs\Lib\Apps::app_feature($uid, ['guid' => $guid], 'nav_pinned_app');
                if ($isFeatured) \Zotlabs\Lib\Apps::app_feature($uid, ['guid' => $guid], 'nav_featured_app');
            }
        }

        Response::send(['status' => 'ok']);
    }

    private function postLocationsSettings(int $uid, array $data): void
    {
        $channel = App::get_channel();
        $action = $data['action'] ?? '';

        if (!in_array($action, ['set_primary', 'drop', 'sync'], true))
            Response::error(400, 'Invalid request');

        if ($action === 'sync') {
            Master::Summon(['Notifier', 'refresh_all', $channel['channel_id']]);
            Response::send(['success' => true]);
        }

        $hubloc_id = intval($data['id'] ?? 0);
        if (!$hubloc_id) Response::error(400, 'Invalid request');

        $r = q(
            "select * from hubloc where hubloc_id = %d and hubloc_hash = '%s' limit 1",
            intval($hubloc_id),
            dbesc($channel['channel_hash'])
        );
        if (!$r) Response::error(404, 'Location not found');

        if ($action === 'set_primary') {
            q(
                "UPDATE hubloc SET hubloc_primary = 0 WHERE hubloc_primary = 1 AND hubloc_hash = '%s'",
                dbesc($channel['channel_hash'])
            );
            q(
                "UPDATE hubloc SET hubloc_primary = 1 WHERE hubloc_id = %d AND hubloc_hash = '%s'",
                intval($hubloc_id),
                dbesc($channel['channel_hash'])
            );

            $x = q(
                "select * from hubloc where hubloc_id = %d and hubloc_hash = '%s'",
                intval($hubloc_id),
                dbesc($channel['channel_hash'])
            );
            if ($x) hubloc_change_primary($x[0]);

            Master::Summon(['Notifier', 'refresh_all', $channel['channel_id']]);
            Response::send(['success' => true]);
        }

        // drop
        if ($r[0]['hubloc_url'] === z_root())
            Response::error(400, 'Cannot drop the local hub location');

        if (intval($r[0]['hubloc_primary'])) {
            $x = q(
                "select hubloc_id from hubloc where hubloc_primary = 1 and hubloc_hash = '%s'",
                dbesc($channel['channel_hash'])
            );
            if (!$x || count($x) === 1)
                Response::error(400, 'Please select another location to become primary before removing the primary location');
        }

        q(
            "UPDATE hubloc SET hubloc_deleted = 1 WHERE hubloc_id_url = '%s' AND hubloc_hash = '%s'",
            dbesc($r[0]['hubloc_id_url']),
            dbesc($channel['channel_hash'])
        );

        Master::Summon(['Notifier', 'refresh_all', $channel['channel_id']]);
        Response::send(['success' => true]);
    }

    private function postProfileSettings(int $uid, array $data): void
    {
        $fields = [
            'fullname' => notags(trim($data['name'] ?? '')),
            'pdesc' => notags(trim($data['pdesc'] ?? '')),
            'homepage' => notags(trim($data['homepage'] ?? '')),
            'hometown' => notags(trim($data['hometown'] ?? '')),
            'gender' => notags(trim($data['gender'] ?? '')),
            'dob' => notags(trim($data['birthday'] ?? '')),
            'about' => escape_tags($data['about'] ?? ''),
            'keywords' => notags(trim($data['keywords'] ?? '')),
            'hide_friends' => intval($data['hide_friends'] ?? 0),
            'publish' => intval($data['publish'] ?? 0),
        ];

        $profile = \Zotlabs\Lib\Profile::load($uid, 'default');
        if (!$profile)
            Response::error(404, 'Profile not found');

        q("UPDATE profile SET
        fullname = '%s', pdesc = '%s', homepage = '%s', hometown = '%s',
        gender = '%s', dob = '%s', about = '%s', keywords = '%s', hide_friends = %d, publish = %d
        WHERE uid = %d AND is_default = 1",
            dbesc($fields['fullname']),
            dbesc($fields['pdesc']),
            dbesc($fields['homepage']),
            dbesc($fields['hometown']),
            dbesc($fields['gender']),
            dbesc($fields['dob']),
            dbesc($fields['about']),
            dbesc($fields['keywords']),
            intval($fields['hide_friends']),
            intval($fields['publish']),
            intval($uid));

        // Sync xchan_hidden immediately so directory listing takes effect without waiting for the daemon
        $channel = \App::get_channel();
        if ($channel) {
            $hidden = 1 - $fields['publish'];
            q("UPDATE xchan SET xchan_hidden = %d WHERE xchan_hash = '%s'",
                intval($hidden),
                dbesc($channel['channel_hash']));
        }

        // Propagate name change to channel table
        if ($fields['fullname'])
            q("UPDATE channel SET channel_name = '%s' WHERE channel_id = %d",
                dbesc($fields['fullname']), intval($uid));

        Response::send(['status' => 'ok']);
    }

    private function postDangerSettings(int $uid, array $data): void
    {
        $action = $data['action'] ?? '';

        if ($action === 'remove_channel') {
            // Mirrors Zotlabs\Module\Removeme
            $account = App::get_account();
            if (!$account)
                Response::error(403, 'Permission denied');

            // Channel removal is irreversible — require the account password as a
            // confirmation step (defence-in-depth beyond the CSRF-protected session).
            $password = (string) ($data['password'] ?? '');
            if ($password === '')
                Response::error(400, 'Password confirmation is required');

            $x = account_verify_password($account['account_email'], $password);
            if (!$x || !$x['account'])
                Response::error(403, 'Incorrect password');

            if ($account['account_password_changed'] > \DBA::$dba->get_null_date()) {
                $d1 = datetime_convert('UTC', 'UTC', 'now - 48 hours');
                if ($account['account_password_changed'] > $d1)
                    Response::error(403, 'Channel removals are not allowed within 48 hours of changing the account password.');
            }

            channel_remove($uid, true, true);
            Response::send(['status' => 'ok', 'redirect' => z_root()]);
        }

        Response::error(400, 'Unknown action');
    }

    // Feature groups/names not offered in the SPA (no corresponding UI, or
    // superseded — e.g. emoji reactions are always on here).
    private const EXCLUDED_FEATURE_GROUPS = ['channel_home', 'connections'];
    private const EXCLUDED_FEATURES = ['emojis'];

    private function getFeaturesSettings(): void
    {
        $uid = local_channel();
        require_once('include/features.php');

        $features_raw = get_features(false);
        $result = [];

        foreach ($features_raw as $group_key => $group) {
            if (!is_array($group) || in_array($group_key, self::EXCLUDED_FEATURE_GROUPS, true)) continue;
            $group_label = '';
            foreach ($group as $idx => $item) {
                if ($idx === 0) {
                    $group_label = is_string($item) ? $item : '';
                    continue;
                }
                if (!is_array($item) || count($item) < 2) continue;

                $name = $item[0] ?? '';
                if (!$name || in_array($name, self::EXCLUDED_FEATURES, true)) continue;

                $result[] = [
                    'name'        => $name,
                    'label'       => $item[1] ?? $name,
                    'description' => $item[2] ?? '',
                    'group'       => $group_label,
                    'enabled'     => (bool) feature_enabled($uid, $name),
                ];
            }
        }

        Response::send(['features' => $result]);
    }

    private function postFeaturesSettings(int $uid, array $data): void
    {
        require_once('include/features.php');

        $feature = notags(trim($data['feature'] ?? ''));
        $enabled = intval($data['enabled'] ?? 0) ? 1 : 0;

        if (!$feature) Response::error(400, 'Feature name required');

        // Validate the feature exists in the system feature list and isn't
        // one the SPA excludes from its Features UI.
        if (in_array($feature, self::EXCLUDED_FEATURES, true)) Response::error(400, 'Unknown feature');

        $features_raw = get_features(false);
        $valid = false;
        foreach ($features_raw as $group_key => $group) {
            if (!is_array($group) || in_array($group_key, self::EXCLUDED_FEATURE_GROUPS, true)) continue;
            foreach ($group as $idx => $item) {
                if ($idx === 0 || !is_array($item)) continue;
                if (($item[0] ?? '') === $feature) {
                    $valid = true;
                    break 2;
                }
            }
        }

        if (!$valid) Response::error(400, 'Unknown feature');

        set_pconfig($uid, 'feature', $feature, $enabled);
        Response::send(['status' => 'ok', 'enabled' => (bool) $enabled]);
    }
}
