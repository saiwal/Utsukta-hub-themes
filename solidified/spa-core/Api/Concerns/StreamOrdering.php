<?php
// Api/Concerns/StreamOrdering.php
namespace Utsukta\SpaCore\Api\Concerns;

// ORDER BY clauses for the stream handlers (Network, Channel).
//
// The chronological orders are plain indexed columns. The ranked ones
// ("reddit style") need reaction counts, which live in sibling `item` rows
// rather than in a column — so each ranked order comes with a LEFT JOIN onto a
// pre-aggregated derived table, and the expression reads off that join.
//
// The joins exist for one reason: correlated scalar subqueries in ORDER BY are
// evaluated per candidate row (EXPLAIN: DEPENDENT SUBQUERY + filesort), and
// `controversial` textually contains four of them, so it paid 4N index dives
// per page. One grouped pass, hash-joined, replaces all of that — and each
// count is computed once per candidate no matter how often the formula
// mentions it.
final class StreamOrdering
{
    // Orders that do not put the newest item first. The client must not run
    // its created-based new-post poll while one is active, and these are the
    // orders whose result is worth caching.
    public const RANKED = ['top', 'hot', 'discussed', 'controversial'];

    public static function isRanked(string $order): bool
    {
        return in_array($order, self::RANKED, true);
    }

    // Whitelist of accepted `order` values (chronological ones included).
    public static function isValid(string $order): bool
    {
        return in_array($order, ['created', 'commented', 'unthreaded'], true)
            || self::isRanked($order);
    }

    /**
     * ORDER BY clause for an order key, plus the JOIN it needs.
     *
     * ponytail: a cold ranked page still filesorts the whole candidate set —
     * inherent to ranking without capping the candidate pool, which was a
     * deliberate call (exact rankings over approximate ones). CachesRanking
     * makes that cost once per 15 minutes instead of once per page. If it ever
     * needs to go lower, cap the pool in the inner query or denormalise the
     * counts onto `item`; the join below is already as cheap as this shape gets.
     *
     * @return array{join: string, order: string} `join` is empty for the
     *         chronological orders; `order` never carries DESC.
     *
     * The outer table must be aliased `item` (both handlers' queries are), and
     * the join fragments are scoped to $uid, matching how the count subqueries
     * in ReactionCounts correlate.
     */
    public static function clause(string $order, int $uid): array
    {
        $pg = defined('ACTIVE_DBTYPE') && defined('DBTYPE_POSTGRES')
            && ACTIVE_DBTYPE == DBTYPE_POSTGRES;

        // LEFT JOIN misses (a post nobody reacted to) come back NULL.
        $likes    = 'COALESCE(rx.likes, 0)';
        $dislikes = 'COALESCE(rx.dislikes, 0)';
        $comments = 'COALESCE(cx.comments, 0)';

        switch ($order) {
            case 'commented':
                return ['join' => '', 'order' => 'item.commented'];

            case 'top':
                return ['join' => self::reactionJoin($uid), 'order' => $likes];

            case 'discussed':
                return ['join' => self::commentJoin($uid), 'order' => $comments];

            case 'hot':
                // Reddit's hotness: log of the score plus a linear age term,
                // so a post needs ~10x the likes to outrank one 12.5h newer.
                $log   = $pg ? "LOG(GREATEST($likes, 1)::numeric)" : "LOG10(GREATEST($likes, 1))";
                $epoch = $pg ? 'EXTRACT(EPOCH FROM item.created)' : 'UNIX_TIMESTAMP(item.created)';
                return [
                    'join'  => self::reactionJoin($uid),
                    'order' => "($log + $epoch / 45000)",
                ];

            case 'controversial':
                // Engagement volume scaled down the more one-sided the split is.
                $total   = "($likes + $dislikes)";
                $balance = "ABS($likes - $dislikes)";
                // Postgres does integer division on bigint counts — cast.
                $cast    = $pg ? '::numeric' : '';
                return [
                    'join'  => self::reactionJoin($uid),
                    'order' => "($total * (1 - $balance$cast / GREATEST($total, 1)))",
                ];

            case 'created':
            case 'unthreaded':
            default:
                return ['join' => '', 'order' => 'item.created'];
        }
    }

    // Like/dislike counts per thread root, grouped the way
    // ReactionCounts::subqueries() correlates them: on thr_parent = the root's
    // mid, so only direct reactions to the root count, one vote per author
    // however many duplicate activities federation delivered.
    private static function reactionJoin(int $uid): string
    {
        $normal = ReactionCounts::normalFlags();
        return "LEFT JOIN (
                  SELECT r.thr_parent AS tp,
                         COUNT(DISTINCT CASE WHEN r.verb = 'Like'    THEN r.author_xchan END) AS likes,
                         COUNT(DISTINCT CASE WHEN r.verb = 'Dislike' THEN r.author_xchan END) AS dislikes
                  FROM item r
                  WHERE r.uid = $uid
                    AND r.item_thread_top = 0
                    AND r.obj_type != 'Answer'
                    AND r.verb IN ('Like', 'Dislike')
                    AND $normal
                  GROUP BY r.thr_parent
                ) rx ON rx.tp = item.mid ";
    }

    // Reply counts, grouped on r.parent — NOT thr_parent. This mirrors
    // ReactionCounts::commentCountSubquery(), which correlates on
    // `r.parent = item.id`: "Most discussed" counts the whole thread including
    // nested replies, where the reaction counts only count direct children of
    // the root. Grouping these by thr_parent would quietly redefine the order.
    private static function commentJoin(int $uid): string
    {
        $normal = ReactionCounts::normalFlags();
        return "LEFT JOIN (
                  SELECT r.parent AS pid, COUNT(*) AS comments
                  FROM item r
                  WHERE r.uid = $uid
                    AND r.item_thread_top = 0
                    AND r.obj_type != 'Answer'
                    AND r.verb IN ('Create', 'Update', 'EmojiReact')
                    AND $normal
                  GROUP BY r.parent
                ) cx ON cx.pid = item.id ";
    }
}
