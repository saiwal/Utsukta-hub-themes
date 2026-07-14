<?php
namespace Theme\Solidified\Api\Handlers;

class Manifest
{
    public function get(): void
    {
        header('Content-Type: application/manifest+json');
        header('Cache-Control: max-age=86400');
        $hub_name = \get_config('system', 'sitename') ?: 'Hubzilla';
        $logo_512 = \get_config('system', 'sitelogo_512');
        $logo_192 = \get_config('system', 'sitelogo_192');
        $icons = ($logo_512 && $logo_192)
            ? [
                [
                    'src' => $logo_192,
                    'sizes' => '192x192',
                    'type' => 'image/png',
                    'purpose' => 'any',
                ],
                [
                    'src' => $logo_512,
                    'sizes' => '512x512',
                    'type' => 'image/png',
                    'purpose' => 'any',
                ],
                [
                    'src' => $logo_512,
                    'sizes' => '512x512',
                    'type' => 'image/png',
                    'purpose' => 'maskable',
                ],
            ]
            : [
                [
                    'src' => '/view/theme/solidified/assets/pwa-192x192.png',
                    'sizes' => '192x192',
                    'type' => 'image/png',
                    'purpose' => 'any',
                ],
                [
                    'src' => '/view/theme/solidified/assets/pwa-512x512.png',
                    'sizes' => '512x512',
                    'type' => 'image/png',
                    'purpose' => 'any',
                ],
                [
                    'src' => '/view/theme/solidified/assets/maskable-icon-512x512.png',
                    'sizes' => '512x512',
                    'type' => 'image/png',
                    'purpose' => 'maskable',
                ],
            ];
        echo json_encode([
            'name' => $hub_name,
            'short_name' => $hub_name,
            'description' => 'Hubzilla — Solidified frontend',
            'theme_color' => '#1e293b',
            'background_color' => '#0f172a',
            'display' => 'standalone',
            'start_url' => '/hq',
            'scope' => '/',
            'orientation' => 'portrait-primary',
            'icons' => $icons,
            'shortcuts' => [
                ['name' => 'HQ', 'url' => '/hq', 'description' => 'Your home stream'],
                ['name' => 'Network', 'url' => '/network', 'description' => 'Network stream'],
            ],
        ], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        exit;
    }
}
