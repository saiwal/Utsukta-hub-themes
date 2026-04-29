<?php
namespace Theme\Solidified\Api\Handlers;

use Theme\Solidified\Api\Concerns\FormatsItems;
use Theme\Solidified\Api\Response;

class Channel
{
    use FormatsItems;

    public function get(): void
    {
        require_once 'include/items.php';
        require_once 'include/security.php';

        $uid         = local_channel() ?: 0;
        $channel     = $this->resolveChannel($uid);
        $channel_uid = intval($channel['channel_id']);

        $observer_xchan = get_observer_hash();
        $itemspage      = intval(get_pconfig($uid ?: $channel_uid, 'system', 'itemspage') ?: 10);

        // ── Pagination ────────────────────────────────────────────────────────
        $offset     = max(0, intval($_GET['start'] ?? 0));
        $pager_sql  = " LIMIT $itemspage OFFSET $offset ";

        // ── Ordering ──────────────────────────────────────────────────────────
        $get_order = $_GET['order'] ?? 'created';
        $nouveau   = false;

        switch ($get_order) {
            case 'commented': $ordering = 'commented'; break;
            case 'unthreaded': $nouveau = true; $ordering = 'created'; break;
            default:           $ordering = 'created';
        }

        // ── Filter params ─────────────────────────────────────────────────────
        $search   = $_GET['search']  ?? '';
        $hashtags = $_GET['tag']     ?? '';
        $category = $_GET['cat']     ?? '';
        $mid      = $_GET['mid']     ?? '';

        $datequery  = (isset($_GET['dend'])   && is_a_date_arg($_GET['dend']))
            ? notags($_GET['dend'])   : '';
        $datequery2 = (isset($_GET['dbegin']) && is_a_date_arg($_GET['dbegin']))
            ? notags($_GET['dbegin']) : '';

        if ($search || $hashtags || $category) {
            $nouveau = true;
        }

        // ── SQL scaffolding ───────────────────────────────────────────────────
        $item_normal     = item_normal();
        $uids            = ' AND item.uid = ' . $channel_uid . ' ';
        $item_thread_top = ' AND item_thread_top = 1 ';
        $sql_extra       = ' AND item.item_wall = 1 ';

        // Verb whitelist — exclude reactions and federation noise
        $sql_extra .= " AND item.verb IN ('Create', 'Update', 'EmojiReact') ";

        if ($category) {
            $sql_extra .= protect_sprintf(term_query('item', $category, TERM_CATEGORY));
        }
        if ($hashtags) {
            $sql_extra .= protect_sprintf(term_query('item', $hashtags, TERM_HASHTAG, TERM_COMMUNITYTAG));
        }
        if ($search) {
            $sql_extra .= sprintf(
                " AND (item.body LIKE '%s' OR item.title LIKE '%s') ",
                dbesc(protect_sprintf('%' . $search . '%')),
                dbesc(protect_sprintf('%' . $search . '%'))
            );
        }
        if ($mid) {
            $sql_extra .= " AND item.mid = '" . dbesc($mid) . "' ";
            $nouveau = true;
        }

        // Permission filter for non-owners
        $sql_extra .= item_permissions_sql($channel_uid, $observer_xchan);

        // Date range (threaded mode: parent query only)
        $sql_date  = '';
        if ($datequery) {
            $sql_date .= " AND item.created <= '"
                . dbesc(datetime_convert(date_default_timezone_get(), '', $datequery)) . "' ";
        }
        if ($datequery2) {
            $sql_date .= " AND item.created >= '"
                . dbesc(datetime_convert(date_default_timezone_get(), '', $datequery2)) . "' ";
        }
        $sql_extra3 = $nouveau ? '' : $sql_date;

        // ── Reaction subqueries ───────────────────────────────────────────────
        $reaction_subqueries = "
            (SELECT COUNT(*) FROM item r WHERE r.parent = item.parent AND r.thr_parent = item.mid AND r.verb = 'Like'    AND r.item_deleted = 0) AS like_count,
            (SELECT COUNT(*) FROM item r WHERE r.parent = item.parent AND r.thr_parent = item.mid AND r.verb = 'Dislike' AND r.item_deleted = 0) AS dislike_count,
            (SELECT COUNT(*) FROM item r WHERE r.parent = item.parent AND r.thr_parent = item.mid AND r.verb = '" . ACTIVITY_SHARE . "' AND r.item_deleted = 0) AS announce_count,
            (SELECT COUNT(*) FROM item r WHERE r.parent = item.id     AND r.item_thread_top = 0   AND r.item_deleted = 0) AS comment_count,
            (SELECT GROUP_CONCAT(verb, ':', author_xchan SEPARATOR '|')
             FROM item r
             WHERE r.parent = item.parent
               AND r.thr_parent = item.mid
               AND r.verb IN ('Like','Dislike','Announce')
               AND r.item_deleted = 0) AS reaction_verbs";

        // ── Fetch ─────────────────────────────────────────────────────────────
        $items     = [];
        $rootCount = 0;

        if ($nouveau) {
            $items = dbq("SELECT item.*, item.id AS item_id, $reaction_subqueries
                FROM item
                WHERE true $uids $item_normal
                $sql_extra $sql_date
                ORDER BY item.created DESC $pager_sql");

            $rootCount = count($items ?: []);

            if ($items) {
                xchan_query($items, true);
                $items = fetch_post_tags($items, true);
            }
        } else {
            // Two-step threaded fetch
            $r = dbq("SELECT item.id AS item_id FROM item
                WHERE true $uids $item_thread_top $item_normal
                AND item.mid = item.parent_mid
                $sql_extra3 $sql_extra
                ORDER BY $ordering DESC $pager_sql");

            $rootCount = count($r ?: []);

            if ($r) {
                $ids = ids_to_querystr($r, 'item_id');

                $items = dbq("SELECT item.*, $reaction_subqueries
                    FROM item
                    WHERE item.id IN ($ids)
                    OR (item.parent IN ($ids)
                        AND item.verb IN ('Create', 'Update', 'EmojiReact')
                        AND item.obj_type NOT IN ('Answer')
                        AND item.item_thread_top = 0
                        $item_normal)
                    ORDER BY item.created ASC");

                if ($items) {
                    xchan_query($items, true);
                    $items = fetch_post_tags($items, true);

                    usort($items, function ($a, $b) use ($ordering) {
                        if ($a['item_thread_top'] && $b['item_thread_top']) {
                            $key = $ordering === 'commented' ? 'commented' : 'created';
                            return strtotime($b[$key]) - strtotime($a[$key]);
                        }
                        return strtotime($a['created']) - strtotime($b['created']);
                    });
                }
            }
        }

        // ── Format and respond ────────────────────────────────────────────────
        $out = array_map(
            fn($item) => $this->formatItem($item, $observer_xchan),
            $items ?: []
        );

        Response::send($out, [
            'offset'   => $offset,
            'limit'    => $itemspage,
            'nouveau'  => $nouveau,
            'has_more' => count($out) >= $itemspage,
            'ordering' => $ordering,
            'count'    => count($out),
        ]);
    }

    private function resolveChannel(int $uid): array
    {
        $nick = \App::$argv[2] ?? null;

        if ($nick) {
            $channel = channelx_by_nick($nick);
            if (!$channel || $channel['channel_removed']) {
                Response::error(404, 'Channel not found');
            }

            $perms = get_all_perms($channel['channel_id'], get_observer_hash());
            if (!$perms['view_stream']) {
                Response::error(403, 'Permission denied');
            }

            return $channel;
        }

        $channel = \App::get_channel();
        if (!$channel) {
            Response::error(500, 'Could not resolve channel');
        }

        return $channel;
    }
}
