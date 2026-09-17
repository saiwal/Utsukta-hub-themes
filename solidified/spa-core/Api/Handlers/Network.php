<?php
// packages/spa-core/php/Api/Handlers/Network.php

namespace Utsukta\SpaCore\Api\Handlers;

use Utsukta\SpaCore\Api\Concerns\FormatsItems;
use Utsukta\SpaCore\Api\Concerns\ReactionCounts;
use Utsukta\SpaCore\Api\Concerns\StreamFilters;
use Utsukta\SpaCore\Api\Concerns\StreamOrdering;
use Utsukta\SpaCore\Api\Concerns\CachesRanking;
use Utsukta\SpaCore\Api\Concerns\FiltersBlockedChannels;
use Utsukta\SpaCore\Api\Auth;
use Utsukta\SpaCore\Api\Response;

require_once ('include/items.php');
require_once ('include/conversation.php');
require_once ('include/acl_selectors.php');

class Network
{
    use FormatsItems;
    use FiltersBlockedChannels;
    use CachesRanking;

    public function get(): void
    {
        Auth::RequireLocalGet();

        $uid = local_channel();
        $channel = \App::get_channel();
        $item_normal = item_normal($uid);
        $observer_xchan = get_observer_hash();
        $abook_uids = ' and abook.abook_channel = ' . $uid . ' ';
        $uids = ' and item.uid = ' . $uid . ' ';

        // ── Pagination ────────────────────────────────────────────────────────
        $itemspage = max(1, min(30, intval(get_pconfig($uid, 'system', 'itemspage') ?: 10)));
        $offset = max(0, intval($_GET['start'] ?? 0));
        $pager_sql = " LIMIT $itemspage OFFSET $offset ";

        // ── Ordering ──────────────────────────────────────────────────────────
        // Deliberately not falling back to the 'mod_network'/'order' pconfig here:
        // that value is only ever written by classic Hubzilla's legacy Activity_order
        // widget, which the SPA has no UI for — honoring it would silently apply a
        // stale preference from a page the user can't see or change.
        $get_order = $_GET['order'] ?? 'created';

        if (!StreamOrdering::isValid($get_order)) {
            $get_order = 'created';
        }

        // 'unthreaded' is the only order that also changes the shape of the
        // result (flat, not threaded); the rest only change the ORDER BY.
        $nouveau = ($get_order === 'unthreaded');
        $clause = StreamOrdering::clause($get_order, $uid);
        $ordering = $clause['order'];
        $rank_join = $clause['join'];

        // ── Filters ───────────────────────────────────────────────────────────
        // Shared with /spa/hq-messages so the inbox answers to the same
        // right-sidebar filter widget — see Concerns/StreamFilters.
        $blocked = $this->blockedXchans($uid);
        $f = StreamFilters::build($_GET, $uid, [
            'alias' => 'item',
            'channel_hash' => $channel['channel_hash'],
            'observer_xchan' => $observer_xchan,
            'item_normal' => $item_normal,
            'flat' => $nouveau,
            'extra' => $this->blockedSqlClause('item.author_xchan', $blocked)
                     . $this->blockedSqlClause('item.owner_xchan', $blocked),
        ]);

        $sql_extra = $f['extra'];
        $sql_options = $f['options'];
        $sql_nets = $f['nets'];
        $sql_date = $f['date'];
        $item_thread_top = $f['thread_top'];
        $nouveau = $f['flat'];
        $net_query = $f['net_query'];
        $net_query2 = $f['net_query2'];

        // A "jump to this date" query is inherently chronological, so it
        // overrides `commented` — but not the ranked orders, where
        // "best posts before <date>" is a perfectly sensible request.
        if ($f['datequery'] && !StreamOrdering::isRanked($get_order)) {
            $ordering = StreamOrdering::clause('created', $uid)['order'];
            $rank_join = '';
        }

        // In threaded mode date filter goes on the parent query only
        $sql_extra3 = $nouveau ? '' : $sql_date;

        // ── Shared reaction subqueries ─────────────────────────────────────────
        $reaction_subqueries = ReactionCounts::subqueries();

        // ── Fetch items ───────────────────────────────────────────────────────
        $items = [];
        $rootCount = 0;
        $cached = false;

        if ($nouveau) {
            // Flat / unthreaded
            $items = dbq("SELECT item.*, item.id AS item_id, $reaction_subqueries
                FROM item
                LEFT JOIN abook ON ( item.owner_xchan = abook.abook_xchan $abook_uids )
                $rank_join
                $net_query
                WHERE true $uids $item_normal
                AND (abook.abook_blocked = 0 OR abook.abook_flags IS NULL)
                AND item.verb NOT IN ('Add', 'Remove')
                $sql_extra $sql_options $sql_nets $sql_date
                $net_query2
                ORDER BY $ordering DESC" . StreamOrdering::TIEBREAK . " $pager_sql");

            $rootCount = count($items ?: []);

            if ($items) {
                xchan_query($items, true);
                $items = fetch_post_tags($items, true);
            }
        } else {
            // Threaded — two-step: parent ids then full threads
            $parents_sql = "SELECT item.parent AS item_id FROM item
                LEFT JOIN abook ON ( item.owner_xchan = abook.abook_xchan $abook_uids )
                $rank_join
                $net_query
                WHERE true $uids $item_thread_top $item_normal
                AND item.mid = item.parent_mid
                AND (abook.abook_blocked = 0 OR abook.abook_flags IS NULL)
                $sql_extra3 $sql_extra $sql_options $sql_nets
                $net_query2
                ORDER BY $ordering DESC" . StreamOrdering::TIEBREAK . " ";

            // Ranked orders sort the whole candidate set before they can
            // return a page, so the ordered ids are cached and every later
            // page of the same scroll is a free slice. Chronological orders
            // stop at LIMIT and stay live.
            if (StreamOrdering::isRanked($get_order) && $this->rankCacheCovers($offset, $itemspage)) {
                $depth = $this->rankCacheDepth();
                $ranked = $this->rankedIds(
                    $this->rankCacheKey('network', $uid, $get_order, $_GET),
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
                    $ordered_parents = array_map('intval', array_column($r, 'item_id'));

                    usort($items, function ($a, $b) use ($ordered_parents) {
                        $ia = array_search(intval($a['id']), $ordered_parents);
                        $ib = array_search(intval($b['id']), $ordered_parents);
                        return $ia - $ib;
                    });
                }
            }
        }

        // ── Viewer following state ────────────────────────────────────────────
        $this->applyViewerFollowing($items, $observer_xchan);

        // ── Format and respond ────────────────────────────────────────────────
        $out = [];
        $out = array_map(
            fn($item) => $this->formatItem($item, $observer_xchan),
            $items
        );

        Response::paginate(
            $out,
            $offset,
            $itemspage,
            $rootCount,
            $nouveau,
            ['ordering' => $get_order, 'cached' => $cached],
        );
    }
}
