<?php
namespace Theme\Solidified\Api\Handlers;

use Theme\Solidified\Api\Auth;
use Theme\Solidified\Api\Response;
use Theme\Solidified\Api\Concerns\FormatsItems;

class Network
{
    use FormatsItems;

    public function get(): void
    {
        require_once 'include/items.php';
        require_once 'include/conversation.php';
        require_once 'include/security.php';

        $uid = Auth::requireLocal();
        $channel = \App::get_channel();

        $itemspage = intval(get_pconfig($uid, 'system', 'itemspage') ?: 10);

        // ── Param remapping ───────────────────────────────────────────────────
        $arr = $_GET;

        if (isset($arr['dbegin']))
            $arr['datequery2'] = $arr['dbegin'];
        if (isset($arr['dend']))
            $arr['datequery'] = $arr['dend'];

        $arr['start'] = max(0, intval($arr['start'] ?? 0));
        $arr['records'] = $itemspage;

        // nouveau needs CLIENT_MODE_LOAD to trigger the unthreaded branch
        $client_mode = CLIENT_MODE_NORMAL;
        if (!empty($arr['nouveau'])) {
            $client_mode = CLIENT_MODE_LOAD;
        }

        // ── Fetch ─────────────────────────────────────────────────────────────
        $items = items_fetch($arr, $channel, get_observer_hash(), $client_mode, 'network');

        // items_fetch returns an array with 'success' => false on certain errors
        if (isset($items['success']) && $items['success'] === false) {
            Response::error(400, $items['message'] ?? 'Fetch failed');
        }

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

        // ── Format ────────────────────────────────────────────────────────────
        $observer_xchan = get_observer_hash();
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

}
