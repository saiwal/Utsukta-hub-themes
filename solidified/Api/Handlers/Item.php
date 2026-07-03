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
use Theme\Solidified\Api\Auth;
use Theme\Solidified\Api\Response;

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
        // Hubzilla message IDs are full URLs (e.g. https://host/item/uuid) that
        // contain "/" characters. When not percent-encoded by the caller, the path
        // is split across multiple argv segments. Reconstruct the mid by detecting
        // the known verb at the end; for comments the optional count sits after it.
        $GET_VERBS = ['comments', 'likes', 'dislikes', 'repeats', 'folders', 'delivery'];
        $segs  = array_slice(App::$argv, 2);
        $n     = count($segs);
        $verb  = '';
        $extra = 'all';

        if ($n >= 2 && in_array($segs[$n - 1], $GET_VERBS, true)) {
            $verb = $segs[$n - 1];
            $mid  = implode('/', array_slice($segs, 0, $n - 1));
        } elseif ($n >= 3 && in_array($segs[$n - 2], $GET_VERBS, true)) {
            // e.g. .../comments/5
            $verb  = $segs[$n - 2];
            $extra = $segs[$n - 1];
            $mid   = implode('/', array_slice($segs, 0, $n - 2));
        } else {
            $mid = implode('/', $segs);
        }

        $mid = self::fixProtocolSlashes($mid);

        if (!$mid) {
            json_return_and_die(['error' => 'mid required']);
        }

        switch ($verb) {
            case 'comments':
                $this->getComments($mid, $extra);
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
            case 'folders':
                $this->getItemFolders($mid);
                break;
            case 'delivery':
                $this->getDeliveryReport($mid);
                break;
            default:
                $this->getItem($mid);
                break;
        }
    }

    public function post(): void
    {
        // Reconstruct mid from all argv segments after "item", accounting for
        // message IDs that contain "/" (full zot6 URLs like https://host/item/uuid).
        // The action verb is always the last segment for POST requests.
        $POST_VERBS = ['like', 'dislike', 'repeat', 'accept', 'reject',
                       'tentativeaccept', 'star', 'comment', 'delete',
                       'edit', 'reshare', 'saveto', 'vote'];

        $segs = array_slice(App::$argv, 2);
        $last = count($segs) ? $segs[count($segs) - 1] : '';

        if (in_array($last, $POST_VERBS, true)) {
            $verb = $last;
            $mid  = implode('/', array_slice($segs, 0, -1));
        } else {
            $verb = '';
            $mid  = implode('/', $segs);
        }

        $mid = self::fixProtocolSlashes($mid);

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
            case 'accept':
                $this->toggleRsvpReaction($mid, 'Accept');
                break;
            case 'reject':
                $this->toggleRsvpReaction($mid, 'Reject');
                break;
            case 'tentativeaccept':
                $this->toggleRsvpReaction($mid, 'TentativeAccept');
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
            case 'reshare':
                $this->createReshare($mid);
                break;
            case 'saveto':
                $this->saveToFolder($mid);
                break;
            case 'vote':
                $this->voteOnPoll($mid);
                break;
            default:
                // POST /api/item/:mid with no verb → comment (convenience alias)
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

        $row = $rows[0];
        if ($ob_hash) {
            $pid = intval($row['parent']);
            $obs = dbesc($ob_hash);
            $fr = dbq(
                "SELECT verb FROM item
                 WHERE parent = $pid
                   AND author_xchan = '$obs'
                   AND verb IN ('Follow', 'Ignore')
                   AND item_deleted = 0
                 ORDER BY created DESC LIMIT 1"
            );
            $row['viewer_following'] = !empty($fr) && $fr[0]['verb'] === 'Follow';
        }

        json_return_and_die(['item' => self::formatItem($row, $ob_hash)]);
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

        $rootId  = intval($root['id']);
        $limit = ($count === 'all' || !is_numeric($count))
            ? ''
            : ' LIMIT ' . max(1, intval($count));

        // Fetch all thread children (direct replies + nested) — excludes reactions
        $rows = dbq('SELECT item.*,
            ' . self::reactionSubqueries() . "
            FROM item
            WHERE item.parent = $rootId
              AND item.verb IN ('Create', 'Update', 'EmojiReact')
              AND item.obj_type NOT IN ('Answer')
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

        $deletedStubs = self::findDeletedParentStubs($comments, $root['mid']);

        json_return_and_die([
            'mid'      => $root['mid'],
            'total'    => count($comments),
            'comments' => array_merge($comments, $deletedStubs),
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
        $rootUid = intval($root['uid']);
        $verb = dbesc($activityVerb);

        $rows = dbq("SELECT DISTINCT item.author_xchan, MIN(item.created) AS created
                     FROM item
                     WHERE item.uid = $rootUid
                       AND item.thr_parent = '$rootMid'
                       AND item.verb = '$verb'
                       AND item.item_deleted = 0
                       $item_normal
                     GROUP BY item.author_xchan
                     ORDER BY MIN(item.created) ASC");

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
    // Body: { profile_uid, body, title?, scope?, summary?, category?, expire?,
    //         contact_allow?, group_allow?, contact_deny?, group_deny?,
    //         poll_answers?, poll_expire_value?, poll_expire_unit? }
    // scope: "public" | "contacts" | "private" | "custom"
    // For scope="custom" supply contact_allow/group_allow arrays of xchan hashes/group ids.
    private function createPost(): void
    {
        $uid = Auth::requireLocalJson();
        $body = Auth::$parsedBody;

        $content = trim($body['body'] ?? '');
        $title = trim($body['title'] ?? '');
        $summary = trim($body['summary'] ?? '');
        $category = trim($body['category'] ?? '');
        $profileUid = intval($body['profile_uid'] ?? $uid);
        $scope = $body['scope'] ?? 'public';
        $mimetype = $body['mimetype'] ?? 'text/bbcode';
        $expire = trim($body['expire'] ?? '');

        if (!$content) {
            json_return_and_die(['error' => 'body is required']);
        }

        if ($scope === 'custom') {
            $contactAllow = is_array($body['contact_allow'] ?? null) ? $body['contact_allow'] : [];
            $groupAllow   = is_array($body['group_allow']   ?? null) ? $body['group_allow']   : [];
            $contactDeny  = is_array($body['contact_deny']  ?? null) ? $body['contact_deny']  : [];
            $groupDeny    = is_array($body['group_deny']    ?? null) ? $body['group_deny']    : [];
            $allowCid = '';
            foreach ($contactAllow as $h) $allowCid .= '<' . $h . '>';
            $allowGid = '';
            foreach ($groupAllow  as $g) $allowGid .= '<' . $g . '>';
            $denyCid  = '';
            foreach ($contactDeny as $h) $denyCid  .= '<' . $h . '>';
            $denyGid  = '';
            foreach ($groupDeny   as $g) $denyGid  .= '<' . $g . '>';
            $acl = ['allow_cid' => $allowCid, 'allow_gid' => $allowGid,
                    'deny_cid'  => $denyCid,  'deny_gid'  => $denyGid];
        } else {
            $acl = self::scopeToAcl($scope, $profileUid);
        }

        $datarray = self::buildItemArray(
            profileUid: $profileUid,
            content: $content,
            title: $title,
            mimetype: $mimetype,
            acl: $acl,
            isWall: true,
        );

        if ($summary) {
            $datarray['summary'] = $summary;
        }

        if ($expire) {
            $expires = datetime_convert(date_default_timezone_get(), 'UTC', $expire);
            if ($expires > datetime_convert()) {
                $datarray['expires'] = $expires;
            }
        }

        // Polls
        $pollAnswers = $body['poll_answers'] ?? null;
        if (is_array($pollAnswers)) {
            $answers = array_values(array_filter(array_map(fn($a) => escape_tags(trim($a)), $pollAnswers)));
            if (count($answers) >= 2) {
                $expireValue = max(1, intval($body['poll_expire_value'] ?? 1));
                $expireUnit  = in_array($body['poll_expire_unit'] ?? 'Days', ['Minutes','Hours','Days','Weeks'], true)
                    ? $body['poll_expire_unit']
                    : 'Days';
                $opts = array_map(
                    fn($a) => ['name' => $a, 'type' => 'Note', 'replies' => ['type' => 'Collection', 'totalItems' => 0]],
                    $answers
                );
                $pollEndTime = datetime_convert(date_default_timezone_get(), 'UTC',
                    'now + ' . $expireValue . ' ' . $expireUnit, ATOM_TIME);
                $channel = App::get_channel();
                $pollObj = [
                    'type'         => 'Question',
                    'id'           => $datarray['mid'],
                    'url'          => $datarray['mid'],
                    'attributedTo' => channel_url($channel),
                    'content'      => bbcode($content),
                    'name'         => $title ?: '',
                    'oneOf'        => $opts,
                    'endTime'      => $pollEndTime,
                    'to'           => [ACTIVITY_PUBLIC_INBOX],
                ];
                $datarray['obj_type'] = 'Question';
                $datarray['obj']      = $pollObj;
                if (empty($datarray['expires'])) {
                    $datarray['expires'] = datetime_convert('UTC', 'UTC', $pollEndTime);
                }
            }
        }

        $post = item_store($datarray);

        if (!$post['success']) {
            json_return_and_die(['error' => 'Failed to create post']);
        }

        if ($category) {
            $cats = array_filter(array_map('trim', explode(',', $category)));
            foreach ($cats as $cat) {
                store_item_tag($profileUid, $post['item_id'], TERM_OBJ_POST, TERM_CATEGORY, $cat, '');
            }
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
        Auth::requireLocalJson();
        $body = Auth::$parsedBody;
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

    // POST /api/item/:mid/accept|reject|tentativeaccept
    // Exclusive RSVP toggle: removes conflicting RSVP verbs, toggles the chosen one.
    private function toggleRsvpReaction(string $mid, string $activityVerb): void
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

        $obHashEsc = dbesc($ob_hash);
        $targetMid = dbesc($target['mid']);

        // Find any existing RSVP from this viewer on this item
        $existing = dbq("SELECT id, verb FROM item
                         WHERE uid = $uid
                           AND verb IN ('Accept','Reject','TentativeAccept')
                           AND thr_parent = '$targetMid'
                           AND author_xchan = '$obHashEsc'
                           AND item_deleted = 0
                           $item_normal
                         LIMIT 1");

        $state = 'added';

        if ($existing) {
            // Always remove the old RSVP first
            drop_item($existing[0]['id'], DROPITEM_PHASE1);
            Master::Summon(['Notifier', 'drop', $existing[0]['id']]);

            // If same verb, this is a toggle-off
            if ($existing[0]['verb'] === $activityVerb) {
                $state = 'removed';
                $counts = $this->fetchReactionCounts($target['mid']);
                json_return_and_die(array_merge(['success' => true, 'state' => $state], $counts));
            }
        }

        // Add the new RSVP reaction
        $uuid        = item_message_id();
        $reactionMid = z_root() . '/item/' . $uuid;
        $now         = datetime_convert();

        $datarray = [
            'aid'            => $channel['channel_account_id'],
            'uid'            => intval($target['uid']),
            'uuid'           => $uuid,
            'mid'            => $reactionMid,
            'parent_mid'     => $target['mid'],
            'thr_parent'     => $target['mid'],
            'owner_xchan'    => $target['owner_xchan'],
            'author_xchan'   => $ob_hash,
            'created'        => $now,
            'edited'         => $now,
            'commented'      => $now,
            'received'       => $now,
            'changed'        => $now,
            'verb'           => $activityVerb,
            'obj_type'       => 'Activity',
            'body'           => '',
            'title'          => '',
            'mimetype'       => 'text/bbcode',
            'allow_cid'      => $target['allow_cid'],
            'allow_gid'      => $target['allow_gid'],
            'deny_cid'       => $target['deny_cid'],
            'deny_gid'       => $target['deny_gid'],
            'item_private'   => intval($target['item_private']),
            'item_wall'      => intval($target['item_wall']),
            'item_origin'    => 1,
            'item_thread_top'=> 0,
            'item_notshown'  => 1,
            'plink'          => $reactionMid,
            'route'          => $target['route'] ?? '',
        ];

        $post = item_store($datarray);
        if (!$post['success']) {
            json_return_and_die(['error' => 'RSVP reaction failed']);
        }
        Master::Summon(['Notifier', 'like', $post['item_id']]);

        if (in_array($activityVerb, ['Accept', 'TentativeAccept']) && $target['obj_type'] === 'Event') {
            event_addtocal($target['id'], $uid);
        }

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
    // FormData: { body, title?, summary?, mimetype?, pagetitle? }
    // Only the item owner can edit.
    private function editItem(string $mid): void
    {
        $uid      = Auth::requireLocalMultipart();
        $content  = trim($_POST['body']      ?? '');
        $title    = trim($_POST['title']     ?? '');
        $summary  = trim($_POST['summary']   ?? '');
        $mimetype = trim($_POST['mimetype']  ?? 'text/bbcode');
        $slug     = trim($_POST['pagetitle'] ?? '');

        if (!$content) {
            Response::error(400, 'body is required');
        }

        // The frontend sends the short uuid (e.g. "abc123") not the full mid URL.
        // Use the right column: uuid for bare identifiers, mid for full URLs.
        $col    = (str_contains($mid, '/') || str_contains($mid, ':')) ? 'mid' : 'uuid';
        $midEsc = dbesc($mid);

        // Do NOT use item_normal() here — it restricts to item_type = ITEM_TYPE_POST (0),
        // which would exclude webpages, articles, wiki pages, etc.
        $item = dbq("SELECT * FROM item
                     WHERE $col = '$midEsc' AND uid = $uid
                     AND item_deleted = 0 LIMIT 1");

        if (!$item) {
            Response::error(404, 'Item not found or permission denied');
        }

        $iid = intval($item[0]['id']);
        $now = datetime_convert();

        q("UPDATE item SET body = '%s', title = '%s', summary = '%s', mimetype = '%s',
                           edited = '%s', changed = '%s'
           WHERE id = %d AND uid = %d",
            dbesc($content), dbesc($title), dbesc($summary), dbesc($mimetype),
            dbesc($now), dbesc($now), $iid, $uid);

        // Update the WEBPAGE slug (stored in iconfig) if one was provided
        if ($slug) {
            q("UPDATE iconfig SET v = '%s' WHERE iid = %d AND cat = 'system' AND k = 'WEBPAGE'",
                dbesc($slug), $iid);
        }

        Master::Summon(['Notifier', 'edit_post', $iid]);

        Response::send(['success' => true]);
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

        if (str_starts_with($mid, 'b64.')) {
            $mid = unpack_link_id($mid);
        }
        $col = (str_contains($mid, '/') || str_contains($mid, ':')) ? 'mid' : 'uuid';
        $midEsc = dbesc($mid);

        // Find any copy to verify identity and permission
        // Deliberately not using item_normal() — it excludes articles (item_type != 0)
        $item = dbq("SELECT * FROM item WHERE $col = '$midEsc' AND item_deleted = 0 LIMIT 1");

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

        // Drop all local copies (same mid stored under different channel uids)
        $globalMidEsc = dbesc($i['mid']);
        $all_copies = dbq("SELECT * FROM item WHERE mid = '$globalMidEsc' AND item_deleted = 0");

        // Prefer the wall copy for federation; fall back to first found
        $primary = $i;
        foreach ($all_copies as $copy) {
            if (intval($copy['item_wall'])) {
                $primary = $copy;
                break;
            }
        }

        foreach ($all_copies as $copy) {
            drop_item($copy['id'], DROPITEM_PHASE1);
        }

        $r = q('SELECT * FROM item WHERE id = %d', intval($primary['id']));
        if ($r) {
            xchan_query($r);
            $sync = fetch_post_tags($r);
            Libsync::build_sync_packet($primary['uid'], ['item' => [encode_item($sync[0], true)]]);
        }

        tag_deliver($primary['uid'], $primary['id']);

        if (intval($primary['item_wall']) || $primary['mid'] !== $primary['parent_mid']) {
            Master::Summon(['Notifier', 'drop', $primary['id']]);
        }

        json_return_and_die(['success' => true]);
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    // Resolve a mid (or uuid) to a readable item row, permission-checked.
    // Accepts full mid (zot6 URL), short uuid, or b64-encoded mid.
    // GET /api/item/:mid/folders
    // Returns the folder names this post is currently filed under for the local user.
    private function getItemFolders(string $mid): void
    {
        Auth::RequireLocalGet();
        $uid = local_channel();

        $item = $this->resolveItem($mid, get_observer_hash());
        if (!$item || intval($item['uid']) !== $uid) {
            json_return_and_die(['data' => []]);
        }

        $rows = q(
            "SELECT term FROM term WHERE uid = %d AND oid = %d AND ttype = %d ORDER BY term ASC",
            intval($uid), intval($item['id']), intval(TERM_FILE)
        );

        json_return_and_die(['data' => $rows ? array_column($rows, 'term') : []]);
    }

    // POST /api/item/:mid/saveto
    // Body: { "name": "folder name" }            → add to folder
    // Body: { "name": "folder name", "remove": true } → remove from folder
    private function saveToFolder(string $mid): void
    {
        Auth::requireLocalJson();
        $uid = local_channel();

        $name = trim(Auth::$parsedBody['name'] ?? '');
        $remove = !empty(Auth::$parsedBody['remove']);

        if (!$name) {
            \Theme\Solidified\Api\Response::error(400, 'name required');
        }

        $item = $this->resolveItem($mid, get_observer_hash());
        if (!$item || intval($item['uid']) !== $uid) {
            \Theme\Solidified\Api\Response::error(403, 'Item not found in your stream');
        }

        $item_id = intval($item['id']);
        $parent_id = intval($item['parent']);

        if ($remove) {
            q("DELETE FROM term WHERE uid = %d AND oid = %d AND ttype = %d AND term = '%s'",
                intval($uid), $item_id, intval(TERM_FILE), dbesc($name));
            q("UPDATE item SET item_retained = 0, changed = '%s' WHERE id = %d AND uid = %d",
                dbesc(datetime_convert()), $item_id, intval($uid));
        } else {
            store_item_tag($uid, $item_id, TERM_OBJ_POST, TERM_FILE, $name, '');
            q("UPDATE item SET item_retained = 1, changed = '%s' WHERE id = %d AND uid = %d",
                dbesc(datetime_convert()), $parent_id, intval($uid));
        }

        $rows = q(
            "SELECT term FROM term WHERE uid = %d AND oid = %d AND ttype = %d ORDER BY term ASC",
            intval($uid), $item_id, intval(TERM_FILE)
        );

        json_return_and_die(['data' => ['folders' => $rows ? array_column($rows, 'term') : []]]);
    }

    // GET /api/item/:mid/delivery
    // Returns delivery report entries for a post authored by the logged-in user.
    private function getDeliveryReport(string $mid): void
    {
        Auth::requireLocalGet();
        $channel = \App::get_channel();
        $channelHash = $channel['channel_hash'];

        $item = $this->resolveItem($mid, $channelHash);
        if (!$item) {
            Response::error(404, 'Item not found or permission denied');
        }

        $isAuthor    = $item['author_xchan'] === $channelHash;
        $isWallOwner = $item['owner_xchan'] === $channelHash && intval($item['item_wall']) === 1;
        if (!$isAuthor && !$isWallOwner) {
            Response::error(403, 'Permission denied');
        }

        $itemMid     = dbesc($item['mid']);
        $activityMid = dbesc(str_replace('/item/', '/activity/', $item['mid']));
        $hashEsc     = dbesc($channelHash);

        $rows = dbq("SELECT dreport_name, dreport_recip, dreport_result, dreport_time
                     FROM dreport
                     WHERE dreport_xchan = '$hashEsc'
                       AND (dreport_mid = '$itemMid' OR dreport_mid = '$activityMid')
                     ORDER BY dreport_time ASC");

        $entries = [];
        foreach ($rows ?: [] as $r) {
            $entries[] = [
                'name'   => $r['dreport_name'] ?: $r['dreport_recip'],
                'result' => $r['dreport_result'],
                'time'   => $r['dreport_time'],
            ];
        }

        Response::send($entries);
    }

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

    // Find deleted items that are parents of the given formatted comments but
    // absent from the result set. Returns pre-formatted stubs so the frontend
    // can build a complete thread tree without gaps.
    private static function findDeletedParentStubs(array $comments, string $rootMid): array
    {
        if (empty($comments)) return [];

        $presentMids = array_column($comments, 'mid');
        $missing = [];
        foreach ($comments as $c) {
            $tp = $c['thr_parent'] ?? '';
            if ($tp && $tp !== $rootMid && !in_array($tp, $presentMids) && !in_array($tp, $missing)) {
                $missing[] = $tp;
            }
        }
        if (empty($missing)) return [];

        $inList  = implode("','", array_map('dbesc', $missing));
        $deleted = dbq("SELECT uuid, mid, parent_mid, thr_parent, created
                        FROM item
                        WHERE mid IN ('$inList') AND item_deleted = 1
                        ORDER BY created ASC");

        return array_map(fn($d) => [
            'uuid'             => $d['uuid'],
            'mid'              => $d['mid'],
            'parent_mid'       => $d['parent_mid'],
            'thr_parent'       => $d['thr_parent'],
            'created'          => $d['created'],
            'edited'           => $d['created'],
            'title'            => '',
            'body'             => '',
            'verb'             => 'Create',
            'obj_type'         => 'Note',
            'like_count'       => 0,
            'dislike_count'    => 0,
            'announce_count'   => 0,
            'comment_count'    => 0,
            'item_private'     => 0,
            'item_thread_top'  => 0,
            'item_unseen'      => 0,
            'iid'              => 0,
            'profile_uid'      => 0,
            'flags'            => ['deleted'],
            'author'           => ['name' => '', 'address' => '', 'url' => '', 'photo' => ['src' => '', 'mimetype' => '']],
            'permalink'        => '',
            'viewer_liked'     => false,
            'viewer_disliked'  => false,
            'viewer_repeated'  => false,
            'viewer_attending' => false,
            'viewer_declining' => false,
            'viewer_maybe'     => false,
            'viewer_following' => false,
        ], $deleted ?: []);
    }

    // Shared reaction count subqueries string
    private static function reactionSubqueries(): string
    {
        return "(SELECT COUNT(DISTINCT r.author_xchan) FROM item r WHERE r.uid = item.uid AND r.thr_parent = item.mid AND r.verb = 'Like'    AND r.item_deleted = 0) AS like_count,
                (SELECT COUNT(DISTINCT r.author_xchan) FROM item r WHERE r.uid = item.uid AND r.thr_parent = item.mid AND r.verb = 'Dislike' AND r.item_deleted = 0) AS dislike_count,
                (SELECT COUNT(DISTINCT r.author_xchan) FROM item r WHERE r.uid = item.uid AND r.thr_parent = item.mid AND r.verb = '" . ACTIVITY_SHARE . "' AND r.item_deleted = 0) AS announce_count,
                (SELECT COUNT(*) FROM item r WHERE r.parent = item.id    AND r.item_thread_top = 0    AND r.item_deleted = 0    AND r.verb NOT IN ('Like','Dislike','Announce') AND r.obj_type != 'Answer') AS comment_count,
                (SELECT GROUP_CONCAT(verb, ':', author_xchan SEPARATOR '|')
                 FROM item r
                 WHERE r.parent = item.parent
                   AND r.thr_parent = item.mid
                   AND r.verb IN ('Like','Dislike','Announce','Accept','Reject','TentativeAccept')
                   AND r.item_deleted = 0) AS reaction_verbs";
    }

    // Fetch fresh counts after a toggle — avoids a full item re-fetch
    private function fetchReactionCounts(string $mid): array
    {
        $midEsc = dbesc($mid);
        $uid = intval(local_channel());
        $r = dbq("SELECT
            (SELECT COUNT(DISTINCT r.author_xchan) FROM item r WHERE r.uid = $uid AND r.thr_parent = '$midEsc' AND r.verb = 'Like'              AND r.item_deleted = 0) AS like_count,
            (SELECT COUNT(DISTINCT r.author_xchan) FROM item r WHERE r.uid = $uid AND r.thr_parent = '$midEsc' AND r.verb = 'Dislike'           AND r.item_deleted = 0) AS dislike_count,
            (SELECT COUNT(DISTINCT r.author_xchan) FROM item r WHERE r.uid = $uid AND r.thr_parent = '$midEsc' AND r.verb = '" . ACTIVITY_SHARE . "' AND r.item_deleted = 0) AS announce_count,
            (SELECT COUNT(*) FROM item r WHERE r.thr_parent = '$midEsc' AND r.verb = 'Accept'            AND r.item_deleted = 0) AS attend_count,
            (SELECT COUNT(*) FROM item r WHERE r.thr_parent = '$midEsc' AND r.verb = 'Reject'            AND r.item_deleted = 0) AS decline_count,
            (SELECT COUNT(*) FROM item r WHERE r.thr_parent = '$midEsc' AND r.verb = 'TentativeAccept'   AND r.item_deleted = 0) AS maybe_count");

        return [
            'like_count'     => intval($r[0]['like_count'] ?? 0),
            'dislike_count'  => intval($r[0]['dislike_count'] ?? 0),
            'announce_count' => intval($r[0]['announce_count'] ?? 0),
            'attend_count'   => intval($r[0]['attend_count'] ?? 0),
            'decline_count'  => intval($r[0]['decline_count'] ?? 0),
            'maybe_count'    => intval($r[0]['maybe_count'] ?? 0),
        ];
    }

    // Shared item formatter — same shape as your existing network/channel items
    private static function formatItem(array $item, string $ob_hash): array
    {
        $liked = $disliked = $repeated = $attending = $declining = $maybe = false;
        if ($ob_hash && !empty($item['reaction_verbs'])) {
            foreach (explode('|', $item['reaction_verbs']) as $rv) {
                if (!str_contains($rv, ':'))
                    continue;
                [$v, $xchan] = explode(':', $rv, 2);
                if ($xchan !== $ob_hash)
                    continue;
                if ($v === 'Like')           $liked      = true;
                if ($v === 'Dislike')        $disliked   = true;
                if ($v === 'Announce')       $repeated   = true;
                if ($v === 'Accept')         $attending  = true;
                if ($v === 'Reject')         $declining  = true;
                if ($v === 'TentativeAccept') $maybe     = true;
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
            'viewer_attending' => $attending,
            'viewer_declining' => $declining,
            'viewer_maybe' => $maybe,
            'viewer_following' => (bool)($item['viewer_following'] ?? false),
            'poll'             => self::extractPoll($item, $ob_hash),
        ];
    }

    private static function extractPoll(array $item, string $observer_xchan): ?array
    {
        if (($item['obj_type'] ?? '') !== 'Question') return null;
        $raw = $item['obj'] ?? '';
        if (!$raw) return null;

        $obj = is_array($raw) ? $raw : json_decode($raw, true);
        if (!$obj || ($obj['type'] ?? '') !== 'Question') return null;

        $multiple = false;
        $choices  = $obj['oneOf'] ?? null;
        if (empty($choices)) {
            $choices  = $obj['anyOf'] ?? [];
            $multiple = true;
        }

        $options = [];
        foreach ($choices as $opt) {
            $options[] = [
                'name'  => htmlspecialchars_decode($opt['name'] ?? '', ENT_QUOTES | ENT_HTML5),
                'votes' => intval($opt['replies']['totalItems'] ?? 0),
            ];
        }

        $viewer_votes = [];
        if ($observer_xchan && !empty($item['id'])) {
            $iid   = intval($item['id']);
            $obEsc = dbesc($observer_xchan);
            $rows  = dbq("SELECT title FROM item
                          WHERE parent = $iid
                            AND author_xchan = '$obEsc'
                            AND obj_type = 'Answer'
                            AND item_deleted = 0");
            if ($rows) {
                $viewer_votes = array_column($rows, 'title');
            }
        }

        return [
            'multiple'     => $multiple,
            'end_time'     => $obj['endTime'] ?? null,
            'closed'       => $obj['closed']  ?? null,
            'options'      => $options,
            'viewer_votes' => $viewer_votes,
        ];
    }

    // POST /api/item/:mid/vote
    // Body: { "answer": "Option name" } or { "answer": ["Option A", "Option B"] } for multi-choice
    private function voteOnPoll(string $mid): void
    {
        Auth::requireLocalJson();

        $uid     = local_channel();
        $channel = App::get_channel();
        $ob_hash = $channel['channel_hash'];

        $answer = Auth::$parsedBody['answer'] ?? null;

        if ($answer === null) {
            json_return_and_die(['error' => 'answer is required']);
        }

        $poll = $this->resolveItem($mid, $ob_hash);
        if (!$poll || ($poll['obj_type'] ?? '') !== 'Question') {
            json_return_and_die(['error' => 'Poll not found']);
        }

        $iid   = intval($poll['id']);
        $obEsc = dbesc($ob_hash);

        $existing = dbq("SELECT id FROM item
                         WHERE parent = $iid
                           AND author_xchan = '$obEsc'
                           AND obj_type = 'Answer'
                           AND item_deleted = 0
                         LIMIT 1");
        if ($existing) {
            json_return_and_die(['error' => 'Already voted']);
        }

        $raw = $poll['obj'] ?? '';
        $obj = is_array($raw) ? $raw : json_decode($raw, true);
        if (!$obj) {
            json_return_and_die(['error' => 'Invalid poll data']);
        }

        $multiple   = !empty($obj['anyOf']);
        $optionsKey = $multiple ? 'anyOf' : 'oneOf';
        $validNames = array_map(
            fn($o) => htmlspecialchars_decode($o['name'] ?? '', ENT_QUOTES | ENT_HTML5),
            $obj[$optionsKey] ?? []
        );

        $responses = is_array($answer) ? $answer : [$answer];
        foreach ($responses as $res) {
            if (!in_array($res, $validNames, true)) {
                json_return_and_die(['error' => 'Invalid answer: ' . $res]);
            }
        }

        if (!$multiple) {
            $responses = [$responses[0]];
        }

        foreach ($responses as $res) {
            $uuid      = item_message_id();
            $answerMid = z_root() . '/item/' . $uuid;
            $now       = datetime_convert();

            $datarray = [
                'aid'             => $channel['channel_account_id'],
                'uid'             => intval($poll['uid']),
                'uuid'            => $uuid,
                'mid'             => $answerMid,
                'parent_mid'      => $poll['mid'],
                'thr_parent'      => $poll['mid'],
                'owner_xchan'     => $poll['author_xchan'],
                'author_xchan'    => $ob_hash,
                'created'         => $now,
                'edited'          => $now,
                'commented'       => $now,
                'received'        => $now,
                'changed'         => $now,
                'verb'            => 'Create',
                'obj_type'        => 'Answer',
                'title'           => $res,
                'body'            => '',
                'mimetype'        => 'text/bbcode',
                'allow_cid'       => '<' . $poll['author_xchan'] . '>',
                'allow_gid'       => '',
                'deny_cid'        => '',
                'deny_gid'        => '',
                'item_private'    => 1,
                'item_unseen'     => 0,
                'item_wall'       => 0,
                'item_origin'     => 1,
                'item_thread_top' => 0,
                'plink'           => $answerMid,
            ];

            $post = item_store($datarray);
            if ($post['success']) {
                retain_item($iid);
                Master::Summon(['Notifier', 'like', $post['item_id']]);
            }
        }

        json_return_and_die(['success' => true]);
    }

    // POST /api/item/:mid/reshare
    // Body: { body? }  (optional additional text above the share block)
    private function createReshare(string $mid): void
    {
        $uid = Auth::requireLocalJson();
        $ob_hash = get_observer_hash();

        $extraContent = trim(Auth::$parsedBody['body'] ?? '');

        $item = $this->resolveItem($mid, $ob_hash);
        if (!$item) {
            json_return_and_die(['error' => 'Item not found or permission denied']);
        }

        $iid = intval($item['id']);

        $share = new \Zotlabs\Lib\Share($iid);
        $shareBlock = $share->bbcode();

        // Core Share::bbcode() refuses to wrap posts whose body already contains
        // [/share] (i.e. reshares). Build the block ourselves in that case.
        if (!$shareBlock) {
            $shareBlock = $this->buildShareBlock($item);
        }

        if (!$shareBlock) {
            json_return_and_die(['error' => 'Cannot reshare this post']);
        }

        $content = $extraContent
            ? $extraContent . "\r\n\r\n" . $shareBlock
            : $shareBlock;

        $acl = self::scopeToAcl('public', $uid);

        $datarray = self::buildItemArray(
            profileUid: $uid,
            content: $content,
            title: '',
            mimetype: 'text/bbcode',
            acl: $acl,
            isWall: true,
        );

        $post = item_store($datarray);

        if (!$post['success']) {
            json_return_and_die(['error' => 'Failed to create reshare post']);
        }

        \Zotlabs\Daemon\Master::Summon(['Notifier', 'wall-new', $post['item_id']]);

        json_return_and_die([
            'success' => true,
            'iid'  => $post['item_id'],
            'mid'  => $datarray['mid'],
            'uuid' => $datarray['uuid'],
        ]);
    }

    private function buildShareBlock(array $item): string
    {
        if ($item['item_private'] || $item['mimetype'] !== 'text/bbcode') {
            return '';
        }

        $rows = [$item];
        xchan_query($rows, true);
        $author  = $rows[0]['author'] ?? [];
        $network = $author['xchan_network'] ?? '';
        $quote   = in_array($network, ['zot6', 'activitypub']) ? "quote='true'" : '';

        $bb  = "[share author='" . urlencode($author['xchan_name'] ?? '') . "'\n";
        $bb .= "\tprofile='" . ($author['xchan_url'] ?? '') . "'\n";
        $bb .= "\tavatar='" . ($author['xchan_photo_s'] ?? '') . "'\n";
        $bb .= "\tlink='" . ($item['plink'] ?? '') . "'\n";
        $bb .= "\tauth='" . ($network === 'zot6' ? 'true' : 'false') . "'\n";
        $bb .= "\tposted='" . ($item['created'] ?? '') . "'\n";
        $bb .= "\tmessage_id='" . ($item['mid'] ?? '') . "'\n";
        if ($quote) {
            $bb .= "\t$quote\n";
        }
        $bb .= ']';

        if ($item['title']) {
            $bb .= '[h3][b]' . $item['title'] . '[/b][/h3]' . "\r\n";
        }

        $bb .= $item['body'];
        $bb .= '[/share]';

        return $bb;
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

    // Nginx normalises "https://" to "https:/" in URL paths before passing the
    // request to PHP via $_GET['q']. After explode/implode the reconstructed mid
    // ends up with only one slash after the protocol colon. Restore the missing slash.
    private static function fixProtocolSlashes(string $mid): string
    {
        if (str_starts_with($mid, 'https:/') && !str_starts_with($mid, 'https://')) {
            return 'https://' . substr($mid, 7);
        }
        if (str_starts_with($mid, 'http:/') && !str_starts_with($mid, 'http://')) {
            return 'http://' . substr($mid, 6);
        }
        return $mid;
    }
}
