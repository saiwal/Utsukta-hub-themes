<?php
namespace Utsukta\SpaCore\Api\Handlers;

use Utsukta\SpaCore\Api\Concerns\FormatsItems;
use Utsukta\SpaCore\Api\Concerns\ReactionCounts;
use Utsukta\SpaCore\Api\Concerns\FiltersBlockedChannels;
use Utsukta\SpaCore\Api\Response;

class Display
{
    use FormatsItems;
    use FiltersBlockedChannels;

    /** SPA path of the page a non-post thread lives on, or null for a post. */
    private function ownPageUrl(array $target): ?string
    {
        $root = q("SELECT id, uuid, item_type FROM item WHERE mid = '%s' AND uid = %d LIMIT 1",
            dbesc($target['parent_mid']), intval($target['uid']));
        $type = intval($root[0]['item_type'] ?? ITEM_TYPE_POST);
        if (!in_array($type, [ITEM_TYPE_ARTICLE, ITEM_TYPE_CARD, ITEM_TYPE_WEBPAGE], true)) {
            return null;
        }
        $channel = channelx_by_n(intval($target['uid']));
        if (!$channel) {
            return null;
        }
        $nick = $channel['channel_address'];
        if ($type !== ITEM_TYPE_WEBPAGE) {
            return ($type === ITEM_TYPE_ARTICLE ? '/articles/' : '/cards/') . $nick . '/' . $root[0]['uuid'];
        }
        $slug = q("SELECT v FROM iconfig WHERE iid = %d AND cat = 'system' AND k = 'WEBPAGE' LIMIT 1",
            intval($root[0]['id']));
        return $slug ? "/page/$nick/" . implode('/', array_map('rawurlencode',
            explode('/', urldecode(str_replace('%2f', '/', (string) $slug[0]['v']))))) : null;
    }

