<?php
namespace Utsukta\SpaCore\Api\Handlers;

use Utsukta\SpaCore\Api\Auth;
use Utsukta\SpaCore\Api\Response;

class Csrf {

    public function get(): void {
        Response::send(['token' => self::generate()]);
    }

    public static function generate(): string {
        if (empty($_SESSION['solidified_csrf'])) {
            $_SESSION['solidified_csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['solidified_csrf'];
    }

    public static function validate(): void {
        $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        $expected = $_SESSION['solidified_csrf'] ?? '';

        if (!$expected || !hash_equals($expected, $token)) {
            Response::error(403, 'Invalid CSRF token');
        }
    }
}

