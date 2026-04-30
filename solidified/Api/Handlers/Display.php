<?php
namespace Theme\Solidified\Api\Handlers;

use Theme\Solidified\Api\Concerns\FormatsItems;
use Theme\Solidified\Api\Response;

class Display
{
    use FormatsItems;

    public function get(): void
    {
        require_once 'include/items.php';
        require_once 'include/conversation.php';
        require_once 'include/channel.php';

        $item_hash = \App::$argv[2] ?? null;
        if (!$item_hash) {
            Response::error(400, 'No item specified');
        }

        $identifier = 'uuid';
        if (str_starts_with($item_hash, 'b64.')) {
            $item_hash = unpack_link_id($item_hash);
            $identifier = 'mid';
        }

        if ($item_hash === false) {
            Response::error(400, 'Malformed item id');
        }

        // ── Find target item ──────────────────────────────────────────────────
        $target = q("SELECT id, uid, mid, parent_mid, thr_parent, verb,
                            item_type, item_deleted, item_blocked, author_xchan
                     FROM item WHERE $identifier = '%s' LIMIT 1",
            dbesc($item_hash));

        if (!$target) {
            Response::error(404, 'Item not found');
        }

        $target_item = $target[0];

        if ($target_item['item_deleted']) {
            Response::error(410, 'Item has been deleted');
        }

        $observer_hash = get_observer_hash();
        $item_normal   = item_normal();

        // ── Permission check ──────────────────────────────────────────────────
        // 1. Check the item owner's uid directly — works for wall posts on
        //    any channel the observer has view_stream permission for.
        $owner_uid = intval($target_item['uid']);
        $r = [];

        if ($owner_uid) {
            $perms = get_all_perms($owner_uid, $observer_hash);
            if ($perms['view_stream']) {
                $r = q("SELECT item.id AS item_id FROM item
                        WHERE uid = %d AND mid = '%s' $item_normal LIMIT 1",
                    $owner_uid,
                    dbesc($target_item['parent_mid']));
            }
        }

        // 2. Fallback — check logged-in user's own stream copy
        if (!$r && local_channel()) {
            $r = q("SELECT item.id AS item_id FROM item
                    WHERE uid = %d AND mid = '%s' $item_normal LIMIT 1",
                intval(local_channel()),
                dbesc($target_item['parent_mid']));
        }

        // 3. Fallback — public/network permission check
        if (!$r) {
            $sys    = get_sys_channel();
            $sys_id = perm_is_allowed($sys['channel_id'], $observer_hash, 'view_stream')
                ? $sys['channel_id'] : 0;

            $permission_sql = item_permissions_sql(0, $observer_hash);
            $perms_flag     = $observer_hash
                ? (PERMS_NETWORK | PERMS_PUBLIC)
                : PERMS_PUBLIC;

            $r = q("SELECT item.id AS item_id FROM item
                    WHERE ((mid = '%s'
                      AND (((item.allow_cid = '' AND item.allow_gid = ''
                           AND item.deny_cid  = '' AND item.deny_gid  = ''
                           AND item_private = 0)
                           AND uid IN (" . stream_perms_api_uids($perms_flag) . "))
                      OR uid = %d))
                    OR (mid = '%s' $permission_sql))
                    $item_normal LIMIT 1",
                dbesc($target_item['parent_mid']),
                intval($sys_id),
                dbesc($target_item['parent_mid']));
        }

        if (!$r) {
            Response::error(403, 'Permission denied');
        }

        // ── Fetch thread ──────────────────────────────────────────────────────
        $ids = ids_to_querystr($r, 'item_id');

        $items = dbq("SELECT item.*,
            (SELECT COUNT(*) FROM item r WHERE r.parent = item.parent AND r.thr_parent = item.mid AND r.verb = 'Like'    AND r.item_deleted = 0) AS like_count,
            (SELECT COUNT(*) FROM item r WHERE r.parent = item.parent AND r.thr_parent = item.mid AND r.verb = 'Dislike' AND r.item_deleted = 0) AS dislike_count,
            (SELECT COUNT(*) FROM item r WHERE r.parent = item.parent AND r.thr_parent = item.mid AND r.verb = '" . ACTIVITY_SHARE . "' AND r.item_deleted = 0) AS announce_count,
            (SELECT COUNT(*) FROM item r WHERE r.parent = item.id     AND r.item_thread_top = 0   AND r.item_deleted = 0) AS comment_count,
            (SELECT GROUP_CONCAT(verb, ':', author_xchan SEPARATOR '|')
             FROM item r
             WHERE r.parent = item.parent
               AND r.thr_parent = item.mid
               AND r.verb IN ('Like','Dislike','Announce')
               AND r.item_deleted = 0) AS reaction_verbs
            FROM item
            WHERE item.id IN ($ids)
            OR (item.parent IN ($ids)
                AND item.verb IN ('Create', 'Update', 'EmojiReact')
                AND item.obj_type NOT IN ('Answer')
                AND item.item_thread_top = 0
                $item_normal)
            ORDER BY item.created ASC");

        if (!$items) {
            Response::error(404, 'Thread not found');
        }

        xchan_query($items, true);
        $items = fetch_post_tags($items, true);

        // ── Split root from comments ──────────────────────────────────────────
        $root_item = null;
        $comments  = [];

        foreach ($items as $item) {
            if (intval($item['item_thread_top'])) {
                $root_item = $this->formatItem($item, $observer_hash);
            } else {
                $comments[] = $this->formatItem($item, $observer_hash);
            }
        }

        if (!$root_item) {
            Response::error(404, 'Root item not found');
        }

        Response::send([
            'post'     => $root_item,
            'comments' => $comments,
        ]);
    }
}
