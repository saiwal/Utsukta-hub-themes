<?php
namespace Theme\Solidified\Api;

use Theme\Solidified\Api\Handlers\Csrf;

class Auth
{
    public static array $parsedBody = [];

    public static function requireLocal(): int
    {
        $uid = local_channel();
        if (!$uid)
            Response::error(401, 'Authentication required');
        return $uid;
    }

    // For GET requests — auth only
    public static function requireLocalGet(): int
    {
        return self::requireLocal();
    }

    // For POST/DELETE — auth + content-type + CSRF token
    public static function requireLocalJson(): int
    {
        self::requireJson();
        Csrf::validate();
        self::$parsedBody = json_decode(file_get_contents('php://input'), true) ?? [];
        return self::requireLocal();
    }

    private static function requireJson(): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'GET')
            return;
        $ct = $_SERVER['CONTENT_TYPE'] ?? '';
        if (!str_contains($ct, 'application/json')) {
            Response::error(400, 'Content-Type must be application/json');
        }
    }
}