    public function get(): void
    {
        require_once 'include/items.php';
        require_once 'include/conversation.php';
        require_once 'include/channel.php';

        // As core's Display: a hub closed to the public shows visitors nothing.
        // The public fallback below (stream_perms_api_uids) doesn't check it.
        if (observer_prohibited()) {
            Response::error(403, 'Public access denied');
        }

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
        // Items with wide ACLs (esp. direct messages) have one row per local
        // recipient sharing the same uuid/mid — prefer the viewer's own copy
        // (correct item_unseen/permissions for them) before falling back to
        // an arbitrary row, otherwise an unqualified LIMIT 1 can resolve to
        // e.g. the sender's own copy and silently show the wrong unseen state.
        $target = [];
        if (local_channel()) {
            $target = q("SELECT id, uid, mid, parent_mid, thr_parent, verb,
                                item_type, item_deleted, item_blocked, author_xchan
                         FROM item WHERE $identifier = '%s' AND uid = %d LIMIT 1",
                dbesc($item_hash),
                intval(local_channel()));
        }

        // No copy of your own: take the wall copy (as core's Item::init does),
        // not whichever recipient's row comes first — it is the one whose
        // channel the page is dressed as (channel_nick below).
        if (!$target) {
            $target = q("SELECT id, uid, mid, parent_mid, thr_parent, verb,
                                item_type, item_deleted, item_blocked, author_xchan
                         FROM item WHERE $identifier = '%s' ORDER BY item_wall DESC LIMIT 1",
                dbesc($item_hash));
        }

        if (!$target) {
            Response::error(404, 'Item not found');
        }

        $target_item = $target[0];

        if ($target_item['item_deleted']) {
            Response::error(410, 'Item has been deleted');
        }

        $observer_hash = get_observer_hash();
        $owner_uid     = intval($target_item['uid']);

        // An article, card or webpage (or a comment on one) has its own page;
        // core redirects there, so tell the SPA to. That page checks its own
        // permissions.
        if ($url = $this->ownPageUrl($target_item)) {
            Response::send(['redirect' => $url]);
        }
        // item_normal($owner_uid) relaxes the item_blocked filter to include
        // ITEM_MODERATED (pending-moderation) rows, but only when the viewer
        // IS that channel — same "owners see their own pending items" rule
        // core's item_normal() already encodes internally.
        $item_normal   = item_normal($owner_uid);

        // ── Permission check ──────────────────────────────────────────────────
        // 1. Check the item owner's uid directly — works for wall posts on
        //    any channel the observer has view_stream permission for.
        $r = [];

        //    view_stream alone is NOT enough — it gates the channel, not the
        //    post, so item_permissions_sql() must still match the row's ACL
        //    (anonymous → item_private = 0), otherwise DMs and other private
        //    posts leak to anyone holding the permalink.
        if ($owner_uid) {
            $perms = get_all_perms($owner_uid, $observer_hash);
            if ($perms['view_stream']) {
                $owner_permission_sql = item_permissions_sql($owner_uid, $observer_hash);
                $r = q("SELECT item.id AS item_id FROM item
                        WHERE uid = %d AND mid = '%s'
                        $owner_permission_sql $item_normal LIMIT 1",
                    $owner_uid,
                    dbesc($target_item['parent_mid']));
            }
        }

        // 2. Fallback — check logged-in user's own stream copy. A delayed item
        //    in one's own stream can only be one's own scheduled post (delayed
        //    items never federate), so the relaxed filter is safe here.
        if (!$r && local_channel()) {
            $item_normal_own = item_normal(local_channel());
            $r = q("SELECT item.id AS item_id FROM item
                    WHERE uid = %d AND mid = '%s' $item_normal_own LIMIT 1",
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

        // ── Fetch root item ──────────────────────────────────────────────────
        // Comments are fetched separately via Item::getComments (paginated /
        // sibling-window "around" modes) — this endpoint only resolves the root.
        $ids = ids_to_querystr($r, 'item_id');

        $items = dbq("SELECT item.*, " . ReactionCounts::subqueries() . "
            FROM item
            WHERE item.id IN ($ids)
            $item_normal");

        if (!$items) {
            Response::error(404, 'Thread not found');
        }

        xchan_query($items, true);
        $items = fetch_post_tags($items, true);

        // ── Viewer following state ────────────────────────────────────────────
        $this->applyViewerFollowing($items, $observer_hash);

        $blocked = $this->blockedXchans(local_channel());
        $item    = $items[0];

        $root_item = $this->formatItem($item, $observer_hash, $this->isPinnedItem($item));

        // Root stays visible even if blocked — flagged so the frontend can
        // swap in a placeholder instead of hard-hiding a direct permalink.
        $root_item['blocked'] = $this->isBlockedHash($blocked, $root_item['author']['hash'] ?? null)
            || (!empty($root_item['owner']) && $this->isBlockedHash($blocked, $root_item['owner']['hash'] ?? null))
            || $this->bodySharesBlocked($item['body'] ?? '', $blocked, intval(local_channel()));

        // Was this thread pulled in by URL (Search.php) rather than delivered?
        // A delivered thread arrives with its comments and keeps receiving them;
        // a fetched one is a snapshot, so only it should offer "fetch more
        // replies". Read here rather than in formatItem() — this is one item,
        // and a per-row iconfig lookup would add a query per post in a stream.
        $root_item['imported'] = (bool) \Zotlabs\Lib\IConfig::Get(intval($item['id']), 'spa', 'imported');

        // A post on a local channel's wall names that channel, so the SPA can
        // dress /display like the channel's own pages (nav, theme, search).
        // Not for a stream copy: that row's uid is merely who received it.
        $wall = intval($item['item_wall']) ? channelx_by_n(intval($item['uid'])) : null;
        $root_item['channel_nick'] = $wall ? $wall['channel_address'] : null;

        Response::send([
            'post' => $root_item,
        ]);
    }
}
