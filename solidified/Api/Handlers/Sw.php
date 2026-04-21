<?php
namespace Theme\Solidified\Api\Handlers;

class Sw
{
    public function get(): void
    {
        $path = PROJECT_BASE . '/view/theme/solidified/assets/sw.js';

        if (!file_exists($path)) {
            http_response_code(404);
            exit;
        }

        header('Content-Type: application/javascript; charset=utf-8');
        // Grants the SW scope over the full origin despite being at /api/sw
        header('Service-Worker-Allowed: /');
        header('Cache-Control: no-store');
        header('X-Content-Type-Options: nosniff');
        readfile($path);
        exit;
    }
}
