<?php
namespace Utsukta\SpaCore\Api\Handlers;

use Utsukta\SpaCore\Api\Response;

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
            'inter','atkinson-hyperlegible','literata','jetbrains-mono',
        ];
        $valid_schemes = [
            'light','pastel-soft','warm-paper','mint','sakura','latte-cream',
            'dark','nord','dracula','monokai','one-dark','cyberpunk','rose-pine',
            'gruvbox-dark','gruvbox-light','catppuccin-latte','catppuccin-mocha',
            'solarized-light','solarized-dark','tokyo-night','matrix','custom',
        ];

        $bg_fit        = get_pconfig($cuid, 'spa', 'bg_fit',        'cover');
        $font_size     = get_pconfig($cuid, 'spa', 'font_size',     '');
        $font_family   = get_pconfig($cuid, 'spa', 'font_family',   '');
        $color_scheme  = get_pconfig($cuid, 'spa', 'color_scheme',  '');
        $corner_radius = get_pconfig($cuid, 'spa', 'corner_radius', '');

        $result = [
            'bg_url'        => (string) get_pconfig($cuid, 'spa', 'bg_url', ''),
            'bg_fit'        => in_array($bg_fit,        $valid_fits,     true) ? $bg_fit        : 'cover',
            'font_size'     => in_array($font_size,     $valid_sizes,    true) ? $font_size     : '',
            'font_family'   => in_array($font_family,   $valid_families, true) ? $font_family   : '',
            'color_scheme'  => in_array($color_scheme,  $valid_schemes,  true) ? $color_scheme  : '',
            'corner_radius' => in_array($corner_radius, $valid_radii,    true) ? $corner_radius : '',
        ];

        if ($result['color_scheme'] === 'custom') {
            $stored = get_pconfig($cuid, 'spa', 'custom_theme_colors', '');
            if ($stored) $result['custom_theme_colors'] = $stored;
        }

        // The owner's widget arrangement also applies to visitors of their pages
        $widget_layout = get_pconfig($cuid, 'spa', 'widget_layout', '');
        if ($widget_layout) $result['widget_layout'] = (string) $widget_layout;

        // Layout templates a page may be assigned to — visitors need these to
        // render the same widgets the owner assigned, not just the owner
        // themself.
        $widget_templates = get_pconfig($cuid, 'spa', 'widget_templates', '');
        if ($widget_templates) $result['widget_templates'] = (string) $widget_templates;

        return $result;
    }

    /**
     * Site and observer facts every branch returns. bbcode's [sitename],
     * [baseurl] and the [observer…] conditionals are resolved client-side, and
     * this boot request is the only place the SPA learns any of them.
     */
    private static function siteBlock(): array
    {
        $o = \App::get_observer();
        return [
            'sitename' => (string) \Zotlabs\Lib\Config::Get('system', 'sitename'),
            'baseurl'  => z_root(),
            'observer' => ($o && !empty($o['xchan_hash'])) ? [
                'xchan_url'     => (string) ($o['xchan_url'] ?? ''),
                'xchan_name'    => (string) ($o['xchan_name'] ?? ''),
                'xchan_addr'    => (string) ($o['xchan_addr'] ?? ''),
                'xchan_photo_l' => (string) ($o['xchan_photo_l'] ?? ''),
            ] : null,
        ];
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

            $nsfw_installed = \Zotlabs\Lib\Apps::system_app_installed($uid, 'NSFW');

            require_once 'include/features.php';
            $features = [];
            foreach (get_features(false) as $cat) {
                foreach ($cat as $item) {
                    if (is_array($item)) {
                        $features[$item[0]] = (bool) feature_enabled($uid, $item[0]);
                    }
                }
            }

            $response = [
                'uid'      => $uid,
                'channel'  => $nick,
                // zidify_links() equivalent on the client needs the observer's
                // webbie to reach ACL-restricted media on other hubs.
                'my_address' => (string) (get_my_address() ?: channel_reddress($channel)),
                'is_admin' => is_site_admin(),
                'system'   => $config['system']  ?? [],
                'spa'      => $config['spa']     ?? [],
                'features' => $features,
                'nsfw'     => [
                    'words' => $nsfw_installed ? (string) ($config['nsfw']['words'] ?? 'nsfw,contentwarning') : '',
                ],
            ];

            // Include the visited channel's display prefs so the SPA can theme per-channel
            if ($channel_param !== '') {
                $page_spa = self::channelSpa($channel_param);
                if ($page_spa !== null) {
                    $response['page_spa'] = $page_spa;
                }
            }

            Response::send($response + self::siteBlock());
        }

        // Remote-authenticated visitor
        $observer = \App::get_observer();
        if ($observer && !empty($observer['xchan_hash'])) {
            $base = [
                'uid'        => 0,
                'channel'    => '',
                'is_remote'  => true,
                'my_address' => (string) (get_my_address() ?: ''),
            ];
            if ($channel_param !== '') {
                $page_spa = self::channelSpa($channel_param);
                if ($page_spa !== null) $base['spa'] = $page_spa;
            }
            Response::send($base + self::siteBlock());
        }

        // Anonymous visitor on a channel page — expose only public spa display prefs
        if ($channel_param !== '') {
            $page_spa = self::channelSpa($channel_param);
            if ($page_spa !== null) {
                Response::send([
                    'uid'     => 0,
                    'channel' => $channel_param,
                    'spa'     => $page_spa,
                ] + self::siteBlock());
            }
        }

        // Unauthenticated, no channel context
        Response::send([
            'uid'     => 0,
            'channel' => '',
        ] + self::siteBlock());
    }
}
