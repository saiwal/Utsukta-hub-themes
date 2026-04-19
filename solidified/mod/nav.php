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
                    'icon' => 'house',
                ];

                /* if ($p['view_profile']) */
                /*     $channel_tabs[] = [ */
                /*         'id' => 'profile', */
                /*         'label' => t('About'), */
                /*         'url' => z_root() . '/profile/' . $subject_nick, */
                /*         'icon' => 'person', */
                /*     ]; */

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
                        'icon' => 'layout-text-sidebar',
                    ];
                }
            }
        }

        // ── Response ──────────────────────────────────────────────────────────
        logger(print_r($viewer, true), LOGGER_DEBUG);
        logger(print_r($actions, true), LOGGER_DEBUG);
        json_return_and_die([
            'viewer' => $viewer,
            'actions' => $actions,
            'pinned' => $pinned,
            'featured' => $featured,
            'channel_tabs' => $channel_tabs,
            'has_public_stream' => (bool) can_view_public_stream(),
        ]);
    }
}
