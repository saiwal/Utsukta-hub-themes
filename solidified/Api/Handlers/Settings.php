<?php
namespace Theme\Solidified\Api\Handlers;

use Theme\Solidified\Api\Auth;
use Theme\Solidified\Api\Response;
use Zotlabs\Access\PermissionLimits;
use Zotlabs\Access\PermissionRoles;
use Zotlabs\Access\Permissions;
use Zotlabs\Lib\Apps;
use Zotlabs\Lib\Config;
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
        $valid_font_families = ['system', 'serif', 'monospace', 'nunito', 'saira', 'share-tech', 'playfair', 'libre-baskerville', 'comfortaa', 'space-mono', 'iosevka', 'righteous', 'playwrite-england', 'comic', 'opendyslexic'];

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

        $valid_corner_radii = ['none', 'sm', 'default', 'lg', 'xl'];
        $corner_radius = get_pconfig($uid, 'spa', 'corner_radius', 'default');
        if (!in_array($corner_radius, $valid_corner_radii, true)) $corner_radius = 'default';

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
            'corner_radius' => $corner_radius,
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
            'about' => $profile['about'] ?? '',
            'keywords' => $profile['keywords'] ?? '',
            'hide_friends' => intval($profile['hide_friends'] ?? 0),
            'publish' => intval($profile['publish'] ?? 0),
        ]);
    }

    private function getPrivacySettings(): void
    {
        load_pconfig(local_channel());

        $channel = App::get_channel();
        $global_perms = Permissions::Perms();
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
        $limits = PermissionLimits::Get(local_channel());
        $anon_comments = Config::Get('system', 'anonymous_comments', true);

        foreach ($global_perms as $k => $perm) {
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
                $limits[$k],
                ((in_array($k, $help)) ? $help_txt : ''),
                $options
            ];
        }

        // logger('permiss: ' . print_r($permiss,true));

        $autoperms = get_pconfig(local_channel(), 'system', 'autoperms');
        $index_opt_out = get_pconfig(local_channel(), 'system', 'index_opt_out');
        $group_actor = get_pconfig(local_channel(), 'system', 'group_actor');
        $permit_all_mentions = get_pconfig(local_channel(), 'system', 'permit_all_mentions');
        $moderate_unsolicited_comments = get_pconfig(local_channel(), 'system', 'moderate_unsolicited_comments');
        $ocap_enabled = get_pconfig(local_channel(), 'system', 'ocap_enabled');

        $permissions_role = get_pconfig(local_channel(), 'system', 'permissions_role', 'custom');
        $permission_limits = ($permissions_role === 'custom');

        Response::send([
            '$permission_limits' => $permission_limits,
            '$permiss_arr' => $permiss,
            '$autoperms' => $autoperms,
            '$index_opt_out' => $index_opt_out,
            '$group_actor' => $group_actor,
            '$permit_all_mentions' => $permit_all_mentions,
            '$moderate_unsolicited_comments' => $moderate_unsolicited_comments,
            '$ocap_enabled' => $ocap_enabled,
        ]);
    }

    private function getAccountSettings(): void
    {
        $email = \App::$account['account_email'];
        Response::send([
            '$email' => $email,
        ]);
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
            'name' => $observer['xchan_name'] ?? '',
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
        $system = \Zotlabs\Lib\Apps::get_system_apps(true);
        \Zotlabs\Lib\Apps::translate_system_apps($system);

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

            $inst       = $installed_map[$name] ?? null;
            $categories = $inst['categories'] ?? '';

            $apps[] = [
                'name'        => $name,
                'description' => $app['description'] ?? '',
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
            $categories = $inst['categories'] ?? '';
            $apps[] = [
                'name'        => $name,
                'description' => $inst['description'] ?? '',
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

        Response::send(['apps' => $apps, 'nav_order' => array_values((array) $nav_order)]);
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
            'photo_path' => get_pconfig($uid, 'system', 'photo_path', ''),
            'attach_path' => get_pconfig($uid, 'system', 'attach_path', ''),
            'expire' => intval($channel['channel_expire_days']),
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
        $valid_font_families_post = ['system', 'serif', 'monospace', 'nunito', 'saira', 'share-tech', 'playfair', 'libre-baskerville', 'comfortaa', 'space-mono', 'iosevka', 'righteous', 'playwrite-england', 'comic', 'opendyslexic'];
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

        if (isset($data['corner_radius']) && in_array($data['corner_radius'], ['none', 'sm', 'default', 'lg', 'xl'], true))
            set_pconfig($uid, 'spa', 'corner_radius', $data['corner_radius']);

        if (isset($data['show_emoji_images']))
            set_pconfig($uid, 'system', 'no_smilies', 1 - intval($data['show_emoji_images']));

        Response::send(['status' => 'ok']);
    }

    private function postPrivacySettings(int $uid, array $data): void
    {
        $global_perms = \Zotlabs\Access\Permissions::Perms();

        foreach ($global_perms as $k => $perm) {
            if (isset($data[$k]))
                \Zotlabs\Access\PermissionLimits::Set($uid, $k, intval($data[$k]));
        }

        $toggles = [
            'autoperms',
            'index_opt_out',
            'group_actor',
            'permit_all_mentions',
            'moderate_unsolicited_comments',
            'ocap_enabled',
        ];
        foreach ($toggles as $t) {
            if (isset($data[$t]))
                set_pconfig($uid, 'system', $t, intval($data[$t]));
        }

        Response::send(['status' => 'ok']);
    }


    private function postIntegrationsSettings(int $uid, array $data): void
    {
        $action = $data['action'] ?? '';

        if (!in_array($action, ['install', 'uninstall', 'pin', 'feature', 'reorder'], true))
            Response::error(400, 'Invalid request');

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

        $name = notags(trim($data['name'] ?? ''));
        if (!$name) Response::error(400, 'Invalid request');

        // All operations key on the whirlpool-hash guid used by Hubzilla for system apps
        $guid = hash('whirlpool', $name);

        if ($action === 'install') {
            $system = \Zotlabs\Lib\Apps::get_system_apps(true);
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

        } else {
            // pin or feature — app must be installed first
            $installed = q(
                "SELECT id FROM app WHERE app_id = '%s' AND app_channel = %d AND app_deleted = 0 LIMIT 1",
                dbesc($guid), intval($uid)
            );
            if (!$installed) Response::error(400, 'App must be installed first');

            $term = ($action === 'pin') ? 'nav_pinned_app' : 'nav_featured_app';
            \Zotlabs\Lib\Apps::app_feature($uid, ['guid' => $guid], $term);
        }

        Response::send(['status' => 'ok']);
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

    private function getFeaturesSettings(): void
    {
        $uid = local_channel();
        require_once('include/features.php');

        $features_raw = get_features(false);
        $result = [];

        foreach ($features_raw as $group) {
            if (!is_array($group)) continue;
            $group_label = '';
            foreach ($group as $idx => $item) {
                if ($idx === 0) {
                    $group_label = is_string($item) ? $item : '';
                    continue;
                }
                if (!is_array($item) || count($item) < 2) continue;

                $name = $item[0] ?? '';
                if (!$name) continue;

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

        // Validate the feature exists in the system feature list
        $features_raw = get_features(false);
        $valid = false;
        foreach ($features_raw as $group) {
            if (!is_array($group)) continue;
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
