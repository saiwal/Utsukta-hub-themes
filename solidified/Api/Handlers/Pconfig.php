<?php
namespace Theme\Solidified\Api\Handlers;

use Theme\Solidified\Api\Response;

class Pconfig
{
    private static function channelSpa(string $nick): ?array
    {
        $ch = channelx_by_nick($nick);
        if (!$ch || !empty($ch['channel_removed'])) return null;

        $cuid = intval($ch['channel_id']);

        $valid_fits    = ['tile', 'cover'];
        $valid_sizes   = ['small', 'medium', 'large', 'xl'];
        $valid_radii   = ['none', 'sm', 'default', 'lg', 'xl'];
        $valid_families = [
            'system','serif','monospace','nunito','saira','share-tech',
            'playfair','libre-baskerville','comfortaa','space-mono','iosevka',
            'righteous','playwrite-england','comic','opendyslexic',
        ];
        $valid_schemes = [
            'light','pastel-soft','warm-paper','mint','sakura','latte-cream',
            'dark','nord','dracula','monokai','one-dark','cyberpunk','rose-pine',
            'gruvbox-dark','gruvbox-light','catppuccin-latte','catppuccin-mocha',
            'solarized-light','solarized-dark','tokyo-night','matrix','custom',
        ];

        $bg_fit        = get_pconfig($cuid, 'spa', 'bg_fit',        'cover');
        $font_size     = get_pconfig($cuid, 'spa', 'font_size',     'medium');
        $font_family   = get_pconfig($cuid, 'spa', 'font_family',   'system');
        $color_scheme  = get_pconfig($cuid, 'spa', 'color_scheme',  '');
        $corner_radius = get_pconfig($cuid, 'spa', 'corner_radius', 'default');

        $result = [
            'bg_url'        => (string) get_pconfig($cuid, 'spa', 'bg_url', ''),
            'bg_fit'        => in_array($bg_fit,        $valid_fits,     true) ? $bg_fit        : 'cover',
            'font_size'     => in_array($font_size,     $valid_sizes,    true) ? $font_size     : 'medium',
            'font_family'   => in_array($font_family,   $valid_families, true) ? $font_family   : 'system',
            'color_scheme'  => in_array($color_scheme,  $valid_schemes,  true) ? $color_scheme  : '',
            'corner_radius' => in_array($corner_radius, $valid_radii,    true) ? $corner_radius : 'default',
        ];

        if ($result['color_scheme'] === 'custom') {
            $stored = get_pconfig($cuid, 'spa', 'custom_theme_colors', '');
            if ($stored) $result['custom_theme_colors'] = $stored;
        }

        // The owner's widget arrangement also applies to visitors of their pages
        $widget_layout = get_pconfig($cuid, 'spa', 'widget_layout', '');
        if ($widget_layout) $result['widget_layout'] = (string) $widget_layout;

        return $result;
    }

    public function get(): void
    {
        $channel_param = isset($_GET['channel']) ? notags(trim($_GET['channel'])) : '';

        if (local_channel()) {
            $uid     = local_channel();
            $channel = \App::get_channel();
            $nick    = $channel['channel_address'] ?? '';

            $rows = q('SELECT cat, k, v FROM pconfig WHERE uid = %d', intval($uid));
            $config = [];
            foreach (($rows ?: []) as $row) {
                $config[$row['cat']][$row['k']] = $row['v'];
            }

            $response = [
                'uid'      => $uid,
                'channel'  => $nick,
                'is_admin' => is_site_admin(),
                'system'   => $config['system']  ?? [],
                'spa'      => $config['spa']     ?? [],
                'features' => array_map('boolval', $config['feature'] ?? []),
            ];

            // Include the visited channel's display prefs so the SPA can theme per-channel
            if ($channel_param !== '') {
                $page_spa = self::channelSpa($channel_param);
                if ($page_spa !== null) {
                    $response['page_spa'] = $page_spa;
                }
            }

            Response::send($response);
        }

        // Remote-authenticated visitor
        $observer = \App::get_observer();
        if ($observer && !empty($observer['xchan_hash'])) {
            $base = [
                'uid'       => 0,
                'channel'   => '',
                'is_remote' => true,
            ];
            if ($channel_param !== '') {
                $page_spa = self::channelSpa($channel_param);
                if ($page_spa !== null) $base['spa'] = $page_spa;
            }
            Response::send($base);
        }

        // Anonymous visitor on a channel page — expose only public spa display prefs
        if ($channel_param !== '') {
            $page_spa = self::channelSpa($channel_param);
            if ($page_spa !== null) {
                Response::send([
                    'uid'     => 0,
                    'channel' => $channel_param,
                    'spa'     => $page_spa,
                ]);
            }
        }

        // Unauthenticated, no channel context
        Response::send([
            'uid'     => 0,
            'channel' => '',
        ]);
    }
}
