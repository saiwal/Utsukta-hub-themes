<?php
namespace Theme\Solidified\Api;

class Response {

    public static function send(mixed $data, array $meta = [], int $status = 200): never {
        http_response_code($status);
        header('Content-Type: application/json');
        $envelope = ['data' => $data];
        if ($meta) $envelope['meta'] = $meta;
        echo json_encode($envelope);
        exit;
    }

    public static function paginate(array $items, int $offset, int $limit, int $rootCount): never {
        self::send($items, [
            'offset'   => $offset,
            'limit'    => $limit,
            'count'    => count($items),
            'has_more' => $rootCount >= $limit,
        ]);
    }

    public static function error(int $status, string $message): never {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode(['error' => ['status' => $status, 'message' => $message]]);
        exit;
    }
}
