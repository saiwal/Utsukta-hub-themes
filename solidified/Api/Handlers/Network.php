<?php
// extend/theme/utsukta-themes/solidified/Api/Handlers/Network.php

namespace Theme\Solidified\Api\Handlers;

use Theme\Solidified\Api\Concerns\FormatsItems;
use Theme\Solidified\Api\Auth;
use Theme\Solidified\Api\Response;
use Zotlabs\Lib\AccessList;
use Zotlabs\Lib\Apps;

require_once ('include/items.php');
require_once ('include/conversation.php');
require_once ('include/acl_selectors.php');

class Network
{
    use FormatsItems;

    public function get(): void
    {
        Auth::RequireLocalGet();

        $uid = local_channel();
        $channel = \App::get_channel();
        $observer_xchan = get_observer_hash();

        // ── Ordering (identical to core) ──────────────────────────────────────
        $order = get_pconfig($uid, 'mod_network', 'order', 'created');

        // Honour ?order= override and persist it, just like core does
        if (isset($_GET['order'])) {
            $order = $_GET['order'];
            set_pconfig($uid, 'mod_network', 'order', $order);
        }

        $nouveau = false;
        $ordering = 'created';

        switch ($order) {
            case 'commented':
                $ordering = 'commented';
                break;
            case 'unthreaded':
                $nouveau = true;
                $ordering = 'created';
                break;
            default:
                $ordering = 'created';
        }

        // ── Params (identical to core) ─────────────────────────────────────────
        $datequery = (isset($_GET['dend']) && is_a_date_arg($_GET['dend'])) ? notags($_GET['dend']) : '';
        $datequery2 = (isset($_GET['dbegin']) && is_a_date_arg($_GET['dbegin'])) ? notags($_GET['dbegin']) : '';

        $gid = intval($_GET['gid'] ?? 0);
        $cid = intval($_GET['cid'] ?? 0);
        $star = intval($_GET['star'] ?? 0);
        $liked = intval($_GET['liked'] ?? 0);
        $conv = intval($_GET['conv'] ?? 0);
        $spam = intval($_GET['spam'] ?? 0);
        $dm = intval($_GET['dm'] ?? 0);
        $pf = $_GET['pf'] ?? '';
        $unseen = $_GET['unseen'] ?? '';
        $xchan = $_GET['xchan'] ?? '';
        $net = $_GET['net'] ?? '';
        $file = $_GET['file'] ?? '';
        $category = $_REQUEST['cat'] ?? '';
        $hashtags = $_REQUEST['tag'] ?? '';
        $verb = $_REQUEST['verb'] ?? '';
        $search = $_GET['search'] ?? '';

        $default_cmin = Apps::system_app_installed($uid, 'Affinity Tool')
            ? get_pconfig($uid, 'affinity', 'cmin', 0)
            : -1;
        $default_cmax = Apps::system_app_installed($uid, 'Affinity Tool')
            ? get_pconfig($uid, 'affinity', 'cmax', 99)
            : -1;

        $cmin = array_key_exists('cmin', $_GET) ? intval($_GET['cmin']) : $default_cmin;
        $cmax = array_key_exists('cmax', $_GET) ? intval($_GET['cmax']) : $default_cmax;

        if ($search && strpos($search, '#') === 0) {
            $hashtags = substr($search, 1);
            $search = '';
        }

        if ($datequery)
            $ordering = 'created';

        if ($search || $file || (!$pf && $cid) || $hashtags || $verb || $category || $conv || $unseen)
            $nouveau = true;

        // ── Pagination ────────────────────────────────────────────────────────
        $itemspage = intval(get_pconfig($uid, 'system', 'itemspage') ?: 10);
        \App::set_pager_itemspage($itemspage);

        // Allow ?start= override for our API (core uses App::$pager['start'])
        if (isset($_GET['start']))
            \App::$pager['start'] = max(0, intval($_GET['start']));

        $pager_sql = sprintf(
            ' LIMIT %d OFFSET %d ',
            intval(\App::$pager['itemspage']),
            intval(\App::$pager['start'])
        );

        // ── SQL fragments (identical to core) ─────────────────────────────────
        $item_normal = item_normal();
        $abook_uids = ' and abook.abook_channel = ' . $uid . ' ';
        $uids = ' and item.uid = ' . $uid . ' ';
        $sql_options = $star ? ' and item_starred = 1 ' : '';
        $sql_nets = '';
        $sql_extra = '';
        $item_thread_top = ' AND item_thread_top = 1 ';

        $dismiss_privacy_filter = array_intersect(
            ['cid', 'star', 'conv', 'file', 'verb', 'cat', 'search'],
            array_keys($_GET)
        );

        // Group filter
        $group = 0;
        $group_hash = '';
        if ($gid) {
            $r = q('SELECT * FROM pgrp WHERE id = %d AND uid = %d LIMIT 1', intval($gid), $uid);
            if (!$r)
                Response::error(404, 'No such group');

            $group = $gid;
            $group_hash = $r[0]['hash'];
            $contacts = AccessList::members($uid, $group);
            $contact_str = $contacts ? ids_to_querystr($contacts, 'xchan', true) : " '0' ";

            $item_thread_top = '';
            $sql_extra = " AND item.parent IN ( SELECT DISTINCT parent FROM item WHERE true $sql_options
                AND (( author_xchan IN ( $contact_str ) OR owner_xchan IN ( $contact_str ))
                OR allow_gid LIKE '" . protect_sprintf('%<' . dbesc($group_hash) . '>%') . "' )
                AND id = parent $item_normal ) ";
        }

