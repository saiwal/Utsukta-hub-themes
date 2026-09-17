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

    // A no-op upper bound, ANDed into a stream query whose WHERE carries
    // `item_wall = 1`.
    //
    // `created` and `commented` each have a standalone index besides the
    // uid-prefixed ones, and for `WHERE item.uid = X AND item.item_wall = 1 ...
    // ORDER BY item.created DESC LIMIT 10` MySQL reads that standalone index
    // newest-first across *all* channels, fetching each row to test the flags
    // (EXPLAIN: type index, key created). Wall posts are a small slice of what
    // a uid receives, so the walk runs off the end of the table and the request
    // times out — seen live on a 11.4 hub where every unfiltered /spa/channel
    // 504'd at 60s while `?tag=x` answered instantly.
    //
    // An upper bound on the sort column turns the uid index into a *range* the
    // optimizer prefers: it walks (uid, created) backwards and stops at LIMIT.
    //
    // Scope matters. `item_wall = 1` is the trigger — ablation on a real
    // database: remove it and the optimizer picks a uid_* index_merge on its
    // own; keep it and nothing else (the abook join, the verb whitelist,
    // item_private, id=parent vs mid=parent_mid) changes the outcome. So this
    // belongs only on the wall queries. Anchoring /network or /pubstream, which
    // have no wall filter, only replaces a working index_merge with a range
    // scan that reads full rows — measurably more I/O on the most-polled
    // endpoint there is. Core has exactly one query with the trigger, its
    // channel module, and it is guarded by an accidental `ORDER BY ..., item_id`
    // (an alias for item.parent) — which is why classic Hubzilla never hit this.
    //
    // The bound is deliberately a far-future constant, not NOW(): scheduled
    // posts carry a future `created` and the owner is meant to see them. Unlike
    // FORCE INDEX it is a plain predicate, so a tag/search filter can still win
    // a narrower plan, and it costs Postgres nothing.
    private const FAR_FUTURE = '9999-12-31 23:59:59';

    public static function indexAnchor(string $orderExpr, string $alias = 'item'): string
    {
        // Only a bare indexed column can be served from an index in the first
        // place; the ranked orders sort by an expression over a join, so there
        // is no bad plan there to steer away from.
        $col = preg_quote($alias, '/');
        if (!preg_match("/^$col\\.(created|commented)$/", trim($orderExpr), $m)) {
            return '';
        }

        return " AND $alias.{$m[1]} <= '" . self::FAR_FUTURE . "' ";
    }

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
    public static function clause(string $order, int $uid, string $dbegin = ''): array
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
                return ['join' => self::reactionJoin($uid, $dbegin), 'order' => $likes];

            case 'discussed':
                return ['join' => self::commentJoin($uid, $dbegin), 'order' => $comments];

            case 'hot':
                // Reddit's hotness: log of the score plus a linear age term,
                // so a post needs ~10x the likes to outrank one 12.5h newer.
                $log   = $pg ? "LOG(GREATEST($likes, 1)::numeric)" : "LOG10(GREATEST($likes, 1))";
                $epoch = $pg ? 'EXTRACT(EPOCH FROM item.created)' : 'UNIX_TIMESTAMP(item.created)';
                return [
                    'join'  => self::reactionJoin($uid, $dbegin),
                    'order' => "($log + $epoch / 45000)",
                ];

            case 'controversial':
                // Engagement volume scaled down the more one-sided the split is.
                $total   = "($likes + $dislikes)";
                $balance = "ABS($likes - $dislikes)";
                // Postgres does integer division on bigint counts — cast.
                $cast    = $pg ? '::numeric' : '';
                return [
                    'join'  => self::reactionJoin($uid, $dbegin),
                    'order' => "($total * (1 - $balance$cast / GREATEST($total, 1)))",
                ];

            case 'created':
            case 'unthreaded':
            default:
                return ['join' => '', 'order' => 'item.created'];
        }
    }

    // A ranged view ("Top (month)") still aggregates every reaction the channel
    // ever received, because the range only bounds the *posts*. A reaction
    // cannot predate the post it reacts to, so the same bound applies to the
    // reaction rows: anything older belongs to a post the range already
    // excluded from the candidate set. Measured on a copy of `item` carrying
    // three years of reactions, "Top (month)" went from 10,554 to 3,185 index
    // reads, and the ranked id list came back identical bounded vs unbounded.
    //
    // Valid only because the callers put the same `dbegin` on the candidate
    // query itself (Channel/Network both do, threaded and flat). The day of
    // slack is for federated activities whose remote `created` runs slightly
    // ahead of the local copy of the post.
    private static function sinceClause(string $dbegin): string
    {
        if ($dbegin === '') {
            return '';
        }

        return " AND r.created >= '"
            . dbesc(datetime_convert('UTC', 'UTC', $dbegin . ' - 1 day')) . "' ";
    }

    // Like/dislike counts per thread root, grouped the way
    // ReactionCounts::subqueries() correlates them: on thr_parent = the root's
    // mid, so only direct reactions to the root count, one vote per author
    // however many duplicate activities federation delivered.
    private static function reactionJoin(int $uid, string $dbegin = ''): string
    {
        $normal = ReactionCounts::normalFlags();
        $since  = self::sinceClause($dbegin);
        return "LEFT JOIN (
                  SELECT r.thr_parent AS tp,
                         COUNT(DISTINCT CASE WHEN r.verb = 'Like'    THEN r.author_xchan END) AS likes,
                         COUNT(DISTINCT CASE WHEN r.verb = 'Dislike' THEN r.author_xchan END) AS dislikes
                  FROM item r
                  WHERE r.uid = $uid
                    AND r.item_thread_top = 0
                    AND r.obj_type != 'Answer'
                    AND r.verb IN ('Like', 'Dislike')
                    AND $normal $since
                  GROUP BY r.thr_parent
                ) rx ON rx.tp = item.mid ";
    }

    // Reply counts, grouped on r.parent — NOT thr_parent. This mirrors
    // ReactionCounts::commentCountSubquery(), which correlates on
    // `r.parent = item.id`: "Most discussed" counts the whole thread including
    // nested replies, where the reaction counts only count direct children of
    // the root. Grouping these by thr_parent would quietly redefine the order.
    private static function commentJoin(int $uid, string $dbegin = ''): string
    {
        $normal = ReactionCounts::normalFlags();
        $since  = self::sinceClause($dbegin);
        return "LEFT JOIN (
                  SELECT r.parent AS pid, COUNT(*) AS comments
                  FROM item r
                  WHERE r.uid = $uid
                    AND r.item_thread_top = 0
                    AND r.obj_type != 'Answer'
                    AND r.verb IN ('Create', 'Update', 'EmojiReact')
                    AND $normal $since
                  GROUP BY r.parent
                ) cx ON cx.pid = item.id ";
    }
}
