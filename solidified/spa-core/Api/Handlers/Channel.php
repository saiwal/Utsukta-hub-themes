<?php
namespace Utsukta\SpaCore\Api\Handlers;

use Utsukta\SpaCore\Api\Concerns\FormatsItems;
use Utsukta\SpaCore\Api\Concerns\ReactionCounts;
use Utsukta\SpaCore\Api\Concerns\StreamOrdering;
use Utsukta\SpaCore\Api\Concerns\CachesRanking;
use Utsukta\SpaCore\Api\Concerns\FiltersBlockedChannels;
use Utsukta\SpaCore\Api\Response;

class Channel
{
    use FormatsItems;
    use FiltersBlockedChannels;
    use CachesRanking;

    public function get(): void
    {
        require_once 'include/items.php';
        require_once 'include/security.php';

        $uid         = local_channel() ?: 0;
        $channel     = $this->resolveChannel($uid);
        $channel_uid = intval($channel['channel_id']);

        $observer_xchan = get_observer_hash();
        $itemspage      = max(1, min(30, intval(get_pconfig($uid ?: $channel_uid, 'system', 'itemspage') ?: 10)));

        // ── Pinned posts (channel wall) ──────────────────────────────────────
        $pinnedMidsRaw = get_pconfig($channel_uid, 'pinned', ITEM_TYPE_POST, []);
        $pinnedMids    = array_map('unpack_link_id', is_array($pinnedMidsRaw) ? $pinnedMidsRaw : []);
        $pinnedMidSet  = array_flip($pinnedMids);

        // ── Pagination ────────────────────────────────────────────────────────
        $offset     = max(0, intval($_GET['start'] ?? 0));
        $pager_sql  = " LIMIT $itemspage OFFSET $offset ";

        // ── Ordering ──────────────────────────────────────────────────────────
        $get_order = $_GET['order'] ?? 'created';
        if (!StreamOrdering::isValid($get_order)) {
            $get_order = 'created';
        }

        // 'unthreaded' is the only order that also changes the shape of the
        // result (flat, not threaded); the rest only change the ORDER BY.
        $nouveau   = ($get_order === 'unthreaded');
        $clause    = StreamOrdering::clause($get_order, $channel_uid);
        $ordering  = $clause['order'];
        $rank_join = $clause['join'];

        // ── Filter params ─────────────────────────────────────────────────────
        $search   = $_GET['search']  ?? '';
        $hashtags = $_GET['tag']     ?? '';
        $category = $_GET['cat']     ?? '';
        $mid      = $_GET['mid']     ?? '';
        $dm       = intval($_GET['dm'] ?? 0);

        $datequery  = (isset($_GET['dend'])   && is_a_date_arg($_GET['dend']))
            ? notags($_GET['dend'])   : '';
        $datequery2 = (isset($_GET['dbegin']) && is_a_date_arg($_GET['dbegin']))
            ? notags($_GET['dbegin']) : '';

        if ($search || $hashtags || $category || $dm) {
            $nouveau = true;
        }

        // ── SQL scaffolding ───────────────────────────────────────────────────
        // Passing the channel uid lets item_normal() include delayed
        // (scheduled) posts when the viewer is the channel owner.
        $item_normal     = item_normal($channel_uid);
        $uids            = ' AND item.uid = ' . $channel_uid . ' ';
        $item_thread_top = ' AND item_thread_top = 1 ';
        $blocked         = $this->blockedXchans($uid);
        // dm=1 shows the whole DM conversation with this channel, so it must
        // include the channel's *received* copies (item_wall = 0) as well as
        // what it sent. item_permissions_sql() below is what fences visitors:
        // a received copy carries an empty allow_cid, so it only ever matches
        // for the observer who authored it, or for the channel owner.
        $sql_extra       = ($dm ? '' : ' AND item.item_wall = 1 ')
            . $this->blockedSqlClause('item.author_xchan', $blocked)
            . $this->blockedSqlClause('item.owner_xchan', $blocked);

        // Verb whitelist — exclude reactions and federation noise
				$sql_extra .= " AND item.verb IN ('Create', 'Update', 'EmojiReact', 'Invite') ";

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

        // Privacy fence — DMs are hidden from the general wall view by
        // default, and are the only thing shown when dm=1 is requested.
        $sql_extra .= $dm
            ? ' AND item.item_private = 2 '
            : ' AND item.item_private IN (0, 1) ';

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
        $reaction_subqueries = ReactionCounts::subqueries();

        // ── Fetch ─────────────────────────────────────────────────────────────
        $items     = [];
        $rootCount = 0;
        $cached    = false;

        if ($nouveau) {
            $items = dbq("SELECT item.*, item.id AS item_id, $reaction_subqueries
                FROM item
                $rank_join
                WHERE true $uids $item_normal
                $sql_extra $sql_date
                ORDER BY $ordering DESC $pager_sql");

            $rootCount = count($items ?: []);

            if ($items) {
                xchan_query($items, true);
                $items = fetch_post_tags($items, true);
            }
        } else {
            // Two-step threaded fetch
            $parents_sql = "SELECT item.id AS item_id FROM item
                $rank_join
                WHERE true $uids $item_thread_top $item_normal
                AND item.mid = item.parent_mid
                $sql_extra3 $sql_extra
                ORDER BY $ordering DESC ";

            // See Network.php — ranked orders sort the whole candidate set
            // before they can return a page, so the ordered ids are cached and
            // later pages of the same scroll are free slices. The observer is
            // part of the key: item_permissions_sql() fences visitors, so two
            // viewers of the same wall can legitimately rank differently.
            if (StreamOrdering::isRanked($get_order) && $this->rankCacheCovers($offset, $itemspage)) {
                $depth = $this->rankCacheDepth();
                $ranked = $this->rankedIds(
                    $this->rankCacheKey(
                        'channel:' . $channel_uid . ':' . $observer_xchan,
                        $uid, $get_order, $_GET
                    ),
                    fn() => array_column(dbq($parents_sql . " LIMIT $depth") ?: [], 'item_id')
                );
                $cached = $ranked['cached'];
                $page = array_slice($ranked['ids'], $offset, $itemspage);
                $r = array_map(fn($id) => ['item_id' => $id], $page);
            } else {
                $r = dbq($parents_sql . $pager_sql);
            }

            $rootCount = count($r ?: []);

            if ($r) {
                $ids = ids_to_querystr($r, 'item_id');

                $items = dbq("SELECT item.*, $reaction_subqueries
                    FROM item
                    WHERE item.id IN ($ids)
                    ORDER BY item.created ASC");

                if ($items) {
                    xchan_query($items, true);
                    $items = fetch_post_tags($items, true);

                    // Re-apply the order the parent query produced — the
                    // ranked orders have no column to re-sort by here.
                    $ordered_parents = array_map('intval', array_column($r, 'item_id'));
                    usort($items, fn($a, $b) =>
                        array_search(intval($a['id']), $ordered_parents)
                        - array_search(intval($b['id']), $ordered_parents));
                }
            }
        }

        // ── Viewer following state ────────────────────────────────────────────
        $items = $items ?: [];
        $this->applyViewerFollowing($items, $observer_xchan);

        // ── Format and respond ────────────────────────────────────────────────
        $out = array_map(
            fn($item) => $this->formatItem($item, $observer_xchan, isset($pinnedMidSet[$item['uuid']])),
            $items
        );

        // ── Pinned posts banner (base wall view only: no filters, first page) ──
        // Not folded into the main query/pagination — matches core's separate
        // Zotlabs/Widget/Pinned.php, which never touches stream ordering.
        $showPinnedMeta  = (!$nouveau && $offset === 0);
        $pinnedFormatted = [];

        if ($showPinnedMeta && $pinnedMids) {
            $pinnedInList = implode("','", array_map('dbesc', $pinnedMids));
            $prows = dbq("SELECT item.*, $reaction_subqueries
                FROM item
                WHERE item.uuid IN ('$pinnedInList')
                  AND item.uid = $channel_uid
                  AND item.id = item.parent
                  AND item.item_private = 0
                  AND item.item_deleted = 0");

            if ($prows) {
                xchan_query($prows, true);
                $prows = fetch_post_tags($prows, true);
                $this->applyViewerFollowing($prows, $observer_xchan);

                // Most-recently-pinned first.
                $pinOrder = array_flip(array_reverse($pinnedMids));
                usort($prows, fn($a, $b) => ($pinOrder[$a['uuid']] ?? 0) <=> ($pinOrder[$b['uuid']] ?? 0));

                $pinnedFormatted = array_map(
                    fn($item) => $this->formatItem($item, $observer_xchan, true),
                    $prows
                );

                // Dedup: don't also show these inline in the page-1 batch.
                $pinnedUuids = array_flip(array_column($prows, 'uuid'));
                $out = array_values(array_filter($out, fn($f) => !isset($pinnedUuids[$f['uuid']])));
            }
        }

        $can_post_wall = perm_is_allowed($channel_uid, $observer_xchan, 'post_wall');

        $meta = [
            'offset'        => $offset,
            'limit'         => $itemspage,
            'count'         => count($out),
            'root_count'    => $rootCount,
            'has_more'      => $rootCount >= $itemspage,
            'nouveau'       => $nouveau,
            'ordering'      => $get_order,
            'cached'        => $cached,
            'can_post_wall' => $can_post_wall,
        ];
        if ($showPinnedMeta) {
            $meta['pinned'] = $pinnedFormatted;
        }

        Response::send($out, $meta);
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
