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

    // For POST/DELETE — auth + CSRF token + body parsing (JSON or form-data)
    public static function requireLocalJson(): int
    {
        Csrf::validate();
        $ct = $_SERVER['CONTENT_TYPE'] ?? '';
        if (str_contains($ct, 'multipart/form-data') || str_contains($ct, 'application/x-www-form-urlencoded')) {
            // Form submission — PHP populates $_POST automatically.
            self::$parsedBody = $_POST;
        } else {
            // JSON body — try php://input, then the PSR-7 stream buffered by Hubzilla.
            $raw = file_get_contents('php://input');
            if (empty($raw) && \App::$request) {
                $stream = \App::$request->getBody();
                if ($stream->isSeekable()) {
                    $stream->rewind();
                }
                $raw = (string) $stream;
            }
            self::$parsedBody = json_decode($raw ?: '', true) ?? [];
        }
        return self::requireLocal();
    }

    // For multipart POST (file uploads) — auth + CSRF, no JSON content-type required
    public static function requireLocalMultipart(): int
    {
        Csrf::validate();
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
