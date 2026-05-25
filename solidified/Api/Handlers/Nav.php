<?php
namespace Theme\Solidified\Api\Handlers;

use App;
use Theme\Solidified\Api\Response;

require_once 'include/security.php';
require_once 'include/conversation.php';

class Nav
{
    public function get(): void
    {
        $observer = App::get_observer();
        $ob_hash = $observer ? $observer['xchan_hash'] : '';
        $is_local = (bool) local_channel();
        $uid = local_channel() ?: 0;
        $channel = $is_local ? App::get_channel() : null;

        $is_remote = (!$is_local && $ob_hash !== '');

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

        $actions = [];

        $my_url = get_my_url();
        if (!$my_url)
            $my_url = $observer ? ($observer['xchan_url'] ?? '') : '';

        $homelink_arr = parse_url($my_url);
        $homelink = ($homelink_arr['scheme'] ?? '') . '://' . ($homelink_arr['host'] ?? '');

        if ($is_local) {
            $nick = $channel['channel_address'] ?? '';
            $actions['profile'] = z_root() . '/profile/' . $nick;
            $actions['profiles'] = z_root() . '/profiles';
            $actions['settings'] = z_root() . '/settings';
            $actions['manage'] = z_root() . '/manage';
            $actions['navhome'] = $homelink;
            $actions['logout'] = z_root() . '/logout';
        } elseif ($is_remote) {
            $actions['navhome'] = $homelink;
            $actions['logout'] = z_root() . '/logout';
        } else {
            $actions['login'] = z_root() . '/login';
            $actions['remote_login'] = z_root() . '/rmagic';
            $reg = \Zotlabs\Lib\Config::Get('system', 'register_policy');
            if ($reg == REGISTER_OPEN || $reg == REGISTER_APPROVE)
                $actions['register'] = z_root() . '/register';
        }

        $baseurl = z_root();
        $pinned = [];

        if ($is_local) {
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
            $system = \Zotlabs\Lib\Apps::get_system_apps(true);
            \Zotlabs\Lib\Apps::translate_system_apps($system);

            $public_names = ['Directory', 'Help'];
            if (can_view_public_stream())
                $public_names[] = 'Public Stream';

            foreach ($system as $app) {
                if (in_array($app['name'] ?? '', $public_names, true))
                    $pinned[] = $app;
            }

            usort($pinned, function ($a, $b) use ($public_names) {
                $ia = array_search($a['name'] ?? '', $public_names);
                $ib = array_search($b['name'] ?? '', $public_names);
                return $ia - $ib;
            });
        }

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

        $app_shape = function (array $app) use ($baseurl): array {
            $url = $app['app_url'] ?? ($app['url'] ?? '');
            $url = str_replace('$baseurl', $baseurl, $url);
            // Preserve the full comma-separated value so the SPA can extract
            // both the primary URL and the optional settings URL.
            return [
                'name'     => $app['name'] ?? '',
                'label'    => $app['label'] ?? ($app['name'] ?? ''),
                'url'      => $url,
                'photo'    => $app['photo'] ?? '',
                'requires' => $app['requires'] ?? '',
            ];
        };

        $pinned = array_map($app_shape, $pinned);
        $featured = array_map($app_shape, $featured);

        $channel_tabs = [];
        $subject_nick = trim($_GET['channel_nick'] ?? '');

        if ($subject_nick !== '') {
            $subject = channelx_by_nick($subject_nick);

            if ($subject && !($subject['channel_removed'] ?? false)) {
                $puid = intval($subject['channel_id']);
                $p = get_all_perms($puid, $ob_hash);

                $channel_tabs[] = [
                    'id' => 'stream',
                    'label' => t('Channel'),
                    'url' => z_root() . '/channel/' . $subject_nick,
                    'icon' => 'home',
                ];

                if (!empty($p['view_stream']) &&
                    \Zotlabs\Lib\Apps::system_app_installed($puid, 'Articles'))
                    $channel_tabs[] = [
                        'id' => 'articles-tab',
                        'label' => t('Articles'),
                        'url' => z_root() . '/articles/' . $subject_nick,
                        'icon' => 'articles',
                    ];

                if (!empty($p['view_storage'])) {
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

                if (!empty($p['view_stream'])) {
                    $channel_tabs[] = [
                        'id' => 'calendar',
                        'label' => t('Calendar'),
                        'url' => z_root() . '/cal/' . $subject_nick,
                        'icon' => 'calendar',
                    ];
                }

                if (!empty($p['chat']) &&
                    \Zotlabs\Lib\Apps::system_app_installed($puid, 'Chatrooms') &&
                    \Zotlabs\Lib\Chatroom::list_count($puid))
                    $channel_tabs[] = [
                        'id' => 'chat',
                        'label' => t('Chatrooms'),
                        'url' => z_root() . '/chat/' . $subject_nick,
                        'icon' => 'chat',
                    ];

                if (\Zotlabs\Lib\Apps::system_app_installed($puid, 'Webpages'))
                    $channel_tabs[] = [
                        'id' => 'webpages',
                        'label' => t('Webpages'),
                        'url' => z_root() . '/page/' . $subject_nick . '/home',
                        'icon' => 'webpages',
                    ];

                if (\Zotlabs\Lib\Apps::system_app_installed($puid, 'Wiki'))
                    $channel_tabs[] = [
                        'id' => 'wiki',
                        'label' => t('Wiki'),
                        'url' => z_root() . '/wiki/' . $subject_nick . '/home',
                        'icon' => 'wiki',
                    ];
            }
        }

        // Names of all apps the local user has installed (empty for visitors/anon)
        $installed_apps = [];
        if ($is_local) {
            $all = \Zotlabs\Lib\Apps::app_list($uid, false) ?: [];
            foreach ($all as $app) {
                $enc = \Zotlabs\Lib\Apps::app_encode($app);
                if (!empty($enc['name']))
                    $installed_apps[] = $enc['name'];
            }
        }

        Response::send([
            'viewer' => $viewer,
            'actions' => $actions,
            'pinned' => $pinned,
            'featured' => $featured,
            'channel_tabs' => $channel_tabs,
            'has_public_stream' => (bool) can_view_public_stream(),
            'installed_apps' => $installed_apps,
        ]);
    }
}
