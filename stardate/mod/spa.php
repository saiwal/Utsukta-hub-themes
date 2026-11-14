<?php
namespace Zotlabs\Module;

// Router/Handlers now live in the utsukta/spa-core Composer package (installed
// via a path repository — see composer.json), not this theme's own tree.
require_once __DIR__ . '/../vendor/autoload.php';

use Utsukta\SpaCore\Api\Router;

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
