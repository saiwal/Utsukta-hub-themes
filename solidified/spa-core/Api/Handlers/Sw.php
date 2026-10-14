<?php
namespace Utsukta\SpaCore\Api\Handlers;

class Sw
{
    public function get(): void
    {
        // <theme>/spa-core/Api/Handlers → <theme>/assets. Relative to this
        // file, not PROJECT_BASE/view/theme/<slug>: that path needs the
        // view/theme symlink and a hardcoded slug, and a hub without either
        // 404'd here, so the SW never installed.
        $path = dirname(__DIR__, 3) . '/assets/sw.js';

        if (!file_exists($path)) {
            http_response_code(404);
            exit;
        }

        header('Content-Type: application/javascript; charset=utf-8');
        // Grants the SW scope over the full origin despite being at /spa/sw
        header('Service-Worker-Allowed: /');
        header('Cache-Control: no-store');
        header('X-Content-Type-Options: nosniff');
        readfile($path);
        exit;
    }
}
