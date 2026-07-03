<?php
namespace Theme\Solidified\Api;

class Response
{
    public static function send(mixed $data, array $meta = [], int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json');
        $envelope = ['data' => $data];
        if ($meta)
            $envelope['meta'] = $meta;
        $json = json_encode($envelope, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        if ($json === false)
            $json = json_encode(['error' => ['status' => 500, 'message' => 'JSON encode failed: ' . json_last_error_msg()]]);
        echo $json;
        exit;
    }

    public static function paginate(array $items, int $offset, int $limit, int $rootCount, bool $nouveau = false): never
    {
        self::send($items, [
            'offset'     => $offset,
            'limit'      => $limit,
            'count'      => count($items),
            'root_count' => $rootCount,
            'has_more'   => $rootCount >= $limit,
            'nouveau'    => $nouveau,
        ]);
    }

    public static function error(int $status, string $message): never
    {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode(['error' => ['status' => $status, 'message' => $message]]);
        exit;
    }
}
