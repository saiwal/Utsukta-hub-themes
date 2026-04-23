<?php
// extend/theme/utsukta-themes/solidified/Api/Handlers/Network.php

namespace Theme\Solidified\Api\Handlers;

use Theme\Solidified\Api\Response;
use Theme\Solidified\Api\Concerns\FormatsItems;

require_once ('include/items.php');
require_once ('include/conversation.php');
require_once ('include/acl_selectors.php');

class Network
{
    use FormatsItems;

    public function get(): void
    {
        if (!local_channel()) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Permission denied']);
            exit;
        }

        $uid = local_channel();
        $channel = \App::get_channel();
        $item_normal = item_normal();
        $observer_xchan = get_observer_hash();
        $abook_uids = ' and abook.abook_channel = ' . $uid . ' ';
        $uids = ' and item.uid = ' . $uid . ' ';

        // ── Pagination ────────────────────────────────────────────────────────
        $itemspage = intval(get_pconfig($uid, 'system', 'itemspage') ?: 10);
        $offset = max(0, intval($_GET['start'] ?? 0));
        $pager_sql = " LIMIT $itemspage OFFSET $offset ";

        // ── Ordering ──────────────────────────────────────────────────────────
        $saved_order = get_pconfig($uid, 'mod_network', 'order', 'created');
        $get_order = $_GET['order'] ?? $saved_order;

        $nouveau = false;
        $ordering = 'created';

        switch ($get_order) {
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

        // ── Filter params ─────────────────────────────────────────────────────
        $star = intval($_GET['star'] ?? 0);
        $liked = intval($_GET['liked'] ?? 0);
        $conv = intval($_GET['conv'] ?? 0);
        $dm = intval($_GET['dm'] ?? 0);
        $spam = intval($_GET['spam'] ?? 0);
        $nouveau = $nouveau || (bool) intval($_GET['nouveau'] ?? 0);
        $unseen = $_GET['unseen'] ?? '';
        $pf = intval($_GET['pf'] ?? 0);
        $gid = intval($_GET['gid'] ?? 0);
        $cid = intval($_GET['cid'] ?? 0);
        $xchan = $_GET['xchan'] ?? '';
        $net = $_GET['net'] ?? '';
        $search = $_GET['search'] ?? '';
        $hashtags = $_GET['tag'] ?? '';
        $category = $_GET['cat'] ?? '';
        $verb = $_GET['verb'] ?? '';
        $file = $_GET['file'] ?? '';

        $datequery = (isset($_GET['dend']) && is_a_date_arg($_GET['dend']))
            ? notags($_GET['dend'])
            : '';
        $datequery2 = (isset($_GET['dbegin']) && is_a_date_arg($_GET['dbegin']))
            ? notags($_GET['dbegin'])
            : '';

        // Affinity (disabled when app not installed → -1)
        $cmin = array_key_exists('cmin', $_GET) ? intval($_GET['cmin']) : -1;
        $cmax = array_key_exists('cmax', $_GET) ? intval($_GET['cmax']) : -1;

        // Hashtag shorthand in search
        if ($search && str_starts_with($search, '#')) {
            $hashtags = substr($search, 1);
            $search = '';
        }

        // Filters that force nouveau (flat) mode
        if ($search || $file || (!$pf && $cid) || $hashtags || $verb || $category || $conv || $unseen) {
            $nouveau = true;
        }

        if ($datequery) {
            $ordering = 'created';
        }

        // ── SQL fragments ─────────────────────────────────────────────────────
        $sql_options = $star ? ' and item_starred = 1 ' : '';
        $sql_extra = '';
        $item_thread_top = ' AND item_thread_top = 1 ';

        // Privacy group
        if ($gid) {
            $r = q('SELECT * FROM pgrp WHERE id = %d AND uid = %d LIMIT 1',
                intval($gid), $uid);
            if (!$r) {
                self::die(['error' => 'No such group']);
            }
            $group_hash = $r[0]['hash'];
            $contacts = \Zotlabs\Lib\AccessList::members($uid, $gid);
            $contact_str = $contacts ? ids_to_querystr($contacts, 'xchan', true) : " '0' ";

            $item_thread_top = '';
            $sql_extra .= " AND item.parent IN (
                SELECT DISTINCT parent FROM item
                WHERE true $sql_options
                AND (( author_xchan IN ($contact_str) OR owner_xchan IN ($contact_str))
                     OR allow_gid LIKE '" . protect_sprintf('%<' . dbesc($group_hash) . '>%') . "')
                AND id = parent $item_normal
            ) ";
        }

