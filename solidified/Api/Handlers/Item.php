<?php
// extend/theme/utsukta-themes/solidified/Api/Handlers/Item.php

namespace Theme\Solidified\Api\Handlers;

require_once ('include/items.php');
require_once ('include/conversation.php');
require_once ('include/security.php');
require_once ('include/crypto.php');

use Zotlabs\Daemon\Master;
use Zotlabs\Lib\Libsync;
use App;

class Item
{
    // ── Entry points ──────────────────────────────────────────────────────────
    //
    // GET  /api/item                         -> 400
    // GET  /api/item/:mid                    -> item + thread root details
    // GET  /api/item/:mid/comments           -> all comments
    // GET  /api/item/:mid/comments/:count    -> recent N comments
    // GET  /api/item/:mid/likes              -> who liked
    // GET  /api/item/:mid/dislikes           -> who disliked
    // GET  /api/item/:mid/repeats            -> who repeated
    //
    // POST /api/item/:mid/like               -> toggle like
    // POST /api/item/:mid/dislike            -> toggle dislike
    // POST /api/item/:mid/repeat             -> toggle repeat
    // POST /api/item/:mid/star               -> toggle starred
    // POST /api/item/:mid/comment            -> post a comment
    // POST /api/item/:mid/delete             -> delete item
    // POST /api/item/:mid/edit               -> edit item body/title
    // POST /api/item                         -> create new top-level post
    // POST /api/item/:mid                    -> (same as comment — alias)

    public function get(): void
    {
        $mid = App::$argv[2] ?? '';
        $verb = App::$argv[3] ?? '';

        if (!$mid) {
            json_return_and_die(['error' => 'mid required']);
        }

        switch ($verb) {
            case 'comments':
                $count = App::$argv[4] ?? 'all';
                $this->getComments($mid, $count);
                break;
            case 'likes':
                $this->getReactions($mid, 'Like');
                break;
            case 'dislikes':
                $this->getReactions($mid, 'Dislike');
                break;
            case 'repeats':
                $this->getReactions($mid, ACTIVITY_SHARE);
                break;
            default:
                $this->getItem($mid);
                break;
        }
    }

    public function post(): void
    {
        $mid = App::$argv[2] ?? '';
        $verb = App::$argv[3] ?? '';

        // POST /api/item  (no mid) → create top-level post
        if (!$mid) {
            $this->createPost();
            return;
        }

        switch ($verb) {
            case 'like':
                $this->toggleReaction($mid, 'Like');
                break;
            case 'dislike':
                $this->toggleReaction($mid, 'Dislike');
                break;
            case 'repeat':
                $this->toggleReaction($mid, ACTIVITY_SHARE);
                break;
            case 'star':
                $this->toggleStar($mid);
                break;
            case 'comment':
                $this->createComment($mid);
                break;
            case 'delete':
                $this->deleteItem($mid);
                break;
            case 'edit':
                $this->editItem($mid);
                break;
            default:
                // POST /api/item/:mid  with no verb → comment (convenience alias)
                $this->createComment($mid);
                break;
        }
    }

    // =========================================================================
    // GET handlers
    // =========================================================================

