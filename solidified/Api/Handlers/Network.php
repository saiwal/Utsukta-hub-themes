<?php
namespace Theme\Solidified\Api\Handlers;

use Theme\Solidified\Api\Auth;
use Theme\Solidified\Api\Response;

class Network {

    public function get(): void {
        require_once 'include/items.php';
        require_once 'include/conversation.php';
        require_once 'include/security.php';

        $uid     = Auth::requireLocal();
        $channel = \App::get_channel();

        $itemspage = intval(get_pconfig($uid, 'system', 'itemspage') ?: 10);

        // ── Param remapping ───────────────────────────────────────────────────
        $arr = $_GET;

        if (isset($arr['dbegin'])) $arr['datequery2'] = $arr['dbegin'];
        if (isset($arr['dend']))   $arr['datequery']  = $arr['dend'];

        $arr['start']   = max(0, intval($arr['start'] ?? 0));
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
                'offset'   => $arr['start'],
                'limit'    => $itemspage,
                'count'    => 0,
                'has_more' => false,
                'nouveau'  => !empty($arr['nouveau']),
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
            'offset'   => $arr['start'],
            'limit'    => $itemspage,
            'count'    => count($out),
            'has_more' => count($out) >= $itemspage,
            'nouveau'  => !empty($arr['nouveau']),
            'ordering' => $arr['order'] ?? 'created',
        ]);
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function formatItem(array $item, string $observer_xchan): array {
        $liked = $disliked = $repeated = false;

        if ($observer_xchan && !empty($item['reaction_verbs'])) {
            foreach (explode('|', $item['reaction_verbs']) as $rv) {
                if (!str_contains($rv, ':')) continue;
                [$verb, $xchan] = explode(':', $rv, 2);
                if ($xchan !== $observer_xchan) continue;
                if ($verb === 'Like')     $liked    = true;
                if ($verb === 'Dislike')  $disliked = true;
                if ($verb === 'Announce') $repeated = true;
            }
        }

        return [
            'uuid'            => $item['uuid'],
            'mid'             => $item['mid'],
            'parent_mid'      => $item['parent_mid'],
            'thr_parent'      => $item['thr_parent'],
            'message_top'     => intval($item['item_thread_top'])
                                    ? $item['mid']
                                    : $item['thr_parent'],
            'created'         => $item['created'],
            'edited'          => $item['edited'],
            'commented'       => $item['commented'],
            'title'           => $item['title'],
            'body'            => $item['body'],
            'verb'            => $item['verb'],
            'obj_type'        => $item['obj_type'],
            'like_count'      => intval($item['like_count']     ?? 0),
            'dislike_count'   => intval($item['dislike_count']  ?? 0),
            'announce_count'  => intval($item['announce_count'] ?? 0),
            'comment_count'   => intval($item['comment_count']  ?? 0),
            'item_private'    => intval($item['item_private']),
            'item_thread_top' => intval($item['item_thread_top']),
            'item_unseen'     => intval($item['item_unseen']    ?? 0),
            'iid'             => intval($item['id']),
            'profile_uid'     => intval($item['uid']),
            'flags'           => array_values(array_filter([
                intval($item['item_thread_top']) ? 'thread_parent' : null,
                intval($item['item_private'])    ? 'private'       : null,
                intval($item['item_starred'])    ? 'starred'       : null,
                intval($item['item_notshown'])   ? 'notshown'      : null,
                intval($item['item_unseen'])     ? 'unseen'        : null,
            ])),
            'author'          => [
                'name'    => $item['author']['xchan_name']           ?? '',
                'address' => $item['author']['xchan_addr']           ?? '',
                'url'     => $item['author']['xchan_url']            ?? '',
                'photo'   => [
                    'src'      => $item['author']['xchan_photo_m']        ?? '',
                    'mimetype' => $item['author']['xchan_photo_mimetype'] ?? '',
                ],
            ],
            'permalink'       => $item['plink']     ?? '',
            'viewer_liked'    => $liked,
            'viewer_disliked' => $disliked,
            'viewer_repeated' => $repeated,
        ];
    }
}
