<?php
namespace Zotlabs\Module;

// Bootstrap autoloader before any Theme\Solidified\* class is referenced
spl_autoload_register(function (string $class) {
    // Matches Theme\Solidified\Api\Router → Api/Router.php
    // Matches Theme\Solidified\Api\Handlers\Settings → Api/Handlers/Settings.php
    if (!str_starts_with($class, 'Theme\\Solidified\\')) return;

    $base = __DIR__ . '/../';   // solidified/ root, one level up from mod/
    $rel  = str_replace(['Theme\\Solidified\\', '\\'], ['', '/'], $class);
    $file = $base . $rel . '.php';

    if (file_exists($file)) require_once $file;
});

use Theme\Solidified\Api\Router;

class Spa extends \Zotlabs\Web\Controller {

    function init() {
        header('Content-Type: application/json');
    }

    function get() {
        // Hubzilla only dispatches post() for POST; everything else hits get().
        // Detect the real HTTP method so DELETE/PUT/PATCH reach their handlers.
        $method = strtolower($_SERVER['REQUEST_METHOD'] ?? 'get');
        Router::dispatch(in_array($method, ['get', 'delete', 'put', 'patch']) ? $method : 'get');
    }

    function post() {
        Router::dispatch('post');
    }

    // Note: Hubzilla never calls delete()/put() on controllers — real HTTP method
    // dispatch is handled above in get() via $_SERVER['REQUEST_METHOD'].
}
