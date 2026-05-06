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
                $this->postProfileSettings($uid, $data);
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
        $default_theme = \Zotlabs\Lib\Config::Get('system', 'theme');
        if (!$default_theme)
            $default_theme = 'redbasic';

        $themespec = explode(':', \App::$channel['channel_theme']);
        $existing_theme = $themespec[0] ?? '';
        $existing_schema = $themespec[1] ?? '';

        $theme = (($existing_theme) ? $existing_theme : $default_theme);
        $allowed_themes_str = \Zotlabs\Lib\Config::Get('system', 'allowed_themes');
        $allowed_themes_raw = explode(',', $allowed_themes_str);
        $allowed_themes = array();
        if (count($allowed_themes_raw))
            foreach ($allowed_themes_raw as $x)
                if (strlen(trim($x)) && is_dir("view/theme/$x"))
                    $allowed_themes[] = trim($x);
        $uid = local_channel();
        Response::send([
            'theme' => \App::$channel['channel_theme'] ?? '',
            'thread_allow' => intval(get_pconfig($uid, 'system', 'thread_allow', 1)),
            'update_interval' => intval(get_pconfig($uid, 'system', 'update_interval', 80000)) / 1000,
            'itemspage' => intval(get_pconfig($uid, 'system', 'itemspage', 10)),
            'no_smilies' => intval(get_pconfig($uid, 'system', 'no_smilies', 0)),
            'title_tosource' => intval(get_pconfig($uid, 'system', 'title_tosource', 0)),
            'start_menu' => intval(get_pconfig($uid, 'system', 'start_menu', 0)),
            'user_scalable' => intval(get_pconfig($uid, 'system', 'user_scalable', 0)),
            'theme' => $themespec[0] ?? '',
            'themes' => array_values($allowed_themes),  // build $allowed_themes the same way Display::get() does
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
        $installed = \Zotlabs\Lib\Apps::app_list($uid, false);
        $system = \Zotlabs\Lib\Apps::get_system_apps(true);
        \Zotlabs\Lib\Apps::translate_system_apps($system);

        $installed_names = array_column(
            array_map(fn($a) => \Zotlabs\Lib\Apps::app_encode($a), $installed ?: []),
            null, 'name'
        );

        $apps = [];
        foreach ($system as $app) {
            $name = $app['name'] ?? '';
            if (!$name)
                continue;
            $apps[] = [
                'name' => $name,
                'description' => $app['description'] ?? '',
                'photo' => $app['photo'] ?? '',
                'installed' => isset($installed_names[$name]),
                'requires' => $app['requires'] ?? '',
            ];
        }

        usort($apps, fn($a, $b) => strcasecmp($a['name'], $b['name']));
        Response::send(['apps' => $apps]);
    }

    private function getNotificationSettings(): void
    {
        load_pconfig(local_channel());

        $channel = App::get_channel();
        $nickname = $channel['channel_address'];
        $timezone = $channel['channel_timezone'];
        $notify = $channel['channel_notifyflags'];
        $defloc = $channel['channel_location'];
        $adult_flag = intval($channel['channel_pageflags'] & PAGE_ADULT);
        $post_newfriend = get_pconfig(local_channel(), 'system', 'post_newfriend');
        $post_newfriend = (($post_newfriend === false) ? '0' : $post_newfriend);  // default if not set: 0
        $post_joingroup = get_pconfig(local_channel(), 'system', 'post_joingroup');
        $post_joingroup = (($post_joingroup === false) ? '0' : $post_joingroup);  // default if not set: 0
        $post_profilechange = get_pconfig(local_channel(), 'system', 'post_profilechange');
        $post_profilechange = (($post_profilechange === false) ? '0' : $post_profilechange);  // default if not set: 0
        $subdir = ((strlen(App::get_path())) ? '<br />' . t('or') . ' ' . z_root() . '/channel/' . $nickname : '');
        $webbie = $nickname . '@' . App::get_hostname();
        $intl_nickname = unpunify($nickname) . '@' . unpunify(App::get_hostname());
        $disable_discover_tab = intval(Config::Get('system', 'disable_discover_tab', 1)) == 1;
        $site_firehose = intval(Config::Get('system', 'site_firehose', 0)) == 1;

        $expire = $channel['channel_expire_days'];
        $sys_expire = Config::Get('system', 'default_expire_days');

        $tpl_addr = get_markup_template('settings_nick_set.tpl');
        $prof_addr = replace_macros($tpl_addr, [
            '$desc' => t('Your channel address is'),
            '$nickname' => (($intl_nickname === $webbie) ? $webbie : $intl_nickname . '&nbsp;(' . $webbie . ')'),
            '$subdir' => $subdir,
            '$davdesc' => t('Your files/photos are accessible via WebDAV at'),
            '$davpath' => z_root() . '/dav/' . $nickname,
            '$basepath' => App::get_hostname()
        ]);

        $evdays = get_pconfig(local_channel(), 'system', 'evdays');
        if (!$evdays)
            $evdays = 3;

        $always_show_in_notices = get_pconfig(local_channel(), 'system', 'always_show_in_notices');
        $update_notices_per_parent = get_pconfig(local_channel(), 'system', 'update_notices_per_parent', 1);

        $vnotify = get_pconfig(local_channel(), 'system', 'vnotify');
        if ($vnotify === false)
            $vnotify = (-1);

        $perm_roles = PermissionRoles::channel_roles();
        $permissions_role = get_pconfig(local_channel(), 'system', 'permissions_role');

        if (!in_array($permissions_role, ['public', 'personal', 'group', 'custom'])) {
            notice(t('Please select a channel role') . EOL);
            array_unshift($perm_roles, '');
        }

        $plugin = ['basic' => '', 'notify' => ''];
        call_hooks('channel_settings', $plugin);

        $yes_no = [t('No'), t('Yes')];

        Response::send([
            '$ptitle' => t('Channel Settings'),
            '$submit' => t('Submit'),
            '$baseurl' => z_root(),
            '$uid' => local_channel(),
            '$form_security_token' => get_form_security_token('settings'),
            '$role' => ['permissions_role', t('Channel role'), $permissions_role, '', $perm_roles],
            '$nickname_block' => $prof_addr,
            '$h_basic' => t('Basic Settings'),
            '$timezone' => ['timezone_select', t('Channel timezone:'), $timezone, '', get_timezones()],
            '$defloc' => ['defloc', t('Default post location:'), $defloc, t('Geographical location to display on your posts')],
            '$allowloc' => ['allow_location', t('Use browser location'), ((get_pconfig(local_channel(), 'system', 'use_browser_location')) ? 1 : ''), '', $yes_no],
            '$adult' => ['adult', t('Adult content'), $adult_flag, t('This channel frequently or regularly publishes adult content'), $yes_no],
            '$maxreq' => ['maxreq', t('Maximum Friend Requests/Day:'), intval($channel['channel_max_friend_req']), t('May reduce spam activity')],
            '$h_not' => t('Notification Settings'),
            '$activity_options' => t('By default post a status message when:'),
            '$post_newfriend' => ['post_newfriend', t('accepting a friend request'), $post_newfriend, '', $yes_no],
            '$post_joingroup' => ['post_joingroup', t('joining a forum/community'), $post_joingroup, '', $yes_no],
            '$post_profilechange' => ['post_profilechange', t('making an <em>interesting</em> profile change'), $post_profilechange, '', $yes_no],
            '$lbl_not' => t('Send a notification email when:'),
            '$notify1' => ['notify1', t('You receive a connection request'), ($notify & NOTIFY_INTRO), NOTIFY_INTRO, '', $yes_no],
            '$notify2' => ['notify2', t('Your connections are confirmed'), ($notify & NOTIFY_CONFIRM), NOTIFY_CONFIRM, '', $yes_no],
            '$notify3' => ['notify3', t('Someone writes on your profile wall'), ($notify & NOTIFY_WALL), NOTIFY_WALL, '', $yes_no],
            '$notify4' => ['notify4', t('Someone writes a followup comment'), ($notify & NOTIFY_COMMENT), NOTIFY_COMMENT, '', $yes_no],
            '$notify5' => ['notify5', t('You receive a private message'), ($notify & NOTIFY_MAIL), NOTIFY_MAIL, '', $yes_no],
            '$notify6' => ['notify6', t('You receive a friend suggestion'), ($notify & NOTIFY_SUGGEST), NOTIFY_SUGGEST, '', $yes_no],
            '$notify7' => ['notify7', t('You are tagged in a post'), ($notify & NOTIFY_TAGSELF), NOTIFY_TAGSELF, '', $yes_no],
            '$notify8' => ['notify8', t('You are poked/prodded/etc. in a post'), ($notify & NOTIFY_POKE), NOTIFY_POKE, '', $yes_no],
            '$notify9' => ['notify9', t('Someone likes your post/comment'), ($notify & NOTIFY_LIKE), NOTIFY_LIKE, '', $yes_no],
            '$lbl_vnot' => t('Show visual notifications including:'),
            '$vnotify1' => ['vnotify1', t('Unseen stream activity'), ($vnotify & VNOTIFY_NETWORK), VNOTIFY_NETWORK, '', $yes_no],
            '$vnotify2' => ['vnotify2', t('Unseen channel activity'), ($vnotify & VNOTIFY_CHANNEL), VNOTIFY_CHANNEL, '', $yes_no],
            '$vnotify3' => ['vnotify3', t('Unseen private messages'), ($vnotify & VNOTIFY_MAIL), VNOTIFY_MAIL, t('Recommended'), $yes_no],
            '$vnotify4' => ['vnotify4', t('Upcoming events'), ($vnotify & VNOTIFY_EVENT), VNOTIFY_EVENT, '', $yes_no],
            '$vnotify5' => ['vnotify5', t('Events today'), ($vnotify & VNOTIFY_EVENTTODAY), VNOTIFY_EVENTTODAY, '', $yes_no],
            '$vnotify6' => ['vnotify6', t('Upcoming birthdays'), ($vnotify & VNOTIFY_BIRTHDAY), VNOTIFY_BIRTHDAY, t('Not available in all themes'), $yes_no],
            '$vnotify7' => ['vnotify7', t('System (personal) notifications'), ($vnotify & VNOTIFY_SYSTEM), VNOTIFY_SYSTEM, '', $yes_no],
            '$vnotify8' => ['vnotify8', t('System info messages'), ($vnotify & VNOTIFY_INFO), VNOTIFY_INFO, t('Recommended'), $yes_no],
            '$vnotify9' => ['vnotify9', t('System critical alerts'), ($vnotify & VNOTIFY_ALERT), VNOTIFY_ALERT, t('Recommended'), $yes_no],
            '$vnotify10' => ['vnotify10', t('New connections'), ($vnotify & VNOTIFY_INTRO), VNOTIFY_INTRO, t('Recommended'), $yes_no],
            '$vnotify11' => ((is_site_admin()) ? ['vnotify11', t('System Registrations'), ($vnotify & VNOTIFY_REGISTER), VNOTIFY_REGISTER, '', $yes_no] : []),
            '$vnotify12' => ['vnotify12', t('Unseen shared files'), ($vnotify & VNOTIFY_FILES), VNOTIFY_FILES, '', $yes_no],
            '$vnotify13' => ((($disable_discover_tab && !$site_firehose) || !Apps::system_app_installed(local_channel(), 'Public Stream')) ? [] : ['vnotify13', t('Unseen public stream activity'), ($vnotify & VNOTIFY_PUBS), VNOTIFY_PUBS, '', $yes_no]),
            '$vnotify14' => ['vnotify14', t('Unseen likes and dislikes'), ($vnotify & VNOTIFY_LIKE), VNOTIFY_LIKE, '', $yes_no],
            '$vnotify15' => ['vnotify15', t('Unseen forum posts'), ($vnotify & VNOTIFY_FORUMS), VNOTIFY_FORUMS, '', $yes_no],
            '$mailhost' => ['mailhost', t('Email notification hub (hostname)'), get_pconfig(local_channel(), 'system', 'email_notify_host', App::get_hostname()), sprintf(t('If your channel is mirrored to multiple hubs, set this to your preferred location. This will prevent duplicate email notifications. Example: %s'), App::get_hostname())],
            '$always_show_in_notices' => ['always_show_in_notices', t('Show new wall posts, private messages and connections under Notices'), $always_show_in_notices, 1, '', $yes_no],
            '$update_notices_per_parent' => ['update_notices_per_parent', t('Mark all notices of the thread read if a notice is clicked'), $update_notices_per_parent, 1, t('If no, only the clicked notice will be marked read'), $yes_no],
            '$desktop_notifications_info' => t('Desktop notifications are unavailable because the required browser permission has not been granted'),
            '$desktop_notifications_request' => t('Grant permission'),
            '$evdays' => ['evdays', t('Notify me of events this many days in advance'), $evdays, t('Must be greater than 0')],
            '$basic_addon' => $plugin['basic'],
            '$notify_addon' => $plugin['notify'],
            '$photo_path' => ['photo_path', t('Default photo upload folder'), get_pconfig(local_channel(), 'system', 'photo_path'), t('%Y - current year, %m -  current month')],
            '$attach_path' => ['attach_path', t('Default file upload folder'), get_pconfig(local_channel(), 'system', 'attach_path'), t('%Y - current year, %m -  current month')],
            '$removeme' => t('Remove Channel'),
            '$removechannel' => t('Remove this channel.'),
            '$expire' => ['expire', t('Expire other channel content after this many days'), $expire, t('0 or blank to use the website limit.') . ' ' . ((intval($sys_expire)) ? sprintf(t('This website expires after %d days.'), intval($sys_expire)) : t('This website does not expire imported content.')) . ' ' . t('The website limit takes precedence if lower than your limit.')],
            '$message_filter_excl' => ['message_filter_excl', t('Do not import posts with this text'), get_pconfig(local_channel(), 'system', 'message_filter_excl', ''), t('Words one per line or #tags, $categories, /patterns/, lang=xx, lang!=xx - leave blank to import all posts')],
            '$message_filter_incl' => ['message_filter_incl', t('Only import posts with this text'), get_pconfig(local_channel(), 'system', 'message_filter_incl', ''), t('Words one per line or #tags, $categories, /patterns/, lang=xx, lang!=xx - leave blank to import all posts')]
        ]);
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
            case 'danger':
                $this->postDangerSettings($uid, $data);
                break;
            default:
                Response::error(404, 'Unknown settings section');
        }
    }

    private function postDisplaySettings(int $uid, array $data): void
    {
        $themespec = explode(':', \App::$channel['channel_theme']);

        if (isset($data['thread_allow']))
            set_pconfig($uid, 'system', 'thread_allow', intval($data['thread_allow']));
        if (isset($data['update_interval']))
            set_pconfig($uid, 'system', 'update_interval', intval($data['update_interval']) * 1000);
        if (isset($data['itemspage']))
            set_pconfig($uid, 'system', 'itemspage', max(1, min(30, intval($data['itemspage']))));
        if (isset($data['no_smilies']))
            set_pconfig($uid, 'system', 'no_smilies', intval($data['no_smilies']));
        if (isset($data['title_tosource']))
            set_pconfig($uid, 'system', 'title_tosource', intval($data['title_tosource']));
        if (isset($data['start_menu']))
            set_pconfig($uid, 'system', 'start_menu', intval($data['start_menu']));
        if (isset($data['user_scalable']))
            set_pconfig($uid, 'system', 'user_scalable', intval($data['user_scalable']));
        if (isset($data['theme'])) {
            $newtheme = notags(trim($data['theme']));
            $newschema = ($themespec[0] === $newtheme) ? ($themespec[1] ?? '') : '';
            $theme_val = $newtheme . ($newschema ? ':' . $newschema : '');
            q("UPDATE channel SET channel_theme = '%s' WHERE channel_id = %d",
                dbesc($theme_val), intval($uid));
            $_SESSION['theme'] = $theme_val;
        }

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
        $name = notags(trim($data['name'] ?? ''));
        $action = $data['action'] ?? '';  // 'install' | 'uninstall'

        if (!$name || !in_array($action, ['install', 'uninstall']))
            Response::error(400, 'Invalid request');

        $system = \Zotlabs\Lib\Apps::get_system_apps(true);
        $app = null;
        foreach ($system as $s) {
            if (($s['name'] ?? '') === $name) {
                $app = $s;
                break;
            }
        }

        if (!$app)
            Response::error(404, 'App not found');

        if ($action === 'install')
            \Zotlabs\Lib\Apps::import_app($app, $uid);
        else
            \Zotlabs\Lib\Apps::uninstall_app_by_name($uid, $name);

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
        ];

        $profile = \Zotlabs\Lib\Profile::load($uid, 'default');
        if (!$profile)
            Response::error(404, 'Profile not found');

        q("UPDATE profile SET
        fullname = '%s', pdesc = '%s', homepage = '%s', hometown = '%s',
        gender = '%s', dob = '%s', about = '%s', keywords = '%s', hide_friends = %d
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
            intval($uid));

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
            // Mirrors what the core settings module does
            $account_id = get_account_id();
            if (!$account_id)
                Response::error(403, 'Permission denied');

            \Zotlabs\Lib\Channel::channel_remove($uid, $account_id, true);
            Response::send(['status' => 'ok', 'redirect' => z_root()]);
        }

        Response::error(400, 'Unknown action');
    }
}
