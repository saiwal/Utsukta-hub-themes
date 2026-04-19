<?php
namespace Theme\Solidified\Api\Handlers;

use Theme\Solidified\Api\Concerns\FormatsItems;
use Theme\Solidified\Api\Auth;
use Theme\Solidified\Api\Response;

class Channel
{
    use FormatsItems;

    // GET /api/channel
    // GET /api/channel/:nick
    public function get(): void
    {
        require_once 'include/items.php';
        require_once 'include/conversation.php';
        require_once 'include/security.php';

        $uid = local_channel() ?: 0;
        $channel = $this->resolveChannel($uid);

        $observer_xchan = get_observer_hash();
        $itemspage = intval(get_pconfig($uid, 'system', 'itemspage') ?: 10);

        $arr = $_GET;

        // ── Param remapping ───────────────────────────────────────────────────
        if (isset($arr['dbegin']))
            $arr['datequery2'] = $arr['dbegin'];
        if (isset($arr['dend']))
            $arr['datequery'] = $arr['dend'];

        $arr['start'] = max(0, intval($arr['start'] ?? 0));
        $arr['records'] = $itemspage;
        $arr['wall'] = 1;  // channel stream = wall posts only

        $client_mode = CLIENT_MODE_NORMAL;
        if (!empty($arr['nouveau'])) {
            $client_mode = CLIENT_MODE_LOAD;
        }

        // ── Fetch ─────────────────────────────────────────────────────────────
        $items = items_fetch($arr, $channel, $observer_xchan, $client_mode, 'channel');

        if (isset($items['success']) && $items['success'] === false) {
            Response::error(400, $items['message'] ?? 'Fetch failed');
        }

        // Filter Add/Remove federation noise
        $items = array_values(array_filter(
            $items ?: [],
            fn($item) => !in_array($item['verb'], ['Add', 'Remove'], true)
        ));

        if (!$items) {
            Response::send([], [
                'offset' => $arr['start'],
                'limit' => $itemspage,
                'count' => 0,
                'has_more' => false,
                'nouveau' => !empty($arr['nouveau']),
                'ordering' => $arr['order'] ?? 'created',
            ]);
            return;
        }

        $out = array_map(
            fn($item) => $this->formatItem($item, $observer_xchan),
            $items
        );

        Response::send($out, [
            'offset' => $arr['start'],
            'limit' => $itemspage,
            'count' => count($out),
            'has_more' => count($out) >= $itemspage,
            'nouveau' => !empty($arr['nouveau']),
            'ordering' => $arr['order'] ?? 'created',
        ]);
    }

    // Resolves the channel to fetch from:
    // /api/channel        → logged-in user's own channel
    // /api/channel/:nick  → channel identified by nick (permission checked)
    private function resolveChannel(int $uid): array
    {
	        $nick = \App::$argv[2] ?? null;

        if ($nick) {
            $channel = channelx_by_nick($nick);
            if (!$channel || $channel['channel_removed']) {
                Response::error(404, 'Channel not found');
            }

            // Permission check — observer must be able to view stream
            $perms = get_all_perms($channel['channel_id'], get_observer_hash());
            if (!$perms['view_stream']) {
                Response::error(403, 'Permission denied');
            }

            return $channel;
        }

        // No nick — use the logged-in user's channel
        $channel = \App::get_channel();
        if (!$channel) {
            Response::error(500, 'Could not resolve channel');
        }

        return $channel;
    }
}