        // Abook contact
        if ($cid) {
            $cid_r = q('SELECT abook_xchan FROM abook
                        WHERE abook_id = %d AND abook_channel = %d AND abook_blocked = 0 LIMIT 1',
                intval($cid), $uid);
            if (!$cid_r) {
                self::die(['error' => 'No such channel']);
            }
            $cid_xchan = $cid_r[0]['abook_xchan'];
            $item_thread_top = '';

            if (!$pf && $nouveau) {
                $sql_extra .= " AND author_xchan = '" . dbesc($cid_xchan) . "' ";
            } else {
                $sql_extra .= " AND item.parent IN (
                    SELECT DISTINCT parent FROM item
                    WHERE uid = $uid
                    AND ( author_xchan = '" . dbesc($cid_xchan) . "'
                       OR owner_xchan  = '" . dbesc($cid_xchan) . "')
                    $item_normal
                ) ";
            }
        }

        // xchan
        if ($xchan) {
            $item_thread_top = '';
            $sql_extra .= " AND item.parent IN (
                SELECT DISTINCT parent FROM item
                WHERE true $sql_options AND uid = $uid
                AND ( author_xchan = '" . dbesc($xchan) . "'
                   OR owner_xchan  = '" . dbesc($xchan) . "')
                $item_normal
            ) ";
        }

        // Category / hashtag / search / verb / file
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
        if ($verb) {
            if (str_starts_with($verb, '.')) {
                $sql_extra .= sprintf(
                    " AND item.obj_type = '%s' AND item.verb IN ('Create','Update','Invite') ",
                    dbesc(protect_sprintf(substr($verb, 1)))
                );
            } else {
                $sql_extra .= sprintf(
                    " AND item.verb = '%s' ",
                    dbesc(protect_sprintf($verb))
                );
            }
        }
        if ($file) {
            $sql_extra .= term_query('item', $file, TERM_FILE);
        }

        // Privacy fence
        $dismiss_privacy_filter = array_intersect(
            ['cid', 'star', 'conv', 'file', 'verb', 'cat', 'search'],
            array_keys($_GET)
        );
        if (!$dismiss_privacy_filter) {
            $sql_extra .= $dm
                ? ' AND item.item_private = 2 '
                : ' AND item.item_private IN (0, 1) ';
        }

        // Conversation (mentions + authored)
        if ($conv) {
            $item_thread_top = '';
            $sql_extra .= " AND ( author_xchan = '" . dbesc($channel['channel_hash']) . "'"
                . ' OR item_mentionsme = 1 ) ';
        }

        // Unseen
        if ($unseen) {
            $sql_extra .= ' AND item_unseen = 1 ';
        }

        // Liked threads
        if ($liked) {
            $item_thread_top = '';
            $sql_extra .= " AND item.parent IN (
                SELECT DISTINCT parent FROM item
                WHERE uid = $uid AND verb = 'Like'
                AND author_xchan = '" . dbesc($channel['channel_hash']) . "'
                $item_normal
            ) ";
        }

        // Spam
        if ($spam) {
            $sql_extra .= ' AND item_spam = 1 ';
        }

        // Date range
        $sql_date = '';
        if ($datequery) {
            $sql_date .= " AND item.created <= '"
                . dbesc(datetime_convert(date_default_timezone_get(), '', $datequery)) . "' ";
        }
        if ($datequery2) {
            $sql_date .= " AND item.created >= '"
                . dbesc(datetime_convert(date_default_timezone_get(), '', $datequery2)) . "' ";
        }
        // In threaded mode date filter goes on the parent query only
        $sql_extra3 = $nouveau ? '' : $sql_date;

        // Affinity
        $sql_nets = '';
        if ($cmin !== -1 || $cmax !== -1) {
            $sql_nets .= ' AND ';
            if ($cmax === 99)
                $sql_nets .= ' ( ';
            $sql_nets .= "( abook.abook_closeness >= $cmin AND abook.abook_closeness <= $cmax ) ";
            if ($cmax === 99)
                $sql_nets .= ' OR abook.abook_closeness IS NULL ) ';
        }

        // Network / protocol filter
        $net_query = $net ? ' left join xchan on xchan_hash = author_xchan ' : '';
        $net_query2 = $net ? " and xchan_network = '" . protect_sprintf(dbesc($net)) . "' " : '';

        // ── Shared reaction subqueries ─────────────────────────────────────────
        $reaction_subqueries = "
            (SELECT COUNT(*) FROM item r WHERE r.parent = item.parent AND r.thr_parent = item.mid AND r.verb = 'Like'    AND r.item_deleted = 0) AS like_count,
            (SELECT COUNT(*) FROM item r WHERE r.parent = item.parent AND r.thr_parent = item.mid AND r.verb = 'Dislike' AND r.item_deleted = 0) AS dislike_count,
            (SELECT COUNT(*) FROM item r WHERE r.parent = item.parent AND r.thr_parent = item.mid AND r.verb = '" . ACTIVITY_SHARE . "' AND r.item_deleted = 0) AS announce_count,
            (SELECT COUNT(*) FROM item r WHERE r.parent = item.id    AND r.item_thread_top = 0    AND r.item_deleted = 0) AS comment_count,
            (SELECT GROUP_CONCAT(verb, ':', author_xchan SEPARATOR '|')
             FROM item r
             WHERE r.parent = item.parent
               AND r.thr_parent = item.mid
               AND r.verb IN ('Like','Dislike','Announce')
               AND r.item_deleted = 0) AS reaction_verbs";

        // ── Fetch items ───────────────────────────────────────────────────────
        $items = [];
        $rootCount = 0;

        if ($nouveau) {
            // Flat / unthreaded
            $items = dbq("SELECT item.*, item.id AS item_id, $reaction_subqueries
                FROM item
                LEFT JOIN abook ON ( item.owner_xchan = abook.abook_xchan $abook_uids )
                $net_query
                WHERE true $uids $item_normal
                AND (abook.abook_blocked = 0 OR abook.abook_flags IS NULL)
                AND item.verb NOT IN ('Add', 'Remove')
                $sql_extra $sql_options $sql_nets $sql_date
                $net_query2
                ORDER BY item.created DESC $pager_sql");

            $rootCount = count($items ?: []);

            if ($items) {
                xchan_query($items, true);
                $items = fetch_post_tags($items, true);
            }
        } else {
            // Threaded — two-step: parent ids then full threads
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
        $out = [];
        $out = array_map(
            fn($item) => $this->formatItem($item, $observer_xchan),
            $items
        );

        header('Content-Type: application/json');
        Response::send($out, [
            'offset' => $offset,
            'limit' => $itemspage,
            'nouveau' => $nouveau,
            'has_more' => count($out) >= $itemspage,
            'ordering' => $ordering,
            'count' => count($out),
        ]);
    }


}