    // GET /api/item/:mid
    // Returns the thread root item. Comments are NOT inlined — fetch separately.
    private function getItem(string $mid): void
    {
        $ob_hash = get_observer_hash();
        $item_normal = item_normal();

        $item = $this->resolveItem($mid, $ob_hash);
        if (!$item) {
            json_return_and_die(['error' => 'Item not found or permission denied']);
        }

        // Re-fetch with reaction subqueries now that we have the parent id
        $iid = intval($item['id']);
        $rows = dbq('SELECT item.*,
            ' . self::reactionSubqueries() . "
            FROM item
            WHERE item.id = $iid
            $item_normal
            LIMIT 1");

        if (!$rows) {
            json_return_and_die(['error' => 'Item not found']);
        }

        xchan_query($rows, true);
        $rows = fetch_post_tags($rows, true);

        json_return_and_die(['item' => self::formatItem($rows[0], $ob_hash)]);
    }

    // GET /api/item/:mid/comments
    // GET /api/item/:mid/comments/:count   (:count = integer or "all")
    private function getComments(string $mid, string $count): void
    {
        $ob_hash = get_observer_hash();
        $item_normal = item_normal();

        $root = $this->resolveItem($mid, $ob_hash);
        if (!$root) {
            json_return_and_die(['error' => 'Item not found or permission denied']);
        }

        $rootMid = dbesc($root['mid']);
        $limit = ($count === 'all' || !is_numeric($count))
            ? ''
            : ' LIMIT ' . max(1, intval($count));

        // Fetch direct replies — verb Create/Update only, not reactions
        $rows = dbq('SELECT item.*,
            ' . self::reactionSubqueries() . "
            FROM item
            WHERE item.thr_parent = '$rootMid'
              AND item.verb IN ('Create', 'Update', 'EmojiReact')
              AND item.item_thread_top = 0
              $item_normal
            ORDER BY item.created ASC
            $limit");

        if ($rows) {
            xchan_query($rows, true);
            $rows = fetch_post_tags($rows, true);
        }

        $comments = array_map(
            fn($row) => self::formatItem($row, $ob_hash),
            $rows ?: []
        );

        json_return_and_die([
            'mid' => $root['mid'],
            'total' => count($comments),
            'comments' => $comments,
        ]);
    }

    // GET /api/item/:mid/likes|dislikes|repeats
    // Returns the xchan profiles of reactors — useful for "who liked this" popups
    private function getReactions(string $mid, string $activityVerb): void
    {
        $ob_hash = get_observer_hash();
        $item_normal = item_normal();

        $root = $this->resolveItem($mid, $ob_hash);
        if (!$root) {
            json_return_and_die(['error' => 'Item not found or permission denied']);
        }

        $rootMid = dbesc($root['mid']);
        $verb = dbesc($activityVerb);

        $rows = dbq("SELECT item.id, item.author_xchan, item.created
                     FROM item
                     WHERE item.thr_parent = '$rootMid'
                       AND item.verb = '$verb'
                       AND item.item_deleted = 0
                       $item_normal
                     ORDER BY item.created ASC");

        if (!$rows) {
            json_return_and_die(['reactions' => [], 'total' => 0]);
        }

        xchan_query($rows, true);

        $out = array_map(fn($r) => [
            'name' => $r['author']['xchan_name'] ?? '',
            'address' => $r['author']['xchan_addr'] ?? '',
            'url' => $r['author']['xchan_url'] ?? '',
            'photo' => $r['author']['xchan_photo_m'] ?? '',
            'created' => $r['created'],
        ], $rows);

        json_return_and_die(['reactions' => $out, 'total' => count($out)]);
    }

    // =========================================================================
    // POST handlers
    // =========================================================================

    // POST /api/item
    // Body: { profile_uid, body, title?, scope? }
    // scope: "public" | "contacts" | "private"
    private function createPost(): void
    {
        $this->requireLocalChannel();
        $this->requireCsrf();

        $body = \Auth::$parsedBody ?? [];
        $uid = local_channel();

        $content = trim($body['body'] ?? '');
        $title = trim($body['title'] ?? '');
        $profileUid = intval($body['profile_uid'] ?? $uid);
        $scope = $body['scope'] ?? 'public';
        $mimetype = $body['mimetype'] ?? 'text/bbcode';

        if (!$content) {
            json_return_and_die(['error' => 'body is required']);
        }

        $acl = self::scopeToAcl($scope, $profileUid);

        $datarray = self::buildItemArray(
            profileUid: $profileUid,
            content: $content,
            title: $title,
            mimetype: $mimetype,
            acl: $acl,
            isWall: true,
        );

        $post = item_store($datarray);

        if (!$post['success']) {
            json_return_and_die(['error' => 'Failed to create post']);
        }

        Master::Summon(['Notifier', 'wall-new', $post['item_id']]);

        json_return_and_die([
            'success' => true,
            'iid' => $post['item_id'],
            'mid' => $datarray['mid'],
            'uuid' => $datarray['uuid'],
        ]);
    }

    // POST /api/item/:mid/comment  (or POST /api/item/:mid with no verb)
    // Body: { body, title? }
    private function createComment(string $parentMid): void
    {
        $this->requireLocalChannel();
        $this->requireCsrf();

        $body = \Auth::$parsedBody ?? [];
        $content = trim($body['body'] ?? '');

        if (!$content) {
            json_return_and_die(['error' => 'body is required']);
        }

        $ob_hash = get_observer_hash();
        $item_normal = item_normal();

        // Resolve parent
        $parent = $this->resolveItem($parentMid, $ob_hash);
        if (!$parent) {
            json_return_and_die(['error' => 'Parent item not found or permission denied']);
        }

        // Inherit ACL and privacy from parent
        $datarray = self::buildItemArray(
            profileUid: intval($parent['uid']),
            content: $content,
            title: trim($body['title'] ?? ''),
            mimetype: $body['mimetype'] ?? 'text/bbcode',
            acl: [
                'allow_cid' => $parent['allow_cid'],
                'allow_gid' => $parent['allow_gid'],
                'deny_cid' => $parent['deny_cid'],
                'deny_gid' => $parent['deny_gid'],
            ],
            isWall: intval($parent['item_wall']) === 1,
            parent: $parent,
        );

        $post = item_store($datarray);

        if (!$post['success']) {
            json_return_and_die(['error' => 'Failed to post comment']);
        }

        Master::Summon(['Notifier', 'comment-new', $post['item_id']]);

        json_return_and_die([
            'success' => true,
            'iid' => $post['item_id'],
            'mid' => $datarray['mid'],
            'uuid' => $datarray['uuid'],
        ]);
    }

    // POST /api/item/:mid/like|dislike|repeat
    // Toggles: sends the reaction if not present, drops it if already present.
    // Returns: { success, state: "added"|"removed", like_count, ... }
    private function toggleReaction(string $mid, string $activityVerb): void
    {
        $this->requireLocalChannel();
        $this->requireCsrf();

        $uid = local_channel();
        $channel = App::get_channel();
        $ob_hash = $channel['channel_hash'];
        $item_normal = item_normal();

        $target = $this->resolveItem($mid, $ob_hash);
        if (!$target) {
            json_return_and_die(['error' => 'Item not found or permission denied']);
        }

        $verbEsc = dbesc($activityVerb);
        $targetMid = dbesc($target['mid']);
        $obHashEsc = dbesc($ob_hash);

        // Check for existing reaction
        $existing = dbq("SELECT id FROM item
                         WHERE uid = $uid
                           AND verb = '$verbEsc'
                           AND thr_parent = '$targetMid'
                           AND author_xchan = '$obHashEsc'
                           AND item_deleted = 0
                           $item_normal
                         LIMIT 1");

        if ($existing) {
            // Undo
            drop_item($existing[0]['id'], DROPITEM_PHASE1);
            Master::Summon(['Notifier', 'drop', $existing[0]['id']]);
            $state = 'removed';
        } else {
            // Add reaction — construct a minimal reaction item
            $uuid = item_message_id();
            $reactionMid = z_root() . '/item/' . $uuid;
            $now = datetime_convert();

            $datarray = [
                'aid' => $channel['channel_account_id'],
                'uid' => intval($target['uid']),
                'uuid' => $uuid,
                'mid' => $reactionMid,
                'parent_mid' => $target['mid'],
                'thr_parent' => $target['mid'],
                'owner_xchan' => $target['owner_xchan'],
                'author_xchan' => $ob_hash,
                'created' => $now,
                'edited' => $now,
                'commented' => $now,
                'received' => $now,
                'changed' => $now,
                'verb' => $activityVerb,
                'obj_type' => 'Activity',
                'body' => '',
                'title' => '',
                'mimetype' => 'text/bbcode',
                'allow_cid' => $target['allow_cid'],
                'allow_gid' => $target['allow_gid'],
                'deny_cid' => $target['deny_cid'],
                'deny_gid' => $target['deny_gid'],
                'item_private' => intval($target['item_private']),
                'item_wall' => intval($target['item_wall']),
                'item_origin' => 1,
                'item_thread_top' => 0,
                'item_notshown' => 1,
                'plink' => $reactionMid,
                'route' => $target['route'] ?? '',
            ];

            $post = item_store($datarray);
            if (!$post['success']) {
                json_return_and_die(['error' => 'Reaction failed']);
            }
            Master::Summon(['Notifier', 'like', $post['item_id']]);
            $state = 'added';
        }

        // Return fresh counts
        $counts = $this->fetchReactionCounts($target['mid']);
        json_return_and_die(array_merge(['success' => true, 'state' => $state], $counts));
    }

    // POST /api/item/:mid/star
    // Toggles the starred flag on the item (local only — not federated)
    private function toggleStar(string $mid): void
    {
        $this->requireLocalChannel();
        $this->requireCsrf();

        $uid = local_channel();
        $item_normal = item_normal();
        $midEsc = dbesc($mid);

        $item = dbq("SELECT id, item_starred FROM item
                     WHERE mid = '$midEsc' AND uid = $uid
                     $item_normal LIMIT 1");

        if (!$item) {
            json_return_and_die(['error' => 'Item not found']);
        }

        $newState = intval($item[0]['item_starred']) ? 0 : 1;
        $iid = intval($item[0]['id']);

        q('UPDATE item SET item_starred = %d WHERE id = %d AND uid = %d',
            $newState, $iid, $uid);

        json_return_and_die(['success' => true, 'starred' => (bool) $newState]);
    }

    // POST /api/item/:mid/edit
    // Body: { body, title? }
    // Only the item owner can edit.
    private function editItem(string $mid): void
    {
        $this->requireLocalChannel();
        $this->requireCsrf();

        $uid = local_channel();
        $body = \Auth::$parsedBody ?? [];
        $content = trim($body['body'] ?? '');
        $title = trim($body['title'] ?? '');

        if (!$content) {
            json_return_and_die(['error' => 'body is required']);
        }

        $item_normal = item_normal();
        $midEsc = dbesc($mid);

        $item = dbq("SELECT * FROM item
                     WHERE mid = '$midEsc' AND uid = $uid
                     $item_normal LIMIT 1");

        if (!$item) {
            json_return_and_die(['error' => 'Item not found or permission denied']);
        }

        $iid = intval($item[0]['id']);
        $now = datetime_convert();

        q("UPDATE item SET body = '%s', title = '%s', edited = '%s', changed = '%s'
           WHERE id = %d AND uid = %d",
            dbesc($content), dbesc($title), dbesc($now), dbesc($now), $iid, $uid);

        Master::Summon(['Notifier', 'edit_post', $iid]);

        json_return_and_die(['success' => true]);
    }

    // POST /api/item/:mid/delete
    // Owner or admin only. Federated drop.
    private function deleteItem(string $mid): void
    {
        $this->requireLocalChannel();
        $this->requireCsrf();

        $uid = local_channel();
        $ob_hash = get_observer_hash();
        $item_normal = item_normal();
        $midEsc = dbesc($mid);

        $item = dbq("SELECT * FROM item WHERE mid = '$midEsc' $item_normal LIMIT 1");

        if (!$item) {
            json_return_and_die(['error' => 'Item not found']);
        }

        $i = $item[0];

        $can_delete = (
            ($uid && $uid == $i['uid']) ||
            ($ob_hash && in_array($ob_hash, [$i['author_xchan'], $i['owner_xchan']])) ||
            is_site_admin()
        );

        if (!$can_delete) {
            json_return_and_die(['error' => 'Permission denied']);
        }

        drop_item($i['id'], DROPITEM_PHASE1);

        $r = q('SELECT * FROM item WHERE id = %d', intval($i['id']));
        if ($r) {
            xchan_query($r);
            $sync = fetch_post_tags($r);
            Libsync::build_sync_packet($i['uid'], ['item' => [encode_item($sync[0], true)]]);
        }

        tag_deliver($i['uid'], $i['id']);

        if (intval($i['item_wall']) || $i['mid'] !== $i['parent_mid']) {
            Master::Summon(['Notifier', 'drop', $i['id']]);
        }

        json_return_and_die(['success' => true]);
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    // Resolve a mid (or uuid) to a readable item row, permission-checked.
    // Accepts full mid (zot6 URL), short uuid, or b64-encoded mid.
    private function resolveItem(string $mid, string $ob_hash): ?array
    {
        $item_normal = item_normal();

        // b64-encoded mid
        if (str_starts_with($mid, 'b64.')) {
            $mid = unpack_link_id($mid);
        }

        // Try as uuid first (shorter, common in frontend URLs)
        $col = (str_contains($mid, '/') || str_contains($mid, ':'))
            ? 'mid'
            : 'uuid';

        $midEsc = dbesc($mid);

        // Prefer a copy the local user owns
        if (local_channel()) {
            $uid = local_channel();
            $r = dbq("SELECT * FROM item
                      WHERE item.$col = '$midEsc'
                        AND item.uid = $uid
                        $item_normal
                      LIMIT 1");
            if ($r)
                return $r[0];
        }

        // Fall back to any publicly accessible copy
        $permission_sql = item_permissions_sql(0, $ob_hash);
        $r = dbq("SELECT * FROM item
                  WHERE item.$col = '$midEsc'
                    $item_normal
                    $permission_sql
                  ORDER BY item_wall DESC
                  LIMIT 1");

        return $r ? $r[0] : null;
    }

    // Build a minimal item datarray for item_store().
    // Handles both top-level posts and comments.
    private static function buildItemArray(
        int $profileUid,
        string $content,
        string $title,
        string $mimetype,
        array $acl,
        bool $isWall,
        ?array $parent = null,
    ): array {
        $channel = App::get_channel();
        $observer = App::get_observer();
        $uuid = item_message_id();
        $mid = z_root() . '/item/' . $uuid;
        $now = datetime_convert();
        $isComment = $parent !== null;

        $parentMid = $isComment ? $parent['mid'] : $mid;
        $thrParent = $isComment ? $parent['mid'] : $mid;
        $ownerHash = $isComment ? $parent['owner_xchan'] : $channel['channel_hash'];
        $private = !empty($acl['allow_cid']) || !empty($acl['allow_gid']) ? 1 : 0;

        return [
            'aid' => $channel['channel_account_id'],
            'uid' => $profileUid,
            'uuid' => $uuid,
            'mid' => $mid,
            'parent_mid' => $parentMid,
            'thr_parent' => $thrParent,
            'owner_xchan' => $ownerHash,
            'author_xchan' => $observer['xchan_hash'],
            'created' => $now,
            'edited' => $now,
            'commented' => $now,
            'received' => $now,
            'changed' => $now,
            'verb' => 'Create',
            'obj_type' => 'Note',
            'mimetype' => $mimetype,
            'title' => $title,
            'body' => $content,
            'allow_cid' => $acl['allow_cid'] ?? '',
            'allow_gid' => $acl['allow_gid'] ?? '',
            'deny_cid' => $acl['deny_cid'] ?? '',
            'deny_gid' => $acl['deny_gid'] ?? '',
            'item_wall' => $isWall ? 1 : 0,
            'item_origin' => 1,
            'item_thread_top' => $isComment ? 0 : 1,
            'item_unseen' => 0,
            'item_private' => $private,
            'plink' => $mid,
            'route' => $parent['route'] ?? '',
        ];
    }

    // Map a scope string to an ACL array
    private static function scopeToAcl(string $scope, int $profileUid): array
    {
        if ($scope === 'private') {
            $channel = App::get_channel();
            return [
                'allow_cid' => '<' . $channel['channel_hash'] . '>',
                'allow_gid' => '',
                'deny_cid' => '',
                'deny_gid' => '',
            ];
        }
        if ($scope === 'contacts') {
            // Use the channel's configured default ACL
            $r = q('SELECT * FROM channel WHERE channel_id = %d LIMIT 1', $profileUid);
            $acl = new \Zotlabs\Access\AccessList($r ? $r[0] : App::get_channel());
            $g = $acl->get();
            return [
                'allow_cid' => $g['allow_cid'],
                'allow_gid' => $g['allow_gid'],
                'deny_cid' => $g['deny_cid'],
                'deny_gid' => $g['deny_gid'],
            ];
        }
        // public
        return ['allow_cid' => '', 'allow_gid' => '', 'deny_cid' => '', 'deny_gid' => ''];
    }

    // Shared reaction count subqueries string
    private static function reactionSubqueries(): string
    {
        return "(SELECT COUNT(*) FROM item r WHERE r.parent = item.parent AND r.thr_parent = item.mid AND r.verb = 'Like'    AND r.item_deleted = 0) AS like_count,
                (SELECT COUNT(*) FROM item r WHERE r.parent = item.parent AND r.thr_parent = item.mid AND r.verb = 'Dislike' AND r.item_deleted = 0) AS dislike_count,
                (SELECT COUNT(*) FROM item r WHERE r.parent = item.parent AND r.thr_parent = item.mid AND r.verb = '" . ACTIVITY_SHARE . "' AND r.item_deleted = 0) AS announce_count,
                (SELECT COUNT(*) FROM item r WHERE r.parent = item.id    AND r.item_thread_top = 0    AND r.item_deleted = 0) AS comment_count,
                (SELECT GROUP_CONCAT(verb, ':', author_xchan SEPARATOR '|')
                 FROM item r
                 WHERE r.parent = item.parent
                   AND r.thr_parent = item.mid
                   AND r.verb IN ('Like','Dislike','Announce')
                   AND r.item_deleted = 0) AS reaction_verbs";
    }

    // Fetch fresh counts after a toggle — avoids a full item re-fetch
    private function fetchReactionCounts(string $mid): array
    {
        $midEsc = dbesc($mid);
        $r = dbq("SELECT
            (SELECT COUNT(*) FROM item r WHERE r.thr_parent = '$midEsc' AND r.verb = 'Like'    AND r.item_deleted = 0) AS like_count,
            (SELECT COUNT(*) FROM item r WHERE r.thr_parent = '$midEsc' AND r.verb = 'Dislike' AND r.item_deleted = 0) AS dislike_count,
            (SELECT COUNT(*) FROM item r WHERE r.thr_parent = '$midEsc' AND r.verb = '" . ACTIVITY_SHARE . "' AND r.item_deleted = 0) AS announce_count");

        return [
            'like_count' => intval($r[0]['like_count'] ?? 0),
            'dislike_count' => intval($r[0]['dislike_count'] ?? 0),
            'announce_count' => intval($r[0]['announce_count'] ?? 0),
        ];
    }

    // Shared item formatter — same shape as your existing network/channel items
    private static function formatItem(array $item, string $ob_hash): array
    {
        $liked = $disliked = $repeated = false;
        if ($ob_hash && !empty($item['reaction_verbs'])) {
            foreach (explode('|', $item['reaction_verbs']) as $rv) {
                if (!str_contains($rv, ':'))
                    continue;
                [$v, $xchan] = explode(':', $rv, 2);
                if ($xchan !== $ob_hash)
                    continue;
                if ($v === 'Like')
                    $liked = true;
                if ($v === 'Dislike')
                    $disliked = true;
                if ($v === 'Announce')
                    $repeated = true;
            }
        }

        return [
            'uuid' => $item['uuid'],
            'mid' => $item['mid'],
            'parent_mid' => $item['parent_mid'],
            'thr_parent' => $item['thr_parent'],
            'created' => $item['created'],
            'edited' => $item['edited'],
            'title' => $item['title'],
            'body' => $item['body'],
            'verb' => $item['verb'],
            'obj_type' => $item['obj_type'],
            'like_count' => intval($item['like_count'] ?? 0),
            'dislike_count' => intval($item['dislike_count'] ?? 0),
            'announce_count' => intval($item['announce_count'] ?? 0),
            'comment_count' => intval($item['comment_count'] ?? 0),
            'item_private' => intval($item['item_private']),
            'item_thread_top' => intval($item['item_thread_top']),
            'item_unseen' => intval($item['item_unseen'] ?? 0),
            'iid' => intval($item['id']),
            'profile_uid' => intval($item['uid']),
            'flags' => array_values(array_filter([
                intval($item['item_thread_top']) ? 'thread_parent' : null,
                intval($item['item_private']) ? 'private' : null,
                intval($item['item_starred']) ? 'starred' : null,
                intval($item['item_unseen']) ? 'unseen' : null,
            ])),
            'author' => [
                'name' => $item['author']['xchan_name'] ?? '',
                'address' => $item['author']['xchan_addr'] ?? '',
                'url' => $item['author']['xchan_url'] ?? '',
                'photo' => [
                    'src' => $item['author']['xchan_photo_m'] ?? '',
                    'mimetype' => $item['author']['xchan_photo_mimetype'] ?? '',
                ],
            ],
            'permalink' => $item['plink'] ?? '',
            'viewer_liked' => $liked,
            'viewer_disliked' => $disliked,
            'viewer_repeated' => $repeated,
        ];
    }

    // ── Guards ────────────────────────────────────────────────────────────────

    private function requireLocalChannel(): void
    {
        if (!local_channel()) {
            json_return_and_die(['error' => 'Authentication required']);
        }
    }

    private function requireCsrf(): void
    {
        Csrf::validate();
    }
}
