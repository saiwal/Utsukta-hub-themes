<?php
// packages/spa-core/php/Api/Handlers/Moderate.php
//
// Owner-only moderation queue for unsolicited content: comments, reactions
// (Like/Dislike/Announce), and wall posts stored with item_blocked =
// ITEM_MODERATED (see boot.php) because the sender failed the post_comments
// / post_wall permission check but the channel has "Accept unsolicited
// comments for moderation" enabled (Zotlabs/Lib/Activity.php,
// Zotlabs/Lib/Libzot.php, include/attach.php, include/photos.php all set
// this same flag). Mirrors Zotlabs/Module/Moderate.php's list/approve/drop,
// minus its visible_activity() thread rendering — that's what hides
// pending Likes from core's own /moderate page.

namespace Utsukta\SpaCore\Api\Handlers;

require_once('include/items.php');

use Zotlabs\Daemon\Master;
use Utsukta\SpaCore\Api\Auth;
use Utsukta\SpaCore\Api\Response;

class Moderate
{
    // GET  /api/moderate                  -> list pending items for local_channel()
    // GET  /api/moderate?mid=<mid>         -> pending Like/Dislike/Announce on one post/comment
    //                                         (feeds the inline "pending reactions" panel — comments
    //                                         get an inline approve/deny affordance instead, so this
    //                                         scoped view only needs to cover reactions)
    // POST /api/moderate/:id/approve      -> unblock + re-notify
    // POST /api/moderate/:id/drop         -> delete
    public function get(): void
    {
        $uid = Auth::requireLocalGet();
        $mid = trim($_GET['mid'] ?? '');

        // item_blocked = ITEM_MODERATED is overloaded: besides genuine
        // unsolicited third-party content, core also parks the channel's own
        // internal ActivityPub "Add to collection" bookkeeping records (verb
        // Add/Remove, self-authored — see addToCollectionAndSync() /
        // Activity::addToCollection() in core) under the same flag so they
        // stay hidden from normal stream queries. Restrict to the verbs that
        // are actually comments/reactions, and exclude anything the channel
        // authored about itself, so those never masquerade as pending review.
        $channel = \App::get_channel();
        $ownHash = $channel['channel_hash'] ?? '';
        $verbs   = $mid ? "'Like','Dislike','Announce'" : "'Create','Update','EmojiReact','Like','Dislike','Announce'";

        $rows = q(
            "SELECT * FROM item
             WHERE uid = %d AND item_blocked = %d AND item_deleted = 0
               AND verb IN ($verbs)
               AND author_xchan != '%s'
               " . ($mid ? "AND thr_parent = '%s'" : "") . "
             ORDER BY created DESC",
            intval($uid),
            intval(ITEM_MODERATED),
            dbesc($ownHash),
            ...($mid ? [dbesc($mid)] : [])
        );
        $rows = $rows ?: [];

        if ($rows) {
            xchan_query($rows, true);
        }

        // Replies/reactions carry no useful body of their own — resolve what
        // they're replying to/reacting on so the row is actually actionable.
        $targets = [];
        $parentMids = array_values(array_unique(array_filter(array_map(
            fn($r) => ($r['mid'] !== $r['thr_parent']) ? $r['thr_parent'] : null,
            $rows
        ))));
        if ($parentMids) {
            $inList = implode("','", array_map('dbesc', $parentMids));
            $parents = q("SELECT mid, title, body, plink FROM item WHERE uid = %d AND mid IN ('$inList')", intval($uid));
            foreach (($parents ?: []) as $p) {
                $targets[$p['mid']] = $p;
            }
        }

        Response::send(array_map(fn($r) => $this->formatPending($r, $targets), $rows));
    }

    public function post(): void
    {
        $uid = Auth::requireLocalJson();

        $id     = intval(\App::$argv[2] ?? 0);
        $action = \App::$argv[3] ?? '';

        if (!$id || !in_array($action, ['approve', 'drop'], true)) {
            Response::error(400, 'id and a valid action are required');
        }

        $r = q(
            "SELECT * FROM item WHERE id = %d AND uid = %d AND item_blocked = %d LIMIT 1",
            $id,
            intval($uid),
            intval(ITEM_MODERATED)
        );
        if (!$r) {
            Response::error(404, 'Pending item not found');
        }
        $item = $r[0];

        if ($action === 'approve') {
            q("UPDATE item SET item_blocked = 0 WHERE id = %d AND uid = %d", $id, intval($uid));
            $item['item_blocked'] = 0;
            item_update_parent_commented($item);
            Master::Summon(['Notifier', 'comment-new', $id]);
        } else {
            drop_item($id);
        }

        Response::send(['success' => true]);
    }

    private function formatPending(array $item, array $targets): array
    {
        $isReply = $item['mid'] !== $item['thr_parent'];
        $target  = $isReply ? ($targets[$item['thr_parent']] ?? null) : null;

        return [
            'iid'      => intval($item['id']),
            'uuid'     => $item['uuid'],
            'mid'      => $item['mid'],
            // For a reaction this is the mid of the post/comment reacted to —
            // lets the client show the flag icon only where something's queued.
            'thr_parent' => $item['thr_parent'],
            'verb'     => $item['verb'],
            'obj_type' => $item['obj_type'],
            'created'  => $item['created'],
            'is_reply' => $isReply,
            'title'    => $item['title'],
            'body'     => $item['body'],
            'author'   => [
                'name'  => Response::decodeEntities(urldecode($item['author']['xchan_name'] ?? '')),
                'url'   => $item['author']['xchan_url']  ?? '',
                'hash'  => $item['author']['xchan_hash'] ?? '',
                'photo' => [
                    'src'      => $item['author']['xchan_photo_m']        ?? '',
                    'mimetype' => $item['author']['xchan_photo_mimetype'] ?? '',
                ],
            ],
            'target' => $target ? [
                'title'     => $target['title'],
                'body'      => $target['body'],
                'permalink' => $target['plink'],
            ] : null,
        ];
    }
}
