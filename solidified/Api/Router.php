<?php
namespace Theme\Solidified\Api;

use Theme\Solidified\Api\Handlers;

class Router
{
    private static array $map = [
        'csrf' => Handlers\Csrf::class,
        'manage' => Handlers\Manage::class,
        'network' => Handlers\Network::class,
        /* 'connections' => Handlers\Connections::class, */
        /* 'photos' => Handlers\Photos::class, */
        /* 'notifications' => Handlers\Notifications::class, */
        /* 'post' => Handlers\Post::class, */
    ];

    public static function dispatch(string $method): void
    {
        // URL: /api/settings       → argv: [0]=api  [1]=settings
        // URL: /api/connections/42 → argv: [0]=api  [1]=connections  [2]=42
        $resource = \App::$argv[1] ?? null;

        if (!$resource) {
            Response::error(400, 'No resource specified');
        }

        if (!isset(self::$map[$resource])) {
            Response::error(404, "Unknown endpoint: {$resource}");
        }

        $handlerClass = self::$map[$resource];
        $handler = new $handlerClass();

        if (!method_exists($handler, $method)) {
            Response::error(405, 'Method not allowed');
        }

        $handler->$method();
    }
}