        // Contact filter
        $cid_r = [];
        if ($cid) {
            $cid_r = q('SELECT abook.abook_xchan, xchan.xchan_addr, xchan.xchan_name, xchan.xchan_url,
                        xchan.xchan_photo_s, xchan.xchan_pubforum
                        FROM abook LEFT JOIN xchan ON abook_xchan = xchan_hash
                        WHERE abook_id = %d AND abook_channel = %d AND abook_blocked = 0 LIMIT 1',
                intval($cid), $uid);

            if (!$cid_r)
                Response::error(404, 'No such channel');

            $item_thread_top = '';
            if (!$pf && $nouveau)
                $sql_extra = " AND author_xchan = '" . dbesc($cid_r[0]['abook_xchan']) . "' ";
            else
                $sql_extra = " AND item.parent IN (SELECT DISTINCT parent FROM item
                    WHERE uid = $uid AND ( author_xchan = '" . dbesc($cid_r[0]['abook_xchan']) . "'
                    OR owner_xchan = '" . dbesc($cid_r[0]['abook_xchan']) . "' ) $item_normal) ";
        }

        // xchan filter
        if ($xchan) {
            $item_thread_top = '';
            $sql_extra = " AND item.parent IN ( SELECT DISTINCT parent FROM item WHERE true $sql_options
                AND uid = $uid AND ( author_xchan = '" . dbesc($xchan) . "'
                OR owner_xchan = '" . dbesc($xchan) . "' ) $item_normal ) ";
        }

        if ($category)
            $sql_extra .= protect_sprintf(term_query('item', $category, TERM_CATEGORY));
        if ($hashtags)
            $sql_extra .= protect_sprintf(term_query('item', $hashtags, TERM_HASHTAG, TERM_COMMUNITYTAG));

        $sql_extra3 = '';
        if ($datequery)
            $sql_extra3 .= protect_sprintf(sprintf(" AND item.created <= '%s' ",
                dbesc(datetime_convert(date_default_timezone_get(), '', $datequery))));
        if ($datequery2)
            $sql_extra3 .= protect_sprintf(sprintf(" AND item.created >= '%s' ",
                dbesc(datetime_convert(date_default_timezone_get(), '', $datequery2))));

        $sql_extra3 = $nouveau ? '' : $sql_extra3;

        if ($search) {
            $search = escape_tags($search);
            if (strpos($search, '#') === 0)
                $sql_extra .= term_query('item', substr($search, 1), TERM_HASHTAG, TERM_COMMUNITYTAG);
            else
                $sql_extra .= sprintf(" AND (item.body LIKE '%s' OR item.title LIKE '%s') ",
                    dbesc(protect_sprintf('%' . $search . '%')),
                    dbesc(protect_sprintf('%' . $search . '%')));
        }

        if ($verb) {
            if (str_starts_with($verb, '.'))
                $sql_extra .= sprintf(" AND item.obj_type = '%s' AND item.verb IN ('Create','Update','Invite') ",
                    dbesc(protect_sprintf(substr($verb, 1))));
            else
                $sql_extra .= sprintf(" AND item.verb = '%s' ", dbesc(protect_sprintf($verb)));
        }

        if (strlen($file))
            $sql_extra .= term_query('item', $file, TERM_FILE);

        if (!$dismiss_privacy_filter)
            $sql_extra .= $dm ? ' AND item.item_private = 2 ' : ' AND item.item_private IN (0, 1) ';

        if ($conv) {
            $item_thread_top = '';
            $sql_extra .= " AND ( author_xchan = '" . dbesc($channel['channel_hash']) . "' OR item_mentionsme = 1 ) ";
        }

        if ($spam)
            $sql_extra .= ' AND item_spam = 1 ';

        if ($liked) {
            $item_thread_top = '';
            $sql_extra .= " AND item.parent IN (SELECT DISTINCT parent FROM item
                WHERE uid = $uid AND verb = 'Like'
                AND author_xchan = '" . dbesc($channel['channel_hash']) . "' $item_normal) ";
        }

        if (($cmin !== -1) || ($cmax !== -1)) {
            $sql_nets .= ' AND ';
            if ($cmax === 99)
                $sql_nets .= ' ( ';
            $sql_nets .= "( abook.abook_closeness >= $cmin AND abook.abook_closeness <= $cmax ) ";
            if ($cmax === 99)
                $sql_nets .= ' OR abook.abook_closeness IS NULL ) ';
        }

        $net_query = $net ? ' left join xchan on xchan_hash = author_xchan ' : '';
        $net_query2 = $net ? " and xchan_network = '" . protect_sprintf(dbesc($net)) . "' " : '';

        // ── Fetch items (mirrors core load path exactly) ──────────────────────
        $items = [];
        $rootCount = 0;

        if ($nouveau) {
            $items = dbq("SELECT item.*, item.id AS item_id FROM item
                LEFT JOIN abook ON ( item.owner_xchan = abook.abook_xchan $abook_uids )
                $net_query
                WHERE true $uids $item_normal
                AND (abook.abook_blocked = 0 OR abook.abook_flags IS NULL)
                AND item.verb NOT IN ('Add', 'Remove')
                $sql_extra $sql_options $sql_nets
                $net_query2
                ORDER BY item.created DESC $pager_sql");

            $rootCount = count($items ?: []);

            if ($items) {
                xchan_query($items);
                $items = fetch_post_tags($items, true);
            }
        } else {
            $r = dbq("SELECT item.parent AS item_id FROM item
                LEFT JOIN abook ON ( item.owner_xchan = abook.abook_xchan $abook_uids )
                $net_query
                WHERE true $uids $item_thread_top $item_normal
                AND item.mid = item.parent_mid
                AND (abook.abook_blocked = 0 OR abook.abook_flags IS NULL)
                $sql_extra3 $sql_extra $sql_options $sql_nets
                $net_query2
                ORDER BY $ordering DESC $pager_sql");

            $rootCount = count($r ?: []);

            if ($r) {
                // Use core helpers — this is what fixes the ordering bug
                $items = items_by_parent_ids($r);
                xchan_query($items, true);
                $items = fetch_post_tags($items, true);
                $items = conv_sort($items, $ordering);  // core's own sort: groups by parent, chronological within
                // Build a map of mid -> repeaters for items in this page
                $thread_mids = array_map(
                    fn($i) => "'" . dbesc($i['mid']) . "'",
                    array_filter($items, fn($i) => intval($i['item_thread_top']) === 1)
                );

                $repeaters_map = [];
                if ($thread_mids) {
                    $mid_list = implode(',', $thread_mids);
                    $ann = dbq("SELECT item.thr_parent, xchan.xchan_name, xchan.xchan_url, xchan.xchan_photo_m
        FROM item
        LEFT JOIN xchan ON item.author_xchan = xchan.xchan_hash
        WHERE item.verb = 'Announce'
        AND item.thr_parent IN ($mid_list)
        AND item.uid = $uid
        AND item.item_hidden = 0
        ORDER BY item.created ASC");

                    foreach ($ann ?: [] as $a) {
                        $repeaters_map[$a['thr_parent']][] = [
                            'name' => $a['xchan_name'],
                            'url' => $a['xchan_url'],
                            'photo' => $a['xchan_photo_m'],
                        ];
                    }
                }
            }
        }

        // ── Format and respond ────────────────────────────────────────────────

        $out = array_map(
            fn($item) => $this->formatItem($item, $observer_xchan, $repeaters_map),
            $items ?: []
        );
        Response::paginate($out, intval(\App::$pager['start']), $itemspage, $rootCount);
    }
}
