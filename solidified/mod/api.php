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

class Api extends \Zotlabs\Web\Controller {

    function init() {
        header('Content-Type: application/json');
    }

    function get() {
        Router::dispatch('get');
    }

    function post() {
        Router::dispatch('post');
    }

    function delete() {
        Router::dispatch('delete');
    }
}
