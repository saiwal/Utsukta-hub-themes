<?php
namespace Theme\Solidified\Api\Handlers;

class Manifest
{
    public function get(): void
    {
        header('Content-Type: application/manifest+json');
        header('Cache-Control: max-age=86400');

        echo json_encode([
            'name'             => 'Solidified',
            'short_name'       => 'Solidified',
            'description'      => 'Hubzilla — Solidified frontend',
            'theme_color'      => '#1e293b',
            'background_color' => '#0f172a',
            'display'          => 'standalone',
            'start_url'        => '/hq',
            'scope'            => '/',
            'orientation'      => 'portrait-primary',
            'icons'            => [
                [
                    'src'   => '/view/theme/solidified/assets/icon-192.png',
                    'sizes' => '192x192',
                    'type'  => 'image/png',
                ],
                [
                    'src'     => '/view/theme/solidified/assets/icon-512.png',
                    'sizes'   => '512x512',
                    'type'    => 'image/png',
                    'purpose' => 'maskable any',
                ],
            ],
            'shortcuts' => [
                ['name' => 'HQ',      'url' => '/hq',      'description' => 'Your home stream'],
                ['name' => 'Network', 'url' => '/network', 'description' => 'Network stream'],
            ],
        ], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        exit;
    }
}
