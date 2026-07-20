<?php
// extend/theme/utsukta-themes/solidified/Api/Handlers/Item.php

namespace Theme\Solidified\Api\Handlers;

require_once ('include/items.php');
require_once ('include/conversation.php');
require_once ('include/security.php');
require_once ('include/crypto.php');

use Zotlabs\Daemon\Master;
use Zotlabs\Lib\Libsync;
use Zotlabs\Lib\Enotify;
use Zotlabs\Access\PermissionLimits;
use App;
use Theme\Solidified\Api\Auth;
use Theme\Solidified\Api\Concerns\ReactionCounts;
use Theme\Solidified\Api\Concerns\FiltersBlockedChannels;
use Theme\Solidified\Api\Response;

class Item
{
    use FiltersBlockedChannels;

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
    // POST /api/item/:mid/pin                -> toggle pinned (channel wall)
    // POST /api/item/:mid/comment            -> post a comment
    // POST /api/item/:mid/delete             -> delete item
    // POST /api/item/:mid/edit               -> edit item body/title
    // POST /api/item/:mid/follow             -> follow thread (core: subthread/sub)
    // POST /api/item/:mid/unfollow           -> unfollow thread (core: subthread/unsub)
    // POST /api/item                         -> create new top-level post
    // POST /api/item/:mid                    -> (same as comment — alias)

