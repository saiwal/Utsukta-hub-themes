<?php
namespace Theme\Solidified\Api\Handlers;

use Theme\Solidified\Api\Auth;
use Theme\Solidified\Api\Response;
use Zotlabs\Lib\Config;

/**
 * Site-wide announcements for the SPA's Site Announcements widget.
 *
 * GET  /api/announcements          → public, most recent MAX_SHOWN first
 * POST /api/announcements          → admin only: { action: 'create', title, body }
 *                                                { action: 'delete', id }
 *
 * Stored as a JSON array under system config 'spa_announcements', capped at
 * MAX_STORED entries (oldest dropped). No moderation queue, no federation —
 * purely local site notices.
 */
class Announcements
{
    private const MAX_STORED = 50;
    private const MAX_SHOWN = 10;
    private const MAX_TITLE = 120;
    private const MAX_BODY = 1000;

    public function get(): void
    {
        $list = self::load();
        $shown = array_slice($list, 0, self::MAX_SHOWN);
        Response::send($shown);
    }

    public function post(): void
    {
        Auth::requireLocalJson();
        if (!local_channel() || !is_site_admin()) {
            Response::error(403, 'Permission denied');
        }

        $data = Auth::$parsedBody;
        $action = $data['action'] ?? '';

        if ($action === 'create') {
            $title = trim((string) ($data['title'] ?? ''));
            $body = trim((string) ($data['body'] ?? ''));
            if (!$title && !$body) {
                Response::error(400, 'title or body required');
            }

            $list = self::load();
            array_unshift($list, [
                'id' => uniqid('', true),
                'title' => notags(mb_substr($title, 0, self::MAX_TITLE)),
                'body' => notags(mb_substr($body, 0, self::MAX_BODY)),
                'created' => gmdate('c'),
            ]);
            $list = array_slice($list, 0, self::MAX_STORED);
            self::save($list);

            Response::send(array_slice($list, 0, self::MAX_SHOWN));
            return;
        }

        if ($action === 'delete') {
            $id = (string) ($data['id'] ?? '');
            if (!$id) {
                Response::error(400, 'id required');
            }
            $list = array_values(array_filter(self::load(), fn($a) => $a['id'] !== $id));
            self::save($list);
            Response::send(array_slice($list, 0, self::MAX_SHOWN));
            return;
        }

        Response::error(400, "Unknown action: {$action}");
    }

    private static function load(): array
    {
        $raw = Config::Get('system', 'spa_announcements');
        if (!$raw || !is_string($raw)) return [];
        $list = json_decode($raw, true);
        return is_array($list) ? $list : [];
    }

    private static function save(array $list): void
    {
        Config::Set('system', 'spa_announcements', json_encode($list));
    }
}
