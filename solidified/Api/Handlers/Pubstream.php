<?php
// extend/theme/utsukta-themes/solidified/Api/Handlers/Pubstream.php
namespace Theme\Solidified\Api\Handlers;

use Zotlabs\Lib\Config;
use Theme\Solidified\Api\Response;
use Theme\Solidified\Api\Concerns\FormatsItems;

class Pubstream {

    use FormatsItems;

    public function get(): void {
        // --- Gate 1: pubstream must be globally enabled -----------------------
        $net_firehose = ((Config::Get('system', 'disable_discover_tab', 1)) ? false : true);
        if (!$net_firehose) {
            Response::error('Public stream is disabled on this site.', 403);
            return;
        }

        // --- Gate 2: observer restrictions (mirrors Pubstream module logic) ---
        if (observer_prohibited(true)) {
            Response::error('Authentication required.', 401);
            return;
        }

        if (!intval(Config::Get('system', 'open_pubstream', 1))) {
            if (!get_observer_hash()) {
                Response::error('Authentication required.', 401);
                return;
            }
        }

        // --- Parameters -------------------------------------------------------
        $page    = max(1, intval($_GET['page'] ?? 1));
        $limit   = min(30, max(1, intval($_GET['limit'] ?? 20)));
        $offset  = ($page - 1) * $limit;
        $hashtag = isset($_GET['tag']) ? trim($_GET['tag']) : '';
        $net     = isset($_GET['net']) ? escape_tags($_GET['net']) : '';

        // --- Site firehose flag -----------------------------------------------
        $site_firehose     = (intval(Config::Get('system', 'site_firehose', 0))) ? true : false;
        $site_firehose_sql = '';

        require_once('include/channel.php');
        require_once('include/security.php');
        require_once('include/conversation.php');

        $sys       = get_sys_channel();
        $sys_id    = intval($sys['channel_id']);
        $uids      = " and item.uid = {$sys_id} ";
        $abook_uids = " and abook.abook_channel = {$sys_id} ";

        $sql_extra        = item_permissions_sql($sys_id);
        $item_normal      = item_normal();
        $thread_top       = " and item.item_thread_top = 1 ";
        $ordering         = Config::Get('system', 'pubstream_ordering', 'created');

        if ($site_firehose) {
            $site_firehose_sql = " and owner_xchan in (
                select channel_hash from channel
                where channel_system = 0 and channel_removed = 0
            ) ";
        }

        if ($hashtag) {
            $sql_extra .= protect_sprintf(term_query('item', $hashtag, TERM_HASHTAG, TERM_COMMUNITYTAG));
        }

        $net_query = $net
            ? " and xchan_network = '" . protect_sprintf(dbesc($net)) . "' "
            : '';

        // --- Fetch parent item IDs -------------------------------------------
        $r = dbq("SELECT parent AS item_id FROM item
            left join abook on ( item.author_xchan = abook.abook_xchan {$abook_uids} )
            WHERE item.item_private = 0 {$thread_top}
            {$uids} {$site_firehose_sql}
            {$item_normal}
            and (abook.abook_blocked = 0 or abook.abook_flags is null)
            {$sql_extra} {$net_query}
            ORDER BY item.{$ordering} DESC
            LIMIT {$limit} OFFSET {$offset}
        ");

        if (!$r) {
            Response::send([
                'posts'    => [],
                'page'     => $page,
                'has_more' => false,
                'meta'     => [
                    'firehose'  => $site_firehose,
                    'ordering'  => $ordering,
                ],
            ]);
            return;
        }

        // --- Fetch full items with xchan & tags ------------------------------
        $items = items_by_parent_ids($r);
        xchan_query($items);
        $items = fetch_post_tags($items, true);
        $items = conv_sort($items, $ordering);

        // --- Format ----------------------------------------------------------
        $observer_xchan = get_observer_hash();
        $posts = [];
        foreach ($items as $item) {
            if (!intval($item['item_thread_top'] ?? 0)) continue;
            $posts[] = $this->formatItem($item , $observer_xchan);
        }

        $has_more = count($r) >= $limit;

        Response::send([
            'posts'    => $posts,
            'page'     => $page,
            'has_more' => $has_more,
            'meta'     => [
                'firehose'  => $site_firehose,
                'ordering'  => $ordering,
            ],
        ]);
    }
}