    public function get(): void
    {
        // Hubzilla message IDs are full URLs (e.g. https://host/item/uuid) that
        // contain "/" characters. When not percent-encoded by the caller, the path
        // is split across multiple argv segments. Reconstruct the mid by detecting
        // the known verb at the end; for comments the optional count sits after it.
        $GET_VERBS = ['comments', 'likes', 'dislikes', 'repeats', 'folders', 'delivery', 'compose', 'sharepreview'];
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
            case 'compose':
                $this->getComposeSource($mid);
                break;
            case 'sharepreview':
                $this->getSharePreview($mid);
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
                       'tentativeaccept', 'star', 'pin', 'comment', 'delete',
                       'edit', 'reshare', 'saveto', 'vote',
                       'follow', 'unfollow'];

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
            case 'pin':
                $this->togglePin($mid);
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
            case 'follow':
                $this->toggleThreadFollow($mid, true);
                break;
            case 'unfollow':
                $this->toggleThreadFollow($mid, false);
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
        // Follow/Ignore activities live in the *viewer's* channel copy of the
        // thread (core Mod_Subthread), so match by parent_mid within their uid.
        $luid = intval(local_channel());
        if ($luid && $ob_hash) {
            $pmid = dbesc($row['parent_mid']);
            $obs  = dbesc($ob_hash);
            $fr = dbq(
                "SELECT verb FROM item
                 WHERE uid = $luid
                   AND parent_mid = '$pmid'
                   AND author_xchan = '$obs'
                   AND verb IN ('Follow', 'Ignore')
                   AND item_deleted = 0
                 ORDER BY created DESC, id DESC LIMIT 1"
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

        $blocked = $this->blockedXchans(local_channel());
        $blocked_sql = $this->blockedSqlClause('item.author_xchan', $blocked)
            . $this->blockedSqlClause('item.owner_xchan', $blocked);

        // Fetch all thread children (direct replies + nested) — excludes reactions
        $rows = dbq('SELECT item.*,
            ' . self::reactionSubqueries() . "
            FROM item
            WHERE item.parent = $rootId
              AND item.verb IN ('Create', 'Update', 'EmojiReact')
              AND item.obj_type NOT IN ('Answer')
              AND item.item_thread_top = 0
              $item_normal
              $blocked_sql
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

        $blocked = $this->blockedXchans(local_channel());
        $blocked_sql = $this->blockedSqlClause('item.author_xchan', $blocked);

        $rows = dbq("SELECT DISTINCT item.author_xchan, MIN(item.created) AS created
                     FROM item
                     WHERE item.uid = $rootUid
                       AND item.thr_parent = '$rootMid'
                       AND item.verb = '$verb'
                       AND item.item_deleted = 0
                       $item_normal
                       $blocked_sql
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
        $uid  = Auth::requireLocalJson();
        $body = Auth::$parsedBody;

        $content    = trim($body['body']        ?? '');
        $title      = trim($body['title']       ?? '');
        $summary    = trim($body['summary']     ?? '');
        $category   = trim($body['category']    ?? '');
        $profileUid = intval($body['profile_uid'] ?? $uid);
        $scope      = $body['scope']    ?? 'contacts';
        $mimetype   = $body['mimetype'] ?? 'text/bbcode';
        $expire     = trim($body['expire']      ?? '');
        $location   = escape_tags(trim($body['location'] ?? ''));
        $coord      = escape_tags(trim($body['coord']    ?? ''));
        $nocomment  = !empty($body['nocomment']) ? 1 : 0;
        $createdRaw = trim($body['created'] ?? '');

        if (!$content) {
            Response::error(400, 'body is required');
        }

        $observer = App::get_observer();
        if (!$observer) {
            Response::error(403, 'Authentication required');
        }
        $ob_hash = $observer['xchan_hash'];

        if (!perm_is_allowed($profileUid, $ob_hash, 'post_wall')) {
            Response::error(403, 'Permission denied');
        }

        // Load wall owner's channel record (may differ from the logged-in channel)
        require_once('include/channel.php');
        $r = q('SELECT * FROM channel WHERE channel_id = %d LIMIT 1', $profileUid);
        if (!$r) {
            Response::error(404, 'Channel not found');
        }
        $ownerChannel = $r[0];

        // Wall-to-wall: author differs from wall owner
        $wallToWall = ($ownerChannel['channel_hash'] !== $ob_hash);

        // ACL: W2W always uses the wall owner's channel defaults.
        // For owner posts, apply the scope the client requested.
        $acl = new \Zotlabs\Access\AccessList($ownerChannel);

        if (!$wallToWall) {
            if ($scope === 'public') {
                $acl->set(['allow_cid' => '', 'allow_gid' => '', 'deny_cid' => '', 'deny_gid' => '']);
            } elseif ($scope === 'custom') {
                $contactAllow = is_array($body['contact_allow'] ?? null) ? $body['contact_allow'] : [];
                $groupAllow   = is_array($body['group_allow']   ?? null) ? $body['group_allow']   : [];
                $contactDeny  = is_array($body['contact_deny']  ?? null) ? $body['contact_deny']  : [];
                $groupDeny    = is_array($body['group_deny']    ?? null) ? $body['group_deny']    : [];
                if (!$contactAllow && !$groupAllow) {
                    Response::error(400, 'Select at least one connection or group to allow.');
                }
                $acl->set([
                    'allow_cid' => implode('', array_map(fn($h) => '<' . $h . '>', $contactAllow)),
                    'allow_gid' => implode('', array_map(fn($g) => '<' . $g . '>', $groupAllow)),
                    'deny_cid'  => implode('', array_map(fn($h) => '<' . $h . '>', $contactDeny)),
                    'deny_gid'  => implode('', array_map(fn($g) => '<' . $g . '>', $groupDeny)),
                ]);
            }
            // 'contacts': keep the channel's default ACL from the AccessList constructor
        }

        // Derive public_policy and comment_policy from the wall owner's permission limits
        $viewPolicy    = PermissionLimits::Get($profileUid, 'view_stream');
        $commentPolicy = PermissionLimits::Get($profileUid, 'post_comments');
        $publicPolicy  = map_scope($viewPolicy, true);

        $gacl            = $acl->get();
        $strContactAllow = $gacl['allow_cid'];
        $strGroupAllow   = $gacl['allow_gid'];
        $strContactDeny  = $gacl['deny_cid'];
        $strGroupDeny    = $gacl['deny_gid'];

        $private = intval($acl->is_private() || $publicPolicy);

        // A specific ACL overrides public_policy (same logic as core Item::post)
        if (!empty_acl(['allow_cid' => $strContactAllow, 'allow_gid' => $strGroupAllow,
                        'deny_cid'  => $strContactDeny,  'deny_gid'  => $strGroupDeny])) {
            $publicPolicy = '';
        }

        $postTags    = [];
        $attachments = [];

        if ($mimetype === 'text/bbcode') {
            require_once('include/text.php');

            $content = cleanup_bbcode($content);

            // Linkify @mentions, #tags, !groups — modifies $content in place
            $results = linkify_tags($content, $profileUid);
            if ($results) {
                set_linkified_perms($results, $strContactAllow, $strGroupAllow, $profileUid, $private, false);
                foreach ($results as $result) {
                    $s = $result['success'];
                    if ($s['replaced']) {
                        $postTags[] = [
                            'uid'   => $profileUid,
                            'ttype' => $s['termtype'],
                            'otype' => TERM_OBJ_POST,
                            'term'  => $s['term'],
                            'url'   => $s['url'],
                        ];
                    }
                }
            }

            // Contact-allow without group-allow → direct message between individuals
            if ($strContactAllow && !$strGroupAllow) {
                $private = 2;
            }

            // Sync file/photo ACL to match the post's final ACL
            fix_attached_permissions($profileUid, $content, $strContactAllow, $strGroupAllow, $strContactDeny, $strGroupDeny);

            // Extract [attachment] tags → attach array, strip them from body
            if (preg_match_all('/(\[attachment\](.*?)\[\/attachment\])/', $content, $match)) {
                require_once('include/attach.php');
                foreach ($match[2] as $i => $mtch) {
                    $hash = substr($mtch, 0, strpos($mtch, ','));
                    $rev  = intval(substr($mtch, strpos($mtch, ',')));
                    $r    = attach_by_hash_nodata($hash, $ob_hash, $rev);
                    if ($r['success']) {
                        $attachments[] = [
                            'url'      => z_root() . '/attach/' . $r['data']['hash'],
                            'length'   => $r['data']['filesize'],
                            'type'     => $r['data']['filetype'],
                            'title'    => urlencode($r['data']['filename']),
                            'revision' => $r['data']['revision'],
                        ];
                    }
                    $content = str_replace($match[1][$i], '', $content);
                }
            }

            $content = $this->expandShareTags($content);

            $postTags = array_merge($postTags, self::buildEmojiTerms($profileUid, $content));
        }

        // Categories → term records (federate correctly via datarray['term'])
        if ($category) {
            foreach (array_filter(array_map('trim', explode(',', $category))) as $cat) {
                $postTags[] = [
                    'uid'   => $profileUid,
                    'ttype' => TERM_CATEGORY,
                    'otype' => TERM_OBJ_POST,
                    'term'  => $cat,
                    'url'   => channel_url($ownerChannel) . '?cat=' . urlencode($cat),
                ];
            }
        }

        $channel = App::get_channel();
        $uuid    = item_message_id();
        $mid     = z_root() . '/item/' . $uuid;
        $now     = datetime_convert();

        // Delayed publish ("time travel post", core feature delayed_posting):
        // a future created date stores the item with item_delayed = 1, which
        // hides it from all item_normal queries. Daemon\Cron flips the flag and
        // summons the Notifier once the publish time arrives.
        $created = $now;
        $delayed = 0;
        if ($createdRaw) {
            $ts = datetime_convert(date_default_timezone_get(), 'UTC', $createdRaw);
            if ($ts > $now) {
                $created = $ts;
                $delayed = 1;
            }
        }

        $datarray = [
            'aid'             => $channel['channel_account_id'],
            'uid'             => $profileUid,
            'uuid'            => $uuid,
            'mid'             => $mid,
            'parent_mid'      => $mid,
            'thr_parent'      => $mid,
            'owner_xchan'     => $ownerChannel['channel_hash'],
            'author_xchan'    => $ob_hash,
            'created'         => $created,
            'edited'          => $now,
            'commented'       => $now,
            'received'        => $now,
            'changed'         => $now,
            'verb'            => 'Create',
            'obj_type'        => 'Note',
            'mimetype'        => $mimetype,
            'title'           => $title,
            'summary'         => $summary,
            'body'            => $content,
            'location'        => $location,
            'coord'           => $coord,
            'allow_cid'       => $strContactAllow,
            'allow_gid'       => $strGroupAllow,
            'deny_cid'        => $strContactDeny,
            'deny_gid'        => $strGroupDeny,
            'attach'          => $attachments,
            'term'            => array_unique($postTags, SORT_REGULAR),
            'item_wall'       => 1,
            'item_origin'     => 1,
            'item_thread_top' => 1,
            'item_unseen'     => ($wallToWall ? 1 : 0),
            'item_private'    => $private,
            'item_delayed'    => $delayed,
            'item_nocomment'  => $nocomment,
            'public_policy'   => $publicPolicy,
            'comment_policy'  => map_scope($commentPolicy),
            'plink'           => $mid,
            'route'           => '',
        ];

        // Core closes comments from the moment of publication when nocomment
        // is set (comments_closed = created); otherwise item_store leaves the
        // column at the DB null date (comments stay open).
        if ($nocomment) {
            $datarray['comments_closed'] = $created;
        }

        if ($expire) {
            $exp = datetime_convert(date_default_timezone_get(), 'UTC', $expire);
            if ($exp > $now) {
                $datarray['expires'] = $exp;
            }
        }

        // Polls
        $pollAnswers = $body['poll_answers'] ?? null;
        if (is_array($pollAnswers)) {
            $answers = array_values(array_filter(array_map(fn($a) => escape_tags(trim($a)), $pollAnswers)));
            if (count($answers) >= 2) {
                $expireValue = max(1, intval($body['poll_expire_value'] ?? 1));
                $expireUnit  = in_array($body['poll_expire_unit'] ?? 'Days', ['Minutes', 'Hours', 'Days', 'Weeks'], true)
                    ? $body['poll_expire_unit']
                    : 'Days';
                $opts        = array_map(
                    fn($a) => ['name' => $a, 'type' => 'Note', 'replies' => ['type' => 'Collection', 'totalItems' => 0]],
                    $answers
                );
                $pollEndTime = datetime_convert(date_default_timezone_get(), 'UTC',
                    'now + ' . $expireValue . ' ' . $expireUnit, ATOM_TIME);
                $datarray['obj_type'] = 'Question';
                $datarray['obj']      = [
                    'type'         => 'Question',
                    'id'           => $mid,
                    'url'          => $mid,
                    'attributedTo' => channel_url($ownerChannel),
                    'content'      => bbcode($content),
                    'name'         => $title ?: '',
                    'oneOf'        => $opts,
                    'endTime'      => $pollEndTime,
                    'to'           => [ACTIVITY_PUBLIC_INBOX],
                ];
                if (empty($datarray['expires'])) {
                    $datarray['expires'] = datetime_convert('UTC', 'UTC', $pollEndTime);
                }
            }
        }

        call_hooks('post_local', $datarray);

        if (!empty($datarray['cancel'])) {
            Response::error(400, 'Post cancelled');
        }

        $post = item_store($datarray);

        if (!$post['success']) {
            Response::error(500, 'Failed to create post');
        }

        // Notify wall owner when someone posts on their wall (wall-to-wall)
        if ($wallToWall) {
            Enotify::submit([
                'type'       => NOTIFY_WALL,
                'from_xchan' => $ob_hash,
                'to_xchan'   => $ownerChannel['channel_hash'],
                'item'       => $datarray,
                'link'       => z_root() . '/display/' . $uuid,
                'verb'       => 'Create',
                'otype'      => 'item',
            ]);
        } else {
            // Update owner's last-post timestamp
            q("UPDATE channel SET channel_lastpost = '%s' WHERE channel_id = %d",
                dbesc($now), $profileUid);
        }

        $datarray['id'] = $post['item_id'];
        call_hooks('post_local_end', $datarray);

        // Delayed items are delivered by Daemon\Cron at publish time
        if (!$delayed) {
            Master::Summon(['Notifier', 'wall-new', $post['item_id']]);
        }

        // Fetch the stored item back fully formatted and return it
        $iid  = intval($post['item_id']);
        $rows = dbq('SELECT item.*, ' . self::reactionSubqueries() . " FROM item WHERE item.id = $iid LIMIT 1");
        if ($rows) {
            xchan_query($rows, true);
            $rows          = fetch_post_tags($rows, true);
            $formattedPost = self::formatItem($rows[0], $ob_hash);
        } else {
            $formattedPost = ['iid' => $iid, 'mid' => $mid, 'uuid' => $uuid];
        }

        Response::send(['post' => $formattedPost, 'comments' => []]);
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

        // resolveItem only proves the observer can *view* the parent. Commenting
        // is a separate permission — enforce the channel's comment policy /
        // post_comments grant and closed-comment state, as core Item.php does.
        if (!can_comment_on_post($ob_hash, $parent)) {
            json_return_and_die(['error' => 'Commenting is not permitted on this post']);
        }

        $profileUid = intval($parent['uid']);
        $mimetype   = $body['mimetype'] ?? 'text/bbcode';

        $postTags    = [];
        $attachments = [];

        if ($mimetype === 'text/bbcode') {
            require_once('include/text.php');

            $content = cleanup_bbcode($content);

            // Linkify @mentions, #tags, !groups. Unlike top-level posts the
            // resulting tags never widen the thread ACL (core passes the
            // parent item to set_linkified_perms, which makes it a no-op), so
            // only the term records are collected here.
            $results = linkify_tags($content, $profileUid);
            if ($results) {
                foreach ($results as $result) {
                    $s = $result['success'];
                    if ($s['replaced']) {
                        $postTags[] = [
                            'uid'   => $profileUid,
                            'ttype' => $s['termtype'],
                            'otype' => TERM_OBJ_POST,
                            'term'  => $s['term'],
                            'url'   => $s['url'],
                        ];
                    }
                }
            }

            // Sync file/photo ACL to the thread's ACL so recipients can open them
            fix_attached_permissions($profileUid, $content,
                $parent['allow_cid'], $parent['allow_gid'],
                $parent['deny_cid'], $parent['deny_gid']);

            // Extract [attachment] tags → attach array, strip them from body
            if (preg_match_all('/(\[attachment\](.*?)\[\/attachment\])/', $content, $match)) {
                require_once('include/attach.php');
                foreach ($match[2] as $i => $mtch) {
                    $hash = substr($mtch, 0, strpos($mtch, ','));
                    $rev  = intval(substr($mtch, strpos($mtch, ',')));
                    $r    = attach_by_hash_nodata($hash, $ob_hash, $rev);
                    if ($r['success']) {
                        $attachments[] = [
                            'url'      => z_root() . '/attach/' . $r['data']['hash'],
                            'length'   => $r['data']['filesize'],
                            'type'     => $r['data']['filetype'],
                            'title'    => urlencode($r['data']['filename']),
                            'revision' => $r['data']['revision'],
                        ];
                    }
                    $content = str_replace($match[1][$i], '', $content);
                }
            }

            $content = $this->expandShareTags($content);

            $postTags = array_merge($postTags, self::buildEmojiTerms($profileUid, $content));
        }

        // Inherit ACL and privacy from parent
        $datarray = self::buildItemArray(
            profileUid: $profileUid,
            content: $content,
            title: trim($body['title'] ?? ''),
            mimetype: $mimetype,
            acl: [
                'allow_cid' => $parent['allow_cid'],
                'allow_gid' => $parent['allow_gid'],
                'deny_cid' => $parent['deny_cid'],
                'deny_gid' => $parent['deny_gid'],
            ],
            isWall: intval($parent['item_wall']) === 1,
            parent: $parent,
            term: $postTags,
            attach: $attachments,
        );

        call_hooks('post_local', $datarray);

        if (!empty($datarray['cancel'])) {
            json_return_and_die(['error' => 'Comment cancelled']);
        }

        $post = item_store($datarray);

        if (!$post['success']) {
            json_return_and_die(['error' => 'Failed to post comment']);
        }

        $datarray['id'] = $post['item_id'];
        call_hooks('post_local_end', $datarray);

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

    // POST /api/item/:mid/pin
    // Toggles pinned state of a top-level, non-private wall post owned by the
    // local channel. Core parity (Zotlabs/Module/Pin.php): pconfig-backed
    // ('pinned' cat, ITEM_TYPE_POST key), synced to clones via Libsync. Unlike
    // core, membership in the pinned array is toggled (add/remove) rather than
    // always replaced — this SPA allows pinning more than one post at a time.
    private function togglePin(string $mid): void
    {
        $this->requireLocalChannel();
        $this->requireCsrf();

        $uid = local_channel();

        if (str_starts_with($mid, 'b64.')) {
            $mid = unpack_link_id($mid);
        }
        $col = (str_contains($mid, '/') || str_contains($mid, ':')) ? 'mid' : 'uuid';
        $midEsc = dbesc($mid);

        $item = dbq("SELECT id, uuid FROM item
                     WHERE item.$col = '$midEsc' AND item.uid = $uid
                       AND item.id = item.parent
                       AND item.item_private = 0
                       AND item.item_wall = 1
                       AND item.item_deleted = 0
                     LIMIT 1");

        if (!$item) {
            json_return_and_die(['error' => 'Item not found, not eligible, or permission denied']);
        }

        $midb64 = $item[0]['uuid'];
        $pinned = get_pconfig($uid, 'pinned', ITEM_TYPE_POST, []);
        $pinned = is_array($pinned) ? $pinned : [];
        $isPinned = in_array($midb64, $pinned, true);

        $pinned = $isPinned
            ? array_values(array_diff($pinned, [$midb64]))
            : [...$pinned, $midb64];

        set_pconfig($uid, 'pinned', ITEM_TYPE_POST, $pinned);

        Libsync::build_sync_packet($uid, ['config']);

        json_return_and_die(['success' => true, 'pinned' => !$isPinned]);
    }

    // POST /api/item/:mid/follow | /api/item/:mid/unfollow
    // Mirrors core Mod_Subthread: records a Follow (sub) or Ignore (unsub)
    // activity authored by the viewer on the thread top of their own copy of
    // the thread. The activity is local-only (no delivery), and the latest
    // Follow/Ignore wins — the same convention the stream queries and the
    // pf (followed threads) filter rely on. Items that exist only in the sys
    // channel (pubstream) are copied into the viewer's channel first, exactly
    // like core's copy_of_pubitem() fallback.
    private function toggleThreadFollow(string $mid, bool $follow): void
    {
        require_once('include/channel.php');

        $this->requireLocalChannel();
        $this->requireCsrf();

        $uid         = local_channel();
        $channel     = App::get_channel();
        $observer    = App::get_observer();
        $ob_hash     = $channel['channel_hash'];
        $item_normal = item_normal();

        $target = $this->resolveItem($mid, $ob_hash);
        if (!$target) {
            json_return_and_die(['error' => 'Item not found or permission denied']);
        }

        // The follow state lives on the viewer's copy of the thread.
        // resolveItem() already prefers it; anything else means the viewer
        // has no copy — pull pubstream items in, refuse the rest.
        if (intval($target['uid']) !== $uid) {
            $sys = get_sys_channel();
            if (intval($target['uid']) === intval($sys['channel_id'])) {
                $copy = copy_of_pubitem($channel, $target['mid']);
                if (!$copy) {
                    json_return_and_die(['error' => 'Unable to copy item to your stream']);
                }
                $target = $copy;
            } else {
                json_return_and_die(['error' => 'This conversation is not in your stream']);
            }
        }

        // Follow state always attaches to the thread top (like core subthread)
        if (!intval($target['item_thread_top'])) {
            $pid = intval($target['parent']);
            $r = dbq("SELECT * FROM item WHERE id = $pid $item_normal LIMIT 1");
            if (!$r) {
                json_return_and_die(['error' => 'Thread not found']);
            }
            $target = $r[0];
        }

        // No-op when the latest Follow/Ignore already matches the request
        $tid    = intval($target['id']);
        $obsEsc = dbesc($ob_hash);
        $cur = dbq("SELECT verb FROM item
                    WHERE parent = $tid
                      AND author_xchan = '$obsEsc'
                      AND verb IN ('Follow', 'Ignore')
                      AND item_deleted = 0
                    ORDER BY created DESC LIMIT 1");
        $currently = !empty($cur) && $cur[0]['verb'] === 'Follow';
        if ($currently === $follow) {
            json_return_and_die(['success' => true, 'following' => $follow]);
        }

        $author = q("SELECT * FROM xchan WHERE xchan_hash = '%s' LIMIT 1",
            dbesc($target['author_xchan']));
        if (!$author) {
            json_return_and_die(['error' => 'Item author not found']);
        }

        $uuid      = item_message_id();
        $post_type = (($target['resource_type'] ?? '') === 'photo') ? t('photo') : t('status');
        $ulink     = '[zrl=' . $author[0]['xchan_url'] . ']' . $author[0]['xchan_name'] . '[/zrl]';
        $alink     = '[zrl=' . $observer['xchan_url'] . ']' . $observer['xchan_name'] . '[/zrl]';
        $plink     = '[zrl=' . z_root() . '/display/' . $target['uuid'] . ']' . $post_type . '[/zrl]';
        $bodyverb  = $follow
            ? t('%1$s is following %2$s\'s %3$s')
            : t('%1$s stopped following %2$s\'s %3$s');

        $arr = [
            'uuid'          => $uuid,
            'mid'           => z_root() . '/item/' . $uuid,
            'aid'           => $target['aid'],
            'uid'           => intval($target['uid']),
            'parent'        => $tid,
            'parent_mid'    => $target['mid'],
            'thr_parent'    => $target['mid'],
            'owner_xchan'   => $target['owner_xchan'],
            'author_xchan'  => $ob_hash,
            'item_origin'   => 1,
            'item_notshown' => 1,
            'item_wall'     => intval($target['item_wall']),
            'verb'          => $follow ? 'Follow' : 'Ignore',
            'obj_type'      => (($target['resource_type'] ?? '') === 'photo') ? 'Image' : 'Note',
            'body'          => sprintf($bodyverb, $alink, $ulink, $plink),
            'allow_cid'     => $target['allow_cid'],
            'allow_gid'     => $target['allow_gid'],
            'deny_cid'      => $target['deny_cid'],
            'deny_gid'      => $target['deny_gid'],
        ];

        $post = item_store($arr, false, false, false);
        if (empty($post['item_id'])) {
            json_return_and_die(['error' => 'Failed to store activity']);
        }

        $arr['id'] = $post['item_id'];
        call_hooks('post_local_end', $arr);

        json_return_and_die(['success' => true, 'following' => $follow]);
    }

    // POST /api/item/:mid/edit
    // JSON body: { body, title?, summary?, mimetype?, pagetitle? }
    // Only the item owner can edit.
    private function editItem(string $mid): void
    {
        $uid      = Auth::requireLocalJson();
        $content  = trim(Auth::$parsedBody['body']      ?? '');
        $title    = trim(Auth::$parsedBody['title']     ?? '');
        $summary  = trim(Auth::$parsedBody['summary']   ?? '');
        $mimetype = trim(Auth::$parsedBody['mimetype']  ?? 'text/bbcode');
        $slug     = trim(Auth::$parsedBody['pagetitle'] ?? '');

        if (!$content) {
            Response::error(400, 'body is required');
        }

        $postTags = [];

        if ($mimetype === 'text/bbcode') {
            require_once('include/text.php');

            $content = cleanup_bbcode($content);

            // Rebuild mention/hashtag/group term records from the edited body.
            // Unlike a new post, editing never widens the thread ACL (same
            // reasoning as createComment()) — only term records are collected.
            $results = linkify_tags($content, $uid);
            if ($results) {
                foreach ($results as $result) {
                    $s = $result['success'];
                    if ($s['replaced']) {
                        $postTags[] = [
                            'ttype' => $s['termtype'],
                            'term'  => $s['term'],
                            'url'   => $s['url'],
                        ];
                    }
                }
            }

            $content = $this->expandShareTags($content);

            $postTags = array_merge($postTags, self::buildEmojiTerms($uid, $content));
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

        // Rebuild term records to match the edited body (mentions, hashtags,
        // groups, emoji) — same delete+reinsert approach core's own
        // item_store_update() uses (include/items.php ~2400-2418), since this
        // handler updates the item row directly rather than going through it.
        q("DELETE FROM term WHERE oid = %d AND otype = %d", $iid, intval(TERM_OBJ_POST));
        foreach ($postTags as $t) {
            q("INSERT INTO term (uid, oid, otype, ttype, term, url, imgurl)
                VALUES (%d, %d, %d, %d, '%s', '%s', '%s')",
                intval($uid), $iid, intval(TERM_OBJ_POST), intval($t['ttype']),
                dbesc($t['term']), dbesc($t['url']), dbesc($t['imgurl'] ?? ''));
        }

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

        // Prefer the caller's own copy (needed below to correctly tell whether
        // this is really their own stream copy); falls back to any accessible
        // copy for the true-author/owner/admin case.
        $i = $this->resolveItem($mid, $ob_hash);

        if (!$i) {
            json_return_and_die(['error' => 'Item not found']);
        }

        // $local_delete: this row lives under the caller's own uid (their own
        // stream/wall copy) — lets them remove it from their own feed only.
        // $can_delete: the caller actually authored/owns/sourced this content
        // (or is a site admin deleting content that originated here) — lets
        // them perform a real, federated delete. Mirrors core's
        // Zotlabs/Module/Item.php::get() (/item/drop/:id).
        $local_delete = ($uid && $uid == $i['uid']);

        $can_delete = (
            $ob_hash && in_array($ob_hash, [$i['author_xchan'], $i['owner_xchan'], $i['source_xchan']], true)
        );

        if (is_site_admin()) {
            $local_delete = true;
            if (intval($i['item_origin'])) {
                $can_delete = true;
            }
        }

        if (!($can_delete || $local_delete)) {
            json_return_and_die(['error' => 'Permission denied']);
        }

        if ($local_delete && !$can_delete) {
            // Local-only removal: just this one row (drop_item()'s internal
            // cascade also removes same-uid child comments under it). No sync
            // packet, no tag_deliver, no Notifier summon — nothing federates,
            // other copies of this post elsewhere are untouched.
            drop_item(intval($i['id']));
            json_return_and_die(['success' => true]);
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
        // Do NOT use item_normal() here — it restricts to item_type = ITEM_TYPE_POST (0),
        // which would exclude webpages, articles, wiki pages, etc. (same reasoning as
        // editItem()/deleteItem() below).

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
                        AND item.item_deleted = 0
                      LIMIT 1");
            if ($r)
                return $r[0];
        }

        // Fall back to any publicly accessible copy
        $permission_sql = item_permissions_sql(0, $ob_hash);
        $r = dbq("SELECT * FROM item
                  WHERE item.$col = '$midEsc'
                    AND item.item_deleted = 0
                    $permission_sql
                  ORDER BY item_wall DESC
                  LIMIT 1");

        return $r ? $r[0] : null;
    }

    // Scan body text for :shortcode: emoji recognized by get_emojis() and build
    // TERM_EMOJI term records so they federate as an AP Emoji tag (core mirror:
    // Zotlabs/Module/Item.php ~line 729), instead of surviving only as dead
    // shortcode text for remote instances that don't already know them.
    private static function buildEmojiTerms(int $profileUid, string $content): array
    {
        $terms = [];

        if (preg_match_all('/(\:(\w|\+|\-)+\:)(?=|[\!\.\?]|$)/', $content, $match)) {
            $emojis = get_emojis();
            foreach ($match[0] as $mtch) {
                $shortname = trim($mtch, ':');

                if (!isset($emojis[$shortname])) {
                    continue;
                }

                $emoji = $emojis[$shortname];

                $terms[] = [
                    'uid'    => $profileUid,
                    'ttype'  => TERM_EMOJI,
                    'otype'  => TERM_OBJ_POST,
                    'term'   => trim($mtch),
                    'url'    => z_root() . '/emoji/' . $shortname,
                    'imgurl' => z_root() . '/' . $emoji['filepath'],
                ];
            }
        }

        return $terms;
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
        array $term = [],
        array $attach = [],
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
        // Comments inherit the thread's privacy verbatim (core Item::post) —
        // deriving it from the ACL would downgrade a DM (private=2) to 1.
        $private = $isComment
            ? intval($parent['item_private'])
            : (!empty($acl['allow_cid']) || !empty($acl['allow_gid']) ? 1 : 0);

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
            'term' => $term,
            'attach' => $attach,
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
            'author'           => ['name' => '', 'address' => '', 'url' => '', 'hash' => '', 'photo' => ['src' => '', 'mimetype' => '']],
            'permalink'        => '',
            'viewer_liked'     => false,
            'viewer_disliked'  => false,
            'viewer_repeated'  => false,
            'viewer_attending' => false,
            'viewer_declining' => false,
            'viewer_maybe'     => false,
            'viewer_following' => false,
            'can_comment'      => false,
        ], $deleted ?: []);
    }

    // Shared reaction count subqueries string
    private static function reactionSubqueries(): string
    {
        return ReactionCounts::subqueries();
    }

    // Fetch fresh counts after a toggle — avoids a full item re-fetch
    private function fetchReactionCounts(string $mid): array
    {
        $midEsc = dbesc($mid);
        $uid = intval(local_channel());
        $normal = ReactionCounts::normalFlags();
        $r = dbq("SELECT
            (SELECT COUNT(DISTINCT r.author_xchan) FROM item r WHERE r.uid = $uid AND r.thr_parent = '$midEsc' AND r.item_thread_top = 0 AND r.obj_type != 'Answer' AND r.verb = 'Like'              AND $normal) AS like_count,
            (SELECT COUNT(DISTINCT r.author_xchan) FROM item r WHERE r.uid = $uid AND r.thr_parent = '$midEsc' AND r.item_thread_top = 0 AND r.obj_type != 'Answer' AND r.verb = 'Dislike'           AND $normal) AS dislike_count,
            (SELECT COUNT(DISTINCT r.author_xchan) FROM item r WHERE r.uid = $uid AND r.thr_parent = '$midEsc' AND r.item_thread_top = 0 AND r.obj_type != 'Answer' AND r.verb = '" . ACTIVITY_SHARE . "' AND $normal) AS announce_count,
            (SELECT COUNT(*) FROM item r WHERE r.thr_parent = '$midEsc' AND r.item_thread_top = 0 AND r.obj_type != 'Answer' AND r.verb = 'Accept'            AND $normal) AS attend_count,
            (SELECT COUNT(*) FROM item r WHERE r.thr_parent = '$midEsc' AND r.item_thread_top = 0 AND r.obj_type != 'Answer' AND r.verb = 'Reject'            AND $normal) AS decline_count,
            (SELECT COUNT(*) FROM item r WHERE r.thr_parent = '$midEsc' AND r.item_thread_top = 0 AND r.obj_type != 'Answer' AND r.verb = 'TentativeAccept'   AND $normal) AS maybe_count");

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

        $owner = null;
        if (($item['owner_xchan'] ?? '') !== ($item['author_xchan'] ?? '') && !empty($item['owner'])) {
            $x = $item['owner'];
            $owner = [
                'name'    => $x['xchan_name']            ?? '',
                'address' => $x['xchan_addr']            ?? '',
                'url'     => $x['xchan_url']             ?? '',
                'hash'    => $x['xchan_hash']            ?? '',
                'photo'   => [
                    'src'      => $x['xchan_photo_m']        ?? '',
                    'mimetype' => $x['xchan_photo_mimetype'] ?? '',
                ],
            ];
        }

        $attachRaw = $item['attach'] ?? '';
        $root = z_root();
        $attach = array_map(function (array $a) use ($root): array {
            if (isset($a['href']) && str_starts_with($a['href'], '/')) {
                $a['href'] = $root . $a['href'];
            }
            return $a;
        }, $attachRaw ? (json_decode($attachRaw, true) ?: []) : []);

        // Only top-level items can be pinned — skip the pconfig lookup for comments.
        $isPinned = false;
        if (intval($item['item_thread_top']) && !empty($item['uid']) && !empty($item['uuid'])) {
            $pinnedMidsRaw = get_pconfig(intval($item['uid']), 'pinned', ITEM_TYPE_POST, []);
            $pinnedMids    = array_map('unpack_link_id', is_array($pinnedMidsRaw) ? $pinnedMidsRaw : []);
            $isPinned      = in_array($item['uuid'], $pinnedMids, true);
        }

        return [
            'uuid' => $item['uuid'],
            'mid' => $item['mid'],
            'parent_mid' => $item['parent_mid'],
            'thr_parent' => $item['thr_parent'],
            'message_top' => intval($item['item_thread_top'])
                ? $item['mid']
                : ($item['thr_parent'] ?? $item['mid']),
            'created' => $item['created'],
            'edited' => $item['edited'],
            'commented' => $item['commented'] ?? $item['created'],
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
                intval($item['item_private']) === 2 ? 'direct_message' : null,
                intval($item['item_starred']) ? 'starred' : null,
                $isPinned ? 'pinned' : null,
                intval($item['item_unseen']) ? 'unseen' : null,
            ])),
            'author' => [
                'name'    => $item['author']['xchan_name']            ?? '',
                'address' => $item['author']['xchan_addr']            ?? '',
                'url'     => $item['author']['xchan_url']             ?? '',
                'hash'    => $item['author']['xchan_hash']            ?? '',
                'network' => $item['author']['xchan_network']         ?? '',
                'photo'   => [
                    'src'      => $item['author']['xchan_photo_m']        ?? '',
                    'mimetype' => $item['author']['xchan_photo_mimetype'] ?? '',
                ],
            ],
            'owner'            => $owner,
            'permalink'        => $item['plink'] ?? '',
            'viewer_liked'     => $liked,
            'viewer_disliked'  => $disliked,
            'viewer_repeated'  => $repeated,
            'viewer_attending' => $attending,
            'viewer_declining' => $declining,
            'viewer_maybe'     => $maybe,
            'viewer_following' => (bool)($item['viewer_following'] ?? false),
            // Same check core uses to decide whether to render a comment box
            // (comment_policy, comments_closed, nocomment, owner perms).
            'can_comment'      => (bool) can_comment_on_post($ob_hash, $item),
            'attach'           => $attach,
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

        require_once('include/text.php');
        $extraTerms = $extraContent ? self::buildEmojiTerms($uid, $extraContent) : [];

        $datarray = self::buildItemArray(
            profileUid: $uid,
            content: $content,
            title: '',
            mimetype: 'text/bbcode',
            acl: $acl,
            isWall: true,
            term: $extraTerms,
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

    // GET /api/item/:mid/compose
    // Returns the item's source fields for the edit composer. Full
    // [share …]…[/share] blocks are collapsed to compact [share=<id>][/share]
    // tags (the form the composer works with, mirroring core jot) so the
    // WYSIWYG never has to round-trip the attribute block.
    private function getComposeSource(string $mid): void
    {
        Auth::requireLocalGet();
        $ob_hash = get_observer_hash();

        $item = $this->resolveItem($mid, $ob_hash);
        if (!$item) {
            json_return_and_die(['error' => 'Item not found or permission denied']);
        }

        $body = $item['body'];
        if ($item['mimetype'] === 'text/bbcode') {
            $body = $this->collapseShareTags($body, $ob_hash);
        }

        json_return_and_die([
            'success'  => true,
            'body'     => $body,
            'title'    => $item['title'],
            'summary'  => $item['summary'],
            'mimetype' => $item['mimetype'],
        ]);
    }

    // GET /api/item/:id/sharepreview   (:id = numeric item id)
    // Returns the expanded [share …] block for a compact [share=<id>] tag so
    // the composer can render the reshared content inside the WYSIWYG.
    // Display-only: unlike the save-time expandShareTags (which must refuse
    // private items so they are never embedded into an outgoing body), the
    // preview may render anything the viewer can already see — including
    // their own or ACL-shared private posts.
    private function getSharePreview(string $id): void
    {
        Auth::requireLocalGet();

        $bb = '';
        $r = q("SELECT * FROM item WHERE id = %d LIMIT 1", intval($id));
        if ($r) {
            $sql_extra = item_permissions_sql(intval($r[0]['uid']));
            $v = q("SELECT * FROM item WHERE id = %d $sql_extra", intval($id));
            if ($v) {
                $bb = $this->buildShareBlock($v[0], forDisplay: true);
            }
        }

        if (!$bb) {
            json_return_and_die(['error' => 'Item not found or permission denied']);
        }

        json_return_and_die(['success' => true, 'bbcode' => $bb]);
    }

    // Expand compact [share=<item id>][/share] tags into the canonical
    // [share author=…]…[/share] block before storing — same mechanism as core
    // Item::post. Lib\Share enforces visibility (item_permissions_sql, no
    // private items), so a client-supplied id cannot leak restricted content.
    // Any content inside the compact tag is discarded, as core does.
    private function expandShareTags(string $body): string
    {
        if (!preg_match_all('/(\[share=(\d+)\](.*?)\[\/share\])/ism', $body, $match)) {
            return $body;
        }

        foreach ($match[2] as $i => $id) {
            $share = new \Zotlabs\Lib\Share(intval($id));
            $bb = $share->bbcode();

            if (!$bb) {
                // Share::bbcode() refuses posts that already contain [/share]
                // (nested reshares). Rebuild the block ourselves with the same
                // visibility rules Lib\Share applies.
                $r = q("SELECT * FROM item WHERE id = %d LIMIT 1", intval($id));
                if ($r && !intval($r[0]['item_private'])) {
                    $sql_extra = item_permissions_sql($r[0]['uid']);
                    $r = q("SELECT * FROM item WHERE id = %d $sql_extra", intval($id));
                    if ($r) {
                        $bb = $this->buildShareBlock($r[0]);
                    }
                }
            }

            if (!$bb) {
                // Silently dropping the tag would eat the reshared content on
                // save; refuse instead so the composer keeps the user's draft.
                Response::error(422, 'Shared post not found or cannot be reshared');
            }

            $body = str_replace($match[1][$i], $bb, $body);
        }

        return $body;
    }

    // Inverse of expandShareTags for the edit composer: replace each stored
    // top-level [share …message_id='…'…]…[/share] block with
    // [share=<id>][/share], resolving the shared item through the
    // permission-aware resolveItem. Blocks are located with a depth-aware
    // scan — nested reshares contain inner [/share] closers, and a non-greedy
    // regex would split the block and leave a stray outer [/share] behind.
    // Blocks whose target cannot be resolved are left untouched.
    private function collapseShareTags(string $body, string $ob_hash): string
    {
        $result = '';
        $cursor = 0;

        while (preg_match('/\[share\s/i', $body, $m, PREG_OFFSET_CAPTURE, $cursor)) {
            $start = $m[0][1];
            $end = self::findShareEnd($body, $start);
            if ($end < 0) {
                break; // unbalanced — leave the rest untouched
            }

            $result .= substr($body, $cursor, $start - $cursor);
            $block = substr($body, $start, $end - $start);

            // message_id must come from the outer block's attributes (before
            // the first ']'), never from a nested block's attributes.
            // Only collapse when save-time expandShareTags could re-expand the
            // tag (non-private bbcode target) — otherwise the stored block
            // would be lost on the next save. Unresolvable blocks stay
            // verbatim; the editor renders them from their own attributes.
            $collapsed = $block;
            if (preg_match("/^\[share\s[^\]]*message_id='([^']+)'/is", $block, $mm)) {
                $target = $this->resolveItem($mm[1], $ob_hash);
                if ($target && !intval($target['item_private']) && $target['mimetype'] === 'text/bbcode') {
                    $collapsed = '[share=' . intval($target['id']) . '][/share]';
                }
            }

            $result .= $collapsed;
            $cursor = $end;
        }

        return $result . substr($body, $cursor);
    }

    // End offset (exclusive) of the balanced [share]…[/share] block that
    // opens at $start, counting nested [share openings; -1 if unbalanced.
    private static function findShareEnd(string $body, int $start): int
    {
        $depth = 0;
        $pos = $start;

        while (preg_match('/\[share[=\s]|\[\/share\]/i', $body, $t, PREG_OFFSET_CAPTURE, $pos)) {
            $tok = $t[0][0];
            $tokPos = $t[0][1];
            $pos = $tokPos + strlen($tok);

            if (strcasecmp($tok, '[/share]') === 0) {
                $depth--;
                if ($depth <= 0) {
                    return $pos;
                }
            } else {
                $depth++;
            }
        }

        return -1;
    }

    // $forDisplay: composer previews may include private items the viewer
    // can already see; save-time expansion must never embed them.
    private function buildShareBlock(array $item, bool $forDisplay = false): string
    {
        if (($item['item_private'] && !$forDisplay) || $item['mimetype'] !== 'text/bbcode') {
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
