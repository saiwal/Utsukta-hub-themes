<?php
/**
 * Theme\Solidified\Api\Handlers\Network
 *
 * GET /api/network
 *
 * Mirrors Zotlabs\Module\Network::get() SQL verbatim.
 * Uses dbq() + xchan_query() + fetch_post_tags() + conv_sort()
 * exactly as the original does — no items_fetch() abstraction.
 *
 * Query params (all optional)
 * ───────────────────────────
 * start    int     Offset for pagination (default 0)
 * limit    int     Items per page        (default 20, max 40)
 * order    string  created | commented | unthreaded
 * search   string  Full-text / #hashtag search
 * tag      string  Hashtag filter (without #)
 * cat      string  Category filter
 * verb     string  Verb filter; prefix '.' matches obj_type
 * gid      int     Privacy-group id
 * cid      int     Address-book contact id
 * xchan    string  xchan_hash filter
 * net      string  xchan_network filter
 * pf       int     1 = public-forum mode for cid
 * star     int     1 = starred only
 * conv     int     1 = conversations involving me
 * dm       int     1 = direct messages only
 * unseen   int     1 = unseen only
 * cmin     int     Affinity min (-1 = disabled)
 * cmax     int     Affinity max (-1 = disabled)
 * dbegin   string  Date range start (YYYY-MM-DD)
 * dend     string  Date range end   (YYYY-MM-DD)
 */

namespace Theme\Solidified\Api\Handlers;

use Theme\Solidified\Api\Auth;
use Theme\Solidified\Api\Response;
use Zotlabs\Lib\AccessList;
use Zotlabs\Lib\Apps;
use App;

require_once 'include/items.php';
require_once 'include/taxonomy.php';
require_once 'include/conversation.php';

class Network
{
    public function get(): void
    {
        $uid     = Auth::requireLocalGet();
        $channel = App::get_channel();

        $limit = min(40, max(1, intval($_GET['limit'] ?? 20)));
        $start = max(0, intval($_GET['start'] ?? 0));

        // ── item_normal clause ────────────────────────────────────────────────
        $item_normal = item_normal();

        // ── Ordering ──────────────────────────────────────────────────────────
        $saved_order = get_pconfig($uid, 'mod_network', 'order', 'created');
        $order_param = trim($_GET['order'] ?? $saved_order);

        $nouveau  = false;
        $ordering = 'created';

        switch ($order_param) {
            case 'commented':
                $ordering = 'commented';
                break;
            case 'unthreaded':
                $nouveau = true;
                break;
            default:
                $ordering = 'created';
        }

        // ── Filter params ─────────────────────────────────────────────────────
        $search   = trim($_GET['search'] ?? '');
        $hashtags = trim($_GET['tag']    ?? '');
        $category = trim($_GET['cat']    ?? '');
        $verb     = trim($_GET['verb']   ?? '');
        $gid      = intval($_GET['gid']  ?? 0);
        $cid      = intval($_GET['cid']  ?? 0);
        $xchan    = trim($_GET['xchan']  ?? '');
        $net      = trim($_GET['net']    ?? '');
        $pf       = intval($_GET['pf']   ?? 0);
        $star     = intval($_GET['star'] ?? 0);
        $conv     = intval($_GET['conv'] ?? 0);
        $dm       = intval($_GET['dm']   ?? 0);
        $unseen   = intval($_GET['unseen'] ?? 0);

        $dateend   = (isset($_GET['dend'])   && is_a_date_arg($_GET['dend']))
            ? notags(trim($_GET['dend']))   : '';
        $datebegin = (isset($_GET['dbegin']) && is_a_date_arg($_GET['dbegin']))
            ? notags(trim($_GET['dbegin'])) : '';

        // # prefix in search → hashtag
        if ($search && str_starts_with($search, '#')) {
            $hashtags = substr($search, 1);
            $search   = '';
        }

        // Date filter forces created ordering
        if ($dateend) $ordering = 'created';

        // These modes collapse to nouveau (flat/unthreaded)
        if ($search || $hashtags || $verb || $category || $conv || $unseen
            || ($cid && !$pf)) {
            $nouveau = true;
        }

        // ── Affinity ─────────────────────────────────────────────────────────
        $affinity_app = Apps::system_app_installed($uid, 'Affinity Tool');
        $default_cmin = $affinity_app ? get_pconfig($uid, 'affinity', 'cmin', 0)  : -1;
        $default_cmax = $affinity_app ? get_pconfig($uid, 'affinity', 'cmax', 99) : -1;

        $cmin = array_key_exists('cmin', $_GET) ? intval($_GET['cmin']) : $default_cmin;
        $cmax = array_key_exists('cmax', $_GET) ? intval($_GET['cmax']) : $default_cmax;

        // ── SQL building — exact original logic ───────────────────────────────
        $sql_extra       = '';
        $sql_extra3      = '';
        $sql_nets        = '';
        $sql_options     = '';
        $net_query       = '';
        $net_query2      = '';
        $item_thread_top = ' AND item_thread_top = 1 ';
        $group           = 0;
        $group_hash      = '';

        // Starred
        if ($star) {
            $sql_options .= ' AND item_starred = 1 ';
        }

        // Privacy-group filter
        if ($gid) {
            $r = q(
                "SELECT * FROM pgrp WHERE id = %d AND uid = %d LIMIT 1",
                intval($gid), $uid
            );
            if (!$r) Response::error(404, 'No such group');

            $group      = $gid;
            $group_hash = $r[0]['hash'];
            $contacts   = AccessList::members($uid, $group);
            $contact_str = $contacts
                ? ids_to_querystr($contacts, 'xchan', true)
                : " '0' ";

            $item_thread_top = '';
            $sql_extra = " AND item.parent IN (
                SELECT DISTINCT parent FROM item
                WHERE true $sql_options
                AND (( author_xchan IN ( $contact_str )
                      OR owner_xchan IN ( $contact_str ))
                    OR allow_gid LIKE '%<" . dbesc($group_hash) . ">%')
                AND id = parent $item_normal
            ) ";
        }

