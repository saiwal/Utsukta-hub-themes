<?php
// Api/ForumNotify.php
namespace Utsukta\SpaCore\Api;

use Zotlabs\Lib\Enotify;

/**
 * Notify the sender of a forum DM about activity on the forum's repost of it.
 *
 * A DM to a group actor is not redelivered as-is: start_delivery_chain()
 * (include/items.php, the `$group && !$parent` branch) stores a *new* thread,
 * new mid, authored and owned by the forum, whose body is a [share] of the
 * DM. The sender gets that thread like any member, but core's
 * send_status_notifications() only notifies the thread owner and people who
 * already took part, and the sender is neither, so comments on "their" post
 * reached them silently.
 *
 * Runs from item_stored, i.e. just before send_status_notifications() for the
 * same row, and uses the same /display/<uuid> link, so core's own link check
 * stops it notifying twice once the sender has joined the thread.
 */
class ForumNotify
{
    /** item_stored — $item is the freshly stored row. */
    public static function onItemStored(array $item): void
    {
        if ($item['mid'] === $item['parent_mid'] || intval($item['item_deleted'] ?? 0)) {
            return;
        }

        $uid = intval($item['uid']);
        $ch = q("select channel_hash from channel where channel_id = %d limit 1", intval($uid));
        if (!$ch || $item['author_xchan'] === $ch[0]['channel_hash']) {
            return;
        }
        $me = $ch[0]['channel_hash'];

        $p = q("select item.id, item.mid, item.body, item.author_xchan from item
            left join xchan on xchan_hash = item.author_xchan
            where item.id = %d and item.uid = %d and xchan_pubforum = 1 limit 1",
            intval($item['parent']),
            intval($uid)
        );
        if (!$p || !self::isRepostOfMine($p[0], $me)) {
            return;
        }
        $parent = $p[0];

        $isReaction = activity_match($item['verb'], ['Like', 'Dislike', ACTIVITY_LIKE, ACTIVITY_DISLIKE, 'Announce']);
        // The forum boosts each comment into its members' timelines; that is
        // delivery plumbing, not someone reacting to the post.
        if ($isReaction && $item['author_xchan'] === $parent['author_xchan']) {
            return;
        }

        // Unfollowed thread: same signal core reads.
        $ignored = q("select id from item where parent = %d and uid = %d and author_xchan = '%s'
            and verb in ('Ignore', '%s') limit 1",
            intval($parent['id']),
            intval($uid),
            dbesc($me),
            dbesc(ACTIVITY_UNFOLLOW)
        );
        if ($ignored) {
            return;
        }

        $link = z_root() . '/display/' . $item['uuid'];
        if (q("select id from notify where link = '%s' and uid = %d limit 1", dbesc($link), intval($uid))) {
            return;
        }

        $parentId = $parent['id'];
        $parentMid = $parent['mid'];
        if ($isReaction && $item['thr_parent'] !== $parent['mid']) {
            $t = q("select id from item where mid = '%s' and uid = %d limit 1", dbesc($item['thr_parent']), intval($uid));
            if ($t) {
                $parentId = $t[0]['id'];
                $parentMid = $item['thr_parent'];
            }
        }

        Enotify::submit([
            'type'       => $isReaction ? NOTIFY_LIKE : ((intval($item['item_private']) === 2) ? NOTIFY_MAIL : NOTIFY_COMMENT),
            'from_xchan' => $item['author_xchan'],
            'to_xchan'   => $me,
            'item'       => $item,
            'link'       => $link,
            'verb'       => $item['verb'],
            'otype'      => 'item',
            'parent'     => $parentId,
            'parent_mid' => $parentMid,
        ]);
    }

    /**
     * Does this forum thread root share a post $me wrote? Read from the
     * share's portable_id (the author's xchan hash), not by looking the
     * original up: a post made on the forum's own wall is stored only in the
     * forum's channel, so the sender never holds a copy. Only the first
     * [share] counts: a DM that itself quotes my post is wrapped as an outer
     * share by its own author.
     */
    public static function isRepostOfMine(array $parent, string $me): bool
    {
        return preg_match("/\\[share\\b[^\\]]*\\bportable_id='([^']+)'/", (string) $parent['body'], $m)
            && $m[1] === $me;
    }
}
