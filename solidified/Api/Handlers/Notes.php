<?php
namespace Theme\Solidified\Api\Handlers;

use Theme\Solidified\Api\Auth;
use Theme\Solidified\Api\Response;

class Notes
{
    // POST /api/notes
    // Body (JSON): { body, mimetype? }
    // Creates a personal note directly via item_store(), bypassing the legacy
    // /item endpoint which would also create a blank companion Add activity.
    public function post(): void
    {
        require_once 'include/items.php';

        Auth::requireLocalJson();

        $uid      = local_channel();
        $channel  = \App::get_channel();
        $observer = \App::get_observer();
        $b        = Auth::$parsedBody;

        $content  = $b['body']     ?? '';
        $mimetype = $b['mimetype'] ?? 'text/bbcode';

        if (!trim($content)) {
            Response::error(400, 'Body is required');
        }

        $uuid = item_message_id();
        $mid  = z_root() . '/item/' . $uuid;
        $now  = datetime_convert();

        $datarray = [
            'aid'             => $channel['channel_account_id'],
            'uid'             => $uid,
            'uuid'            => $uuid,
            'mid'             => $mid,
            'parent_mid'      => $mid,
            'thr_parent'      => $mid,
            'owner_xchan'     => $channel['channel_hash'],
            'author_xchan'    => $observer['xchan_hash'],
            'created'         => $now,
            'edited'          => $now,
            'commented'       => $now,
            'received'        => $now,
            'changed'         => $now,
            'verb'            => 'Create',
            'obj_type'        => 'Note',
            'item_type'       => ITEM_TYPE_CUSTOM,
            'mimetype'        => $mimetype,
            'body'            => $content,
            'plink'           => $mid,
            'allow_cid'       => '',
            'allow_gid'       => '',
            'deny_cid'        => '',
            'deny_gid'        => '',
            'item_wall'       => 1,
            'item_origin'     => 1,
            'item_thread_top' => 1,
            'item_unseen'     => 0,
            'item_private'    => 1,
        ];

        // No federation, no delivery, no notifications
        $result = item_store($datarray, false, false, false);

        if (!$result['success']) {
            Response::error(500, 'Failed to save note');
        }

        Response::send(['mid' => $mid]);
    }

    // GET /api/notes
    // Lists the authenticated user's personal notes (ITEM_TYPE_CUSTOM items).
    // These never appear in streams or federate — the Notifier skips type != 0.
    // Query params: start (int, default 0), limit (int, default 20)
    public function get(): void
    {
        require_once 'include/items.php';

        Auth::requireLocalGet();

        $uid   = local_channel();
        $limit = min(50, max(1, intval($_GET['limit'] ?? 20)));
        $start = max(0, intval($_GET['start'] ?? 0));

        // Use item_normal() with ITEM_TYPE_CUSTOM to get the correct SQL fragment
        $item_normal = item_normal(null, 'item', ITEM_TYPE_CUSTOM);

        $count_r = dbq(
            "SELECT COUNT(*) AS total FROM item
             WHERE item.uid = $uid
               AND item.item_thread_top = 1
               AND item.verb = 'Create'
               $item_normal"
        );
        $total = intval($count_r[0]['total'] ?? 0);

        $rows = dbq(
            "SELECT item.id, item.mid, item.uuid, item.body, item.title,
                    item.created, item.edited, item.mimetype
             FROM item
             WHERE item.uid = $uid
               AND item.item_thread_top = 1
               AND item.verb = 'Create'
               $item_normal
             ORDER BY item.created DESC
             LIMIT $limit OFFSET $start"
        );

        $items = [];
        foreach (($rows ?: []) as $row) {
            $items[] = [
                'id'       => intval($row['id']),
                'mid'      => $row['mid'],
                'uuid'     => $row['uuid'],
                'body'     => $row['body'],
                'created'  => $row['created'],
                'edited'   => $row['edited'],
                'mimetype' => $row['mimetype'],
            ];
        }

        Response::paginate($items, $start, $limit, $total, false);
    }
}
