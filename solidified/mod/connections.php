<?php
namespace Zotlabs\Module;

use App;

class Connections_api extends \Zotlabs\Web\Controller
{
    function get()
    {
        if (($_GET['format'] ?? '') !== 'json')
            return;

        if (!local_channel()) {
            json_return_and_die(['error' => 'Permission denied']);
        }

        require_once ('include/socgraph.php');

        $uid = local_channel();

        $filter = $_GET['filter'] ?? 'active';
        $search = trim($_GET['search'] ?? '');
        $gid = intval($_GET['gid'] ?? 0);

        $sql_extra = '';
        switch ($filter) {
            case 'active':
                $sql_extra = ' AND abook_blocked = 0 AND abook_ignored = 0
                               AND abook_hidden = 0 AND abook_archived = 0
                               AND abook_not_here = 0 ';
                break;
            case 'recent':
                $sql_extra = ' AND abook_blocked = 0 AND abook_ignored = 0
                               AND abook_hidden = 0 AND abook_archived = 0
                               AND abook_not_here = 0
                               AND xchan.xchan_updated > UTC_TIMESTAMP() - INTERVAL 7 DAY ';
                break;
            case 'pending':
                $sql_extra = ' AND abook_pending = 1 AND abook_ignored = 0 ';
                break;
            case 'blocked':
                $sql_extra = ' AND abook_blocked = 1 ';
                break;
            case 'ignored':
                $sql_extra = ' AND abook_ignored = 1 ';
                break;
            case 'hidden':
                $sql_extra = ' AND abook_hidden = 1 ';
                break;
            case 'archived':
                $sql_extra = ' AND (abook_archived = 1 OR abook_not_here = 1) ';
                break;
            case 'all':
            default:
                $sql_extra = '';
                break;
        }

        if ($search) {
            $sql_extra .= " AND xchan_name LIKE '%"
                . protect_sprintf(dbesc($search)) . "%' ";
        }

        if ($gid) {
            $sql_extra .= ' AND xchan_hash IN (
                SELECT xchan FROM pgrp_member
                WHERE gid = ' . intval($gid) . '
                  AND uid = ' . intval($uid) . '
            ) ';
        }

        $order_param = $_GET['order'] ?? 'name';
        $sql_order = match ($order_param) {
            'name_desc'      => 'xchan_name DESC',
            'connected'      => 'abook_created ASC',
            'connected_desc' => 'abook_created DESC',
            'recent'         => 'xchan.xchan_updated DESC',
            default          => 'xchan_name ASC',
        };

        $limit  = max(1, min(50, intval($_GET['limit'] ?? 20)));
        $offset = max(0, intval($_GET['start'] ?? 0));

        $count_r = q("SELECT COUNT(abook.abook_id) AS total
                      FROM abook
                      LEFT JOIN xchan ON abook.abook_xchan = xchan.xchan_hash
                      WHERE abook_channel = %d
                        AND abook_self = 0
                        AND xchan_deleted = 0
                        AND xchan_orphan = 0
                        $sql_extra",
            intval($uid));
        $total = intval($count_r[0]['total'] ?? 0);

        $r = q("SELECT abook.abook_id, abook.abook_created, abook.abook_pending,
                       abook.abook_blocked, abook.abook_ignored, abook.abook_hidden,
                       abook.abook_archived, abook.abook_not_here, abook.abook_closeness,
                       abook.abook_role,
                       xchan.xchan_hash, xchan.xchan_name, xchan.xchan_addr,
                       xchan.xchan_url, xchan.xchan_photo_m, xchan.xchan_network,
                       xchan.xchan_pubforum, xchan.xchan_updated
                FROM abook
                LEFT JOIN xchan ON abook.abook_xchan = xchan.xchan_hash
                WHERE abook_channel = %d
                  AND abook_self = 0
                  AND xchan_deleted = 0
                  AND xchan_orphan = 0
                  $sql_extra
                ORDER BY $sql_order
                LIMIT %d OFFSET %d",
            intval($uid),
            $limit,
            $offset);

        $connections = [];
        foreach (($r ?: []) as $row) {
            $status = array_values(array_filter([
                intval($row['abook_pending'])  ? 'pending'  : null,
                intval($row['abook_blocked'])  ? 'blocked'  : null,
                intval($row['abook_ignored'])  ? 'ignored'  : null,
                intval($row['abook_hidden'])   ? 'hidden'   : null,
                intval($row['abook_archived']) ? 'archived' : null,
                intval($row['abook_not_here']) ? 'not_here' : null,
            ]));

            $connections[] = [
                'id'         => intval($row['abook_id']),
                'xchan_hash' => $row['xchan_hash'],
                'name'       => $row['xchan_name'],
                'address'    => $row['xchan_addr'],
                'url'        => $row['xchan_url'],
                'photo'      => $row['xchan_photo_m'],
                'network'    => $row['xchan_network'],
                'is_forum'   => (bool) intval($row['xchan_pubforum']),
                'connected'  => $row['abook_created'],
                'last_seen'  => $row['xchan_updated'],
                'closeness'  => intval($row['abook_closeness']),
                'role'       => $row['abook_role'] ?? '',
                'status'     => $status,
                'pending'    => (bool) intval($row['abook_pending']),
            ];
        }

        json_return_and_die([
            'meta' => [
                'total'  => $total,
                'limit'  => $limit,
                'offset' => $offset,
                'filter' => $filter,
                'order'  => $order_param,
            ],
            'connections' => $connections,
        ]);
    }
}