        // Single-contact filter
        $cid_xchan = '';
        if ($cid && !$group) {
            $cid_r = q(
                "SELECT abook.abook_xchan, xchan.xchan_pubforum
                 FROM abook LEFT JOIN xchan ON abook_xchan = xchan_hash
                 WHERE abook_id = %d AND abook_channel = %d AND abook_blocked = 0 LIMIT 1",
                intval($cid), $uid
            );
            if (!$cid_r) Response::error(404, 'No such channel');
            $cid_xchan = $cid_r[0]['abook_xchan'];

            $item_thread_top = '';
            if (!$pf && $nouveau) {
                $sql_extra = " AND author_xchan = '" . dbesc($cid_xchan) . "' ";
            } else {
                $sql_extra = " AND item.parent IN (
                    SELECT DISTINCT parent FROM item
                    WHERE uid = $uid
                    AND ( author_xchan = '" . dbesc($cid_xchan) . "'
                         OR owner_xchan = '" . dbesc($cid_xchan) . "' )
                    $item_normal
                ) ";
            }
        }

        // xchan_hash filter
        if ($xchan && !$group && !$cid) {
            $xr = q(
                "SELECT xchan_hash FROM xchan WHERE xchan_hash = '%s' LIMIT 1",
                dbesc($xchan)
            );
            if (!$xr) Response::error(404, 'Invalid channel');

            $item_thread_top = '';
            $sql_extra = " AND item.parent IN (
                SELECT DISTINCT parent FROM item
                WHERE uid = $uid
                AND ( author_xchan = '" . dbesc($xchan) . "'
                     OR owner_xchan = '" . dbesc($xchan) . "' )
                $item_normal
            ) ";
        }

        // Category
        if ($category) {
            $sql_extra .= protect_sprintf(term_query('item', $category, TERM_CATEGORY));
        }

        // Hashtag — use LIKE for partial/prefix matching since term_query()
        // generates an exact equality check (term = 'foo') which won't match
        // partial input. We keep the same ttype filter (1=TERM_HASHTAG, 10=TERM_COMMUNITYTAG).
        if ($hashtags) {
            $tag_esc = dbesc(protect_sprintf('%' . $hashtags . '%'));
            $sql_extra .= " AND item.id IN (
                SELECT term.oid FROM term
                WHERE term.ttype IN (1, 10)
                AND term.term LIKE '$tag_esc'
                AND term.uid = item.uid
            ) ";
        }

        // Full-text search
        if ($search) {
            $esc = dbesc(protect_sprintf('%' . $search . '%'));
            $sql_extra .= " AND (item.body LIKE '$esc' OR item.title LIKE '$esc') ";
        }

        // Verb / obj_type filter
        if ($verb) {
            if (str_starts_with($verb, '.')) {
                $obj = dbesc(protect_sprintf(substr($verb, 1)));
                $sql_extra .= " AND item.obj_type = '$obj'
                               AND item.verb IN ('Create','Update','Invite') ";
            } else {
                $vesc = dbesc(protect_sprintf($verb));
                $sql_extra .= " AND item.verb = '$vesc' ";
            }
        }

        // Conversations involving me
        if ($conv) {
            $me = dbesc($channel['channel_hash']);
            $sql_extra .= " AND ( author_xchan = '$me' OR item_mentionsme = 1 ) ";
        }

        // Privacy filter — mirrors original $dismiss_privacy_filter
        $dismiss_privacy_filter = ($gid || $cid || $xchan || $conv
            || $star || $search || $verb || $category || $hashtags);

        if (!$dismiss_privacy_filter) {
            if ($dm) {
                $sql_extra .= ' AND item.item_private = 2 ';
            } else {
                $sql_extra .= ' AND item.item_private IN (0, 1) ';
            }
        }

        // Unseen
        if ($unseen) {
            $sql_extra .= ' AND item_unseen = 1 ';
        }

        // Date range (stripped for nouveau)
        if (!$nouveau) {
            if ($dateend) {
                $sql_extra3 .= protect_sprintf(sprintf(
                    " AND item.created <= '%s' ",
                    dbesc(datetime_convert(date_default_timezone_get(), '', $dateend))
                ));
            }
            if ($datebegin) {
                $sql_extra3 .= protect_sprintf(sprintf(
                    " AND item.created >= '%s' ",
                    dbesc(datetime_convert(date_default_timezone_get(), '', $datebegin))
                ));
            }
        }

        // Affinity range
        if ($cmin !== -1 || $cmax !== -1) {
            $sql_nets .= ' AND ';
            if ($cmax === 99) $sql_nets .= ' ( ';
            $sql_nets .= ' ( abook.abook_closeness >= ' . intval($cmin)
                       . ' AND abook.abook_closeness <= ' . intval($cmax) . ' ) ';
            if ($cmax === 99) $sql_nets .= ' OR abook.abook_closeness IS NULL ) ';
        }

        // Network filter
        if ($net) {
            $net_query  = ' LEFT JOIN xchan ON xchan_hash = author_xchan ';
            $net_query2 = " AND xchan_network = '" . dbesc(protect_sprintf($net)) . "' ";
        }

        // ── Pagination ────────────────────────────────────────────────────────
        $pager_sql  = sprintf(" LIMIT %d OFFSET %d ", $limit, $start);
        $abook_uids = ' AND abook.abook_channel = ' . $uid . ' ';
        $uids       = ' AND item.uid = ' . $uid . ' ';

        // ── Execute — mirrors original Network::get() SQL exactly ─────────────
        $items = [];

        if ($nouveau) {
            // Flat / unthreaded view
            $items = dbq("SELECT item.*, item.id AS item_id FROM item
                LEFT JOIN abook ON ( item.owner_xchan = abook.abook_xchan $abook_uids )
                $net_query
                WHERE true $uids $item_normal
                AND (abook.abook_blocked = 0 OR abook.abook_flags IS NULL)
                AND item.verb NOT IN ('Add', 'Remove')
                $sql_extra $sql_options $sql_nets
                $net_query2
                ORDER BY item.created DESC $pager_sql"
            );

            xchan_query($items);
            $items = fetch_post_tags($items, true);

        } else {
            // Threaded view: fetch root ids, then expand with children
            $r = dbq("SELECT item.parent AS item_id FROM item
                LEFT JOIN abook ON ( item.owner_xchan = abook.abook_xchan $abook_uids )
                $net_query
                WHERE true $uids $item_thread_top $item_normal
                AND item.mid = item.parent_mid
                AND (abook.abook_blocked = 0 OR abook.abook_flags IS NULL)
                AND item.verb NOT IN ('Add', 'Remove')
                $sql_extra3 $sql_extra $sql_options $sql_nets
                $net_query2
                ORDER BY item.$ordering DESC $pager_sql"
            );

            if ($r) {
                $items = items_by_parent_ids($r);
                xchan_query($items, true);
                $items = fetch_post_tags($items, true);
                $items = conv_sort($items, $ordering);
            }
        }

        if (!$items) {
            Response::paginate([], $start, $limit, 0);
        }

        // ── Format ────────────────────────────────────────────────────────────
        $root_count = 0;
        $out        = [];

        foreach ($items as $item) {
            if (intval($item['item_thread_top'] ?? 0)) {
                $root_count++;
            }
            if (in_array($item['verb'] ?? '', ['Add', 'Remove'], true)) {
                continue;
            }
            $out[] = $this->formatItem($item, $uid);
        }

        Response::paginate($out, $start, $limit, $root_count);
    }

    // ── Item formatter ────────────────────────────────────────────────────────

    private function formatItem(array $item, int $uid): array
    {
        // xchan_query() populates xchan_* fields directly on the item row
        $author = [
            'name'    => $item['xchan_name']             ?? '',
            'url'     => $item['xchan_url']              ?? '',
            'address' => $item['xchan_addr']             ?? '',
            'photo'   => [
                'src'      => $item['xchan_photo_m']        ?? '',
                'mimetype' => $item['xchan_photo_mimetype'] ?? '',
            ],
        ];

        // Reaction counts from children attached by items_by_parent_ids()
        $like_count      = 0;
        $dislike_count   = 0;
        $announce_count  = 0;
        $viewer_liked    = false;
        $viewer_disliked = false;
        $viewer_repeated = false;
        $ob_hash = get_observer_hash();

        if (!empty($item['children'])) {
            foreach ($item['children'] as $child) {
                $v  = $child['verb']         ?? '';
                $ax = $child['author_xchan'] ?? '';
                if ($v === 'Like')     $like_count++;
                if ($v === 'Dislike')  $dislike_count++;
                if ($v === 'Announce') $announce_count++;
                if ($ob_hash) {
                    if ($v === 'Like'     && $ax === $ob_hash) $viewer_liked    = true;
                    if ($v === 'Dislike'  && $ax === $ob_hash) $viewer_disliked = true;
                    if ($v === 'Announce' && $ax === $ob_hash) $viewer_repeated = true;
                }
            }
        }

        $flags = [];
        if (!empty($item['item_starred']))    $flags[] = 'starred';
        if (!empty($item['item_mentionsme'])) $flags[] = 'mentionsme';
        if (!empty($item['item_thread_top'])) $flags[] = 'thread_top';
        if (!empty($item['item_private']))    $flags[] = 'private';

        return [
            'iid'             => intval($item['id']              ?? 0),
            'uuid'            => $item['uuid']                   ?? '',
            'mid'             => $item['mid']                    ?? '',
            'parent_mid'      => $item['parent_mid']             ?? '',
            'thr_parent'      => $item['thr_parent']             ?? '',
            'message_top'     => $item['parent_mid']             ?? '',
            'profile_uid'     => $uid,
            'title'           => $item['title']                  ?? '',
            'body'            => $item['body']                   ?? '',
            'summary'         => $item['obj_summary']            ?? '',
            'created'         => $item['created']                ?? '',
            'edited'          => $item['edited']                 ?? '',
            'commented'       => $item['commented']              ?? '',
            'verb'            => $item['verb']                   ?? '',
            'obj_type'        => $item['obj_type']               ?? '',
            'item_thread_top' => intval($item['item_thread_top'] ?? 0),
            'item_private'    => intval($item['item_private']    ?? 0),
            'item_unseen'     => intval($item['item_unseen']     ?? 0),
            'author'          => $author,
            'like_count'      => $like_count,
            'dislike_count'   => $dislike_count,
            'announce_count'  => $announce_count,
            'viewer_liked'    => $viewer_liked,
            'viewer_disliked' => $viewer_disliked,
            'viewer_repeated' => $viewer_repeated,
            'flags'           => $flags,
            'permalink'       => z_root() . '/display/' . ($item['uuid'] ?? ''),
        ];
    }
}
