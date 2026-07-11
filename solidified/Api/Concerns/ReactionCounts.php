<?php
// Api/Concerns/ReactionCounts.php
namespace Theme\Solidified\Api\Concerns;

// Reaction/comment count SQL fragments shared by the stream handlers.
// Mirrors core item_reaction_sql() (include/items.php): only rows that pass
// the item_normal() flag set, sit below the thread top, and are not poll
// answers may contribute to a count; comments are the Create/Update/EmojiReact
// allowlist rather than a verb blocklist — otherwise Follow/Ignore (thread
// subscriptions) and Add/Remove inflate reply counts, and hidden group-boost
// Announce activities produce ghost repeat counts on forum posts.
final class ReactionCounts
{
    // item_normal() flags minus item_type, which differs per module
    // (article comments are ITEM_TYPE_ARTICLE, post comments ITEM_TYPE_POST)
    public static function normalFlags(string $alias = 'r'): string
    {
        return "$alias.item_deleted = 0 AND $alias.item_hidden = 0 AND $alias.item_unpublished = 0
                AND $alias.item_pending_remove = 0 AND $alias.item_blocked = 0 AND $alias.item_delayed = 0";
    }

    // Correlated subquery counting the visible replies of the outer `item` row
    // (whole thread for a thread top, no alias appended)
    public static function commentCountSubquery(): string
    {
        $normal = self::normalFlags();
        return "(SELECT COUNT(*) FROM item r
                 WHERE r.parent = item.id
                   AND r.item_thread_top = 0
                   AND r.obj_type != 'Answer'
                   AND r.verb IN ('Create','Update','EmojiReact')
                   AND $normal)";
    }

    // Full SELECT fragment: like/dislike/announce/comment counts plus the
    // reaction_verbs blob used to derive the viewer_* flags. Correlates
    // against an outer table aliased `item`.
    public static function subqueries(): string
    {
        $normal = self::normalFlags();
        return "
            (SELECT COUNT(DISTINCT r.author_xchan) FROM item r WHERE r.uid = item.uid AND r.thr_parent = item.mid AND r.item_thread_top = 0 AND r.obj_type != 'Answer' AND r.verb = 'Like'    AND $normal) AS like_count,
            (SELECT COUNT(DISTINCT r.author_xchan) FROM item r WHERE r.uid = item.uid AND r.thr_parent = item.mid AND r.item_thread_top = 0 AND r.obj_type != 'Answer' AND r.verb = 'Dislike' AND $normal) AS dislike_count,
            (SELECT COUNT(DISTINCT r.author_xchan) FROM item r WHERE r.uid = item.uid AND r.thr_parent = item.mid AND r.item_thread_top = 0 AND r.obj_type != 'Answer' AND r.verb = '" . ACTIVITY_SHARE . "' AND $normal) AS announce_count,
            " . self::commentCountSubquery() . " AS comment_count,
            (SELECT GROUP_CONCAT(verb, ':', author_xchan SEPARATOR '|')
             FROM item r
             WHERE r.parent = item.parent
               AND r.thr_parent = item.mid
               AND r.item_thread_top = 0
               AND r.verb IN ('Like','Dislike','Announce','Accept','Reject','TentativeAccept')
               AND $normal) AS reaction_verbs";
    }
}
