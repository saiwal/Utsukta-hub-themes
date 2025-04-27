<?php
use Zotlabs\Lib\Apps;
use Zotlabs\Lib\Chatroom;
use Zotlabs\Lib\Config;

require_once 'include/security.php';
require_once 'include/menu.php';

function widget_mynavbar($args) {
    /**
     * Build Top navbar
     */
    App::$page['topnav'] = '';
    App::$page['htmlhead'] = App::$page['htmlhead'] ?? '';
    App::$page['htmlhead'] .= <<<EOT
<script>
$(document).ready(function() {
    $("#nav-search-text").search_autocomplete('{$z_root()}/acl');
});
</script>
EOT;

    $is_owner = local_channel() && (App::$profile_uid == local_channel() || App::$profile_uid == 0);
    $observer = App::get_observer();
    $chans = [];
    $channel = [];

    if (local_channel()) {
        $channel = App::get_channel();
        $prof = q("SELECT id FROM profile WHERE uid = %d AND is_default = 1",
            intval($channel['channel_id'])
        );

        if (empty($_SESSION['delegate']) && feature_enabled(local_channel(), 'nav_channel_select')) {
            $chans = q("SELECT channel_name, channel_id FROM channel WHERE channel_account_id = %d AND channel_removed = 0 ORDER BY channel_name",
                intval(get_account_id())
            );
        }

        $sitelocation = '';
    } else {
        $sitelocation = isset(App::$profile['reddress']) ? App::$profile['reddress'] : '@' . App::get_hostname();
    }

    require_once 'include/conversation.php';

    $nav_apps = [];
    $navbar_apps = [];
    $channel_apps = [];

    if (isset(App::$profile['channel_address'])) {
        $channel_apps[] = channel_apps($is_owner, App::$profile['channel_address']);
    }

    /**
     * Provide a banner/logo/whatever
     */
    $banner = Config::Get('system', 'banner') ?: Config::Get('system', 'sitename');
    call_hooks('get_banner', $banner);

    App::$page['header'] = App::$page['header'] ?? '';
    App::$page['header'] .= replace_macros(get_markup_template('hdr.tpl'), []);

    // nav links: array of array('href', 'text', 'extra css classes', 'title')
    $nav = [];

    if (can_view_public_stream()) {
        $nav['pubs'] = true;
    }

    /**
     * Display login or logout
     */
    $nav['usermenu'] = [];
    $nav['loginmenu'] = [];
    $userinfo = [];

    if ($observer) {
        $userinfo = [
            'icon' => $observer['xchan_photo_s'] . '?rev=' . strtotime($observer['xchan_photo_date']),
            'icon_m' => $observer['xchan_photo_m'] . '?rev=' . strtotime($observer['xchan_photo_date']),
            'icon_l' => $observer['xchan_photo_l'] . '?rev=' . strtotime($observer['xchan_photo_date']),
            'icon_mime_type' => $observer['xchan_photo_mimetype'],
            'addr' => $observer['xchan_addr'],
            'url' => $observer['xchan_url'],
            'forum' => $observer['xchan_pubforum'],
            'name' => $observer['xchan_name']
        ];
    }

    if ($channel) {
        $userinfo = array_merge($userinfo, [
            'id' => $channel['channel_id'],
            'nick' => $channel['channel_address'],
            'location' => $channel['channel_location'],
            'theme' => $channel['channel_theme'],
            'timezone' => $channel['channel_timezone'],
            'startpage' => $channel['channel_startpage']
        ]);
    } elseif (empty($_SESSION['authenticated'])) {
        $nav['remote_login'] = remote_login();
        $nav['loginmenu'][] = ['rmagic', t('Remote authentication'), '', t('Click to authenticate to your home hub'), 'rmagic_nav_btn'];
    }

    if (local_channel()) {
        if (empty($_SESSION['delegate'])) {
            $nav['manage'] = ['manage', t('Channels'), '', t('Manage your channels'), 'manage_nav_btn'];
        }

        $nav['settings'] = ['settings', t('Settings'), '', t('Account/Channel Settings'), 'settings_nav_btn'];

        if ($chans && count($chans) > 1) {
            $nav['channels'] = $chans;
        }

        $nav['logout'] = ['logout', t('Logout'), '', t('End this session'), 'logout_nav_btn'];

        // user menu
        $nav['usermenu'][] = [
            'profile/' . $channel['channel_address'],
            t('View Profile'),
            isset(App::$nav_sel['raw_name']) && App::$nav_sel['raw_name'] == 'Profile' ? 'active' : '',
            t('Your profile page'),
            'profile_nav_btn'
        ];

        if (feature_enabled(local_channel(), 'multi_profiles')) {
            $nav['usermenu'][] = [
                'profiles',
                t('Edit Profiles'),
                isset(App::$nav_sel['raw_name']) && App::$nav_sel['raw_name'] == 'Profiles' ? 'active' : '',
                t('Manage/Edit profiles'),
                'profiles_nav_btn'
            ];
        } else {
            $nav['usermenu'][] = [
                'profiles/' . $prof[0]['id'],
                t('Edit Profile'),
                isset(App::$nav_sel['raw_name']) && App::$nav_sel['raw_name'] == 'Profiles' ? 'active' : '',
                t('Edit your profile'),
                'profiles_nav_btn'
            ];
        }
    } else {
        if (!get_account_id()) {
            if (App::$module === 'channel') {
                $nav['login'] = login(true, 'modal_login', false, false);
                $nav['loginmenu'][] = ['login', t('Login'), '', t('Sign in'), ''];
            } else {
                $nav['login'] = login(true, 'modal_login', false, false);
                $nav['loginmenu'][] = ['login', t('Login'), '', t('Sign in'), 'login_nav_btn'];
                App::$page['content'] .= replace_macros(get_markup_template('nav_login.tpl'), [
                    '$nav' => $nav,
                    'userinfo' => $userinfo
                ]);
            }
        } else {
            $nav['alogout'] = ['logout', t('Logout'), '', t('End this session'), 'logout_nav_btn'];
        }
    }

    $my_url = get_my_url() ?: ($observer ? $observer['xchan_url'] : '');
    $homelink_arr = parse_url($my_url);
    $homelink = ($homelink_arr['scheme'] ?? '') . '://' . ($homelink_arr['host'] ?? '');

    if (!$is_owner) {
        $nav['rusermenu'] = [
            $homelink,
            t('Take me home'),
            'logout',
            local_channel() ? t('Logout') : t('Log me out of this site')
        ];
    }

    if (in_array(Config::Get('system', 'register_policy'), [REGISTER_OPEN, REGISTER_APPROVE]) && empty($_SESSION['authenticated'])) {
        $nav['register'] = ['register', t('Register'), '', t('Create an account'), 'register_nav_btn'];
    }

    // TODO: update help content for various modules
    if (false /* !Config::Get('system', 'hide_help') */) {
        $help_url = z_root() . '/help?f=&cmd=' . App::$cmd;
        $context_help = '';
        $enable_context_help = Config::Get('system', 'enable_context_help') !== 0;
        
        if ($enable_context_help) {
            require_once 'include/help.php';
            $context_help = load_context_help();
            $enable_context_help = !empty($context_help);
        }
        
        $nav['help'] = [$help_url, t('Help'), '', t('Help and documentation'), 'help_nav_btn', $context_help, $enable_context_help];
    }

    $search_form_action = match (App::$module) {
        'network' => 'network',
        'channel' => 'channel/' . App::$profile['channel_address'],
        default => 'search'
    };

    $nav['search'] = ['search', t('Search'), '', t('Search site @name, !forum, #tag, ?docs, content'), $search_form_action];

    /**
     * Admin page
     */
    if (is_site_admin()) {
        $nav['admin'] = ['admin/', t('Admin'), '', t('Site Setup and Configuration'), 'admin_nav_btn'];
    }

    $theme_switch_icon = isset(App::$page['color_mode']) ? (App::$page['color_mode'] === 'dark' ? 'sun' : 'moon') : '';

    $x = ['nav' => $nav, 'usermenu' => $userinfo];
    call_hooks('nav', $x);

    $url = '';
    $settings_url = '';

    if (App::$profile_uid && isset(App::$nav_sel['raw_name']) && App::$nav_sel['raw_name']) {
        $active_app = q("SELECT app_url FROM app WHERE app_channel = %d AND app_name = '%s' LIMIT 1",
            intval(App::$profile_uid),
            dbesc(App::$nav_sel['raw_name'])
        );

        if ($active_app) {
            if (strpos($active_app[0]['app_url'], ',')) {
                $urls = explode(',', $active_app[0]['app_url']);
                $url = trim($urls[0]);
                if ($is_owner) {
                    $settings_url = trim($urls[1]);
                }
            } else {
                $url = $active_app[0]['app_url'];
            }
        }
    }

    if (!$settings_url && isset(App::$nav_sel['settings_url'])) {
        $settings_url = App::$nav_sel['settings_url'];
    }

    $pinned_list = [];

    // app bin
    if ($is_owner) {
        $today = datetime_convert('UTC', 'UTC', 'now', 'Y-m-d');
        if (get_pconfig(local_channel(), 'system', 'import_system_apps') !== $today) {
            Apps::import_system_apps();
            set_pconfig(local_channel(), 'system', 'import_system_apps', $today);
        }

        if (get_pconfig(local_channel(), 'system', 'force_import_system_apps') !== STD_VERSION) {
            Apps::import_system_apps();
            set_pconfig(local_channel(), 'system', 'force_import_system_apps', STD_VERSION);
        }

        $list = Apps::app_list(local_channel(), false, ['nav_pinned_app']);
        if ($list) {
            $pinned_list = array_map([Apps::class, 'app_encode'], $list);
        }

        Apps::translate_system_apps($pinned_list);
        usort($pinned_list, [Apps::class, 'app_name_compare']);
        $pinned_list = Apps::app_order(local_channel(), $pinned_list, 'nav_pinned_app');

        $syslist = [];
        $list = Apps::app_list(local_channel(), false, ['nav_featured_app']);

        if ($list) {
            $syslist = array_map([Apps::class, 'app_encode'], $list);
        }

        Apps::translate_system_apps($syslist);
    } else {
        $syslist = Apps::get_system_apps(true);
    }

    usort($syslist, [Apps::class, 'app_name_compare']);
    $syslist = Apps::app_order(local_channel(), $syslist, 'nav_featured_app');

    if ($pinned_list) {
        foreach ($pinned_list as $app) {
            if (isset(App::$nav_sel['name']) && App::$nav_sel['name'] == $app['name']) {
                $app['active'] = true;
            }
            if ($is_owner || strpos($app['requires'], 'local_channel') === false) {
                $navbar_apps[] = Apps::app_render($app, 'navbar');
            }
        }
    }

    if ($syslist) {
        foreach ($syslist as $app) {
            if (isset(App::$nav_sel['name']) && App::$nav_sel['name'] == $app['name']) {
                $app['active'] = true;
            }
            if ($is_owner || (!isset($app['requires']) || strpos($app['requires'], 'local_channel') === false)) {
                $nav_apps[] = Apps::app_render($app, 'nav');
            }
        }
    }

    $channel = App::get_channel();
    $notifications = [];

    if (local_channel()) {
        $notifications = [
            [
                'type' => 'network',
                'icon' => 'grid-3x3',
                'severity' => 'secondary',
                'label' => t('Network'),
                'title' => t('New network activity notifications'),
                'viewall' => [
                    'url' => 'network',
                    'label' => t('Network stream')
                ],
                'markall' => [
                    'label' => t('Mark all notifications read')
                ],
                'filter' => [
                    'posts_label' => t('Show new posts only'),
                    'name_label' => t('Filter by name or address')
                ]
            ],
            [
                'type' => 'home',
                'icon' => 'house',
                'severity' => 'danger',
                'label' => t('Home'),
                'title' => t('New home activity notifications'),
                'viewall' => [
                    'url' => 'channel/' . $channel['channel_address'],
                    'label' => t('Home stream')
                ],
                'markall' => [
                    'label' => t('Mark all notifications seen')
                ],
                'filter' => [
                    'posts_label' => t('Show new posts only'),
                    'name_label' => t('Filter by name or address')
                ]
            ],
            [
                'type' => 'dm',
                'icon' => 'envelope',
                'severity' => 'danger',
                'label' => t('Direct Messages'),
                'title' => t('New direct messages notifications'),
                'viewall' => [
                    'url' => 'network/?dm=1',
                    'label' => t('Direct messages stream')
                ],
                'markall' => [
                    'label' => t('Mark all notifications read')
                ],
                'filter' => [
                    'posts_label' => t('Show new posts only'),
                    'name_label' => t('Filter by name or address')
                ]
            ],
            [
                'type' => 'all_events',
                'icon' => 'calendar-date',
                'severity' => 'secondary',
                'label' => t('Events'),
                'title' => t('New events notifications'),
                'viewall' => [
                    'url' => 'cdav/calendar',
                    'label' => t('View events')
                ],
                'markall' => [
                    'label' => t('Mark all events seen')
                ]
            ],
            [
                'type' => 'intros',
                'icon' => 'people',
                'severity' => 'danger',
                'label' => t('New Connections'),
                'title' => t('New connections notifications'),
                'viewall' => [
                    'url' => 'connections',
                    'label' => t('View all connections')
                ]
            ],
            [
                'type' => 'files',
                'icon' => 'folder',
                'severity' => 'danger',
                'label' => t('Files'),
                'title' => t('New files notifications'),
            ],
            [
                'type' => 'notify',
                'icon' => 'exclamation-circle',
                'severity' => 'danger',
                'label' => t('Notices'),
                'title' => t('Notices'),
                'viewall' => [
                    'url' => 'notifications/system',
                    'label' => t('View all notices')
                ],
                'markall' => [
                    'label' => t('Mark all notices seen')
                ]
            ],
            [
                'type' => 'forums',
                'icon' => 'chat-quote',
                'severity' => 'secondary',
                'label' => t('Forums'),
                'title' => t('Forums'),
                'filter' => [
                    'name_label' => t('Filter by name or address')
                ]
            ]
        ];
    }

    if (local_channel() && is_site_admin()) {
        $notifications[] = [
            'type' => 'register',
            'icon' => 'person-exclamation',
            'severity' => 'danger',
            'label' => t('Registrations'),
            'title' => t('New registrations notifications'),
        ];
    }

    if (can_view_public_stream()) {
        $notifications[] = [
            'type' => 'pubs',
            'icon' => 'globe',
            'severity' => 'secondary',
            'label' => t('Public Stream'),
            'title' => t('New public stream notifications'),
            'viewall' => [
                'url' => 'pubstream',
                Packing list for public stream')
            ],
            'filter' => [
                'posts_label' => t('Show new posts only'),
                'name_label' => t('Filter by name or address')
            ]
        ];
    }

    $template = purify_filename($template);
    $c = theme_include("navbar_{$template}.css");
    $tpl = get_markup_template("navbar_{$template}.tpl") ?: get_markup_template('topnav.tpl');

    if ($c) {
        head_add_css("navbar_{$template}.css");
    }

    App::$page['topnav'] .= replace_macros($tpl, [
        '$baseurl' => z_root(),
        '$color_mode' => App::$page['color_mode'] ?? '',
        '$navbar_color_mode' => App::$page['navbar_color_mode'] ?? '',
        '$theme_switch_icon' => $theme_switch_icon,
        '$fulldocs' => t('Help'),
        '$sitelocation' => $sitelocation,
        '$nav' => $x['nav'],
        '$banner' => $banner,
        '$emptynotifications' => t('Loading'),
        '$userinfo' => $x['usermenu'],
        '$localuser' => local_channel(),
        '$is_owner' => $is_owner,
        '$sel' => App::$nav_sel,
        '$help' => t('@name, #tag, ?doc, content'),
        '$pleasewait' => t('Please wait...'),
        '$nav_apps' => $nav_apps,
        '$navbar_apps' => $navbar_apps,
        '$channel_menu' => get_pconfig(App::$profile_uid, 'system', 'channel_menu', Config::Get('system', 'channel_menu')),
        '$channel_thumb' => App::$profile['thumb'] ?? '',
        '$channel_apps' => $channel_apps,
        '$addapps' => t('Apps'),
        '$channelapps' => t('Channel Apps'),
        '$sysapps' => t('System Apps'),
        '$pinned_apps' => t('Pinned Apps'),
        '$featured_apps' => t('Featured Apps'),
        '$url' => $url ?: z_root() . '/'itorioApp::$cmd,
        '$settings_url' => $settings_url,
        '$name' => !$is_owner && isset(App::$profile['fullname']) ? App::$profile['fullname'] : '',
        '$thumb' => !$is_owner && isset(App::$profile['thumb']) ? App::$profile['thumb'] : '',
        '$form_security_token' => get_form_security_token('pconfig'),
        '$notifications' => $notifications,
        '$no_notifications' => t('No Notifications'),
        '$loading' => t('Loading'),
        '$sys_only' => empty($arr['sys_only']) ? 0 : 1
    ]);

    if (isset($_SESSION['reload_avatar']) && $observer) {
        $tpl = get_markup_template('force_image_reload.tpl');
        App::$page['topnav'] .= replace_macros($tpl, [
            '$imgUrl' => $observer['xchan_photo_m']
        ]);
        unset($_SESSION['reload_avatar']);
    }

    return App::$page['topnav'];
}
