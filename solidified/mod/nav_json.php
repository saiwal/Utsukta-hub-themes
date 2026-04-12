<?php
namespace Zotlabs\Module;

use App;

class Nav_api extends \Zotlabs\Web\Controller
{
    function get()
    {
        if (($_GET['format'] ?? '') !== 'json')
            return;

        require_once ('include/security.php');
        require_once ('include/menu.php');
        require_once ('include/conversation.php');

        $is_owner = (local_channel() &&
            (App::$profile_uid == local_channel() || App::$profile_uid == 0));

        $observer = App::get_observer();
        $channel = local_channel() ? App::get_channel() : null;
        $uid = local_channel() ?: 0;

        $baseurl = z_root();
        // ── Viewer identity ───────────────────────────────────────────────────────
        $viewer = [
            'is_local' => (bool) local_channel(),
            'is_owner' => $is_owner,
            'is_admin' => is_site_admin(),
            'nick' => $channel['channel_address'] ?? '',
            'name' => $observer['xchan_name'] ?? '',
            'avatar' => $observer['xchan_photo_m'] ?? '',
            'url' => $observer['xchan_url'] ?? '',
            'uid' => $uid,
            'baseurl' => z_root(),
        ];

        // ── Standard action links ─────────────────────────────────────────────────
        $actions = [];

        if (local_channel()) {
            $actions['logout'] = z_root() . '/logout';
            $actions['settings'] = z_root() . '/settings';
            $actions['manage'] = z_root() . '/manage';  // channel switcher
            $actions['profile'] = z_root() . '/profile/' . ($channel['channel_address'] ?? '');
            $actions['profiles'] = z_root() . '/profiles';
        } else {
            $actions['login'] = z_root() . '/login';
            $actions['remote_login'] = z_root() . '/rmagic';
            if (\Zotlabs\Lib\Config::Get('system', 'register_policy') == REGISTER_OPEN ||
                    \Zotlabs\Lib\Config::Get('system', 'register_policy') == REGISTER_APPROVE) {
                $actions['register'] = z_root() . '/register';
            }
        }

        // ── Apps: pinned & featured ───────────────────────────────────────────────
        $pinned = [];
        $featured = [];

        if ($is_owner) {
            // Keep system apps current (mirrors nav() logic exactly)
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

            $list = \Zotlabs\Lib\Apps::app_list($uid, false, ['nav_featured_app']);
            foreach (($list ?: []) as $li)
                $featured[] = \Zotlabs\Lib\Apps::app_encode($li);
            \Zotlabs\Lib\Apps::translate_system_apps($featured);
        } else {
            $featured = \Zotlabs\Lib\Apps::get_system_apps(true);
            // Strip local-channel-only apps for non-owners
            $featured = array_values(array_filter($featured, fn($a) =>
                !isset($a['requires']) || strpos($a['requires'], 'local_channel') === false));
        }

        usort($featured, 'Zotlabs\Lib\Apps::app_name_compare');
        $featured = \Zotlabs\Lib\Apps::app_order($uid, $featured, 'nav_featured_app');

        // Normalise app shape: keep only fields the frontend needs
        $app_shape = fn(array $app) => [
            'name' => $app['name'] ?? '',
            'label' => $app['label'] ?? ($app['name'] ?? ''),
            'url' => $app['url'] ?? ($app['app_url'] ?? ''),
            'icon' => $app['icon'] ?? '',
            'photo' => $app['photo'] ?? '',
            'requires' => $app['requires'] ?? '',
        ];

        $pinned = array_map($app_shape, $pinned);
        $featured = array_map($app_shape, $featured);

        // ── Channel tabs (only when viewing a channel page) ───────────────────────
        $channel_tabs = [];
        if (isset(App::$profile['channel_address'])) {
            $pnick = App::$profile['channel_address'];
            $puid = App::$profile['profile_uid'] ?? 0;
            $ob_hash = $observer ? $observer['xchan_hash'] : '';
            $p = get_all_perms($puid, $ob_hash);

            $channel_tabs[] = [
                'id' => 'stream',
                'label' => t('Posts'),
                'url' => z_root() . '/channel/' . $pnick,
                'icon' => 'house',
            ];
            if ($p['view_profile'])
                $channel_tabs[] = [
                    'id' => 'profile',
                    'label' => t('About'),
                    'url' => z_root() . '/profile/' . $pnick,
                    'icon' => 'person',
                ];
            if ($p['view_storage']) {
                $channel_tabs[] = [
                    'id' => 'photos',
                    'label' => t('Photos'),
                    'url' => z_root() . '/photos/' . $pnick,
                    'icon' => 'image',
                ];
                $channel_tabs[] = [
                    'id' => 'files',
                    'label' => t('Files'),
                    'url' => z_root() . '/cloud/' . $pnick,
                    'icon' => 'folder',
                ];
            }
            if ($p['view_stream'])
                $channel_tabs[] = [
                    'id' => 'calendar',
                    'label' => t('Calendar'),
                    'url' => z_root() . '/cal/' . $pnick,
                    'icon' => 'calendar',
                ];
            if ($p['chat'] &&
                \Zotlabs\Lib\Apps::system_app_installed($puid, 'Chatrooms') &&
                \Zotlabs\Lib\Chatroom::list_count($puid))
                $channel_tabs[] = [
                    'id' => 'chat',
                    'label' => t('Chatrooms'),
                    'url' => z_root() . '/chat/' . $pnick,
                    'icon' => 'chat',
                ];
        }

        // ── Public stream availability ────────────────────────────────────────────
        $has_public_stream = (bool) can_view_public_stream();

        json_return_and_die([
            'viewer' => $viewer,
            'actions' => $actions,
            'pinned' => $pinned,
            'featured' => $featured,
            'channel_tabs' => $channel_tabs,
            'has_public_stream' => $has_public_stream,
        ]);
    }
}
