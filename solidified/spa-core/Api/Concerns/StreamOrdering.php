<?php
// Api/Concerns/StreamOrdering.php
namespace Utsukta\SpaCore\Api\Concerns;

// ORDER BY expressions for the stream handlers (Network, Channel).
//
// The chronological orders are plain columns; the ranked ones ("reddit style")
// are built from the same correlated count subqueries ReactionCounts uses, so
// they correlate against an outer table aliased `item` — which both handlers'
// parent/flat queries satisfy.
final class StreamOrdering
{
    // Orders that do not put the newest item first. These force the threaded
    // path off nothing, but the client must not run its created-based
    // new-post poll while one is active.
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

    // Full ORDER BY expression (no DESC). Unknown keys fall back to created.
    //
    // ponytail: the ranked expressions are correlated subqueries, which no
    // index can cover — sorting scans the candidate parent set. The time-range
    // picker in the UI is the mitigation (it bounds the window via dbegin).
    // If that stops being enough, denormalise the counts onto `item` or
    // maintain a materialised ranking table; don't try to index this.
    public static function expr(string $order): string
    {
        $pg = defined('ACTIVE_DBTYPE') && defined('DBTYPE_POSTGRES')
            && ACTIVE_DBTYPE == DBTYPE_POSTGRES;

        switch ($order) {
            case 'commented':
                return 'item.commented';

            case 'top':
                return self::verbCount('Like');

            case 'discussed':
                return ReactionCounts::commentCountSubquery();

            case 'hot':
                // Reddit's hotness: log of the score plus a linear age term,
                // so a post needs ~10x the likes to outrank one 12.5h newer.
                $likes = self::verbCount('Like');
                $log   = $pg ? "LOG(GREATEST($likes, 1)::numeric)" : "LOG10(GREATEST($likes, 1))";
                $epoch = $pg ? 'EXTRACT(EPOCH FROM item.created)' : 'UNIX_TIMESTAMP(item.created)';
                return "($log + $epoch / 45000)";

            case 'controversial':
                // Engagement volume scaled down the more one-sided the split is.
                $likes    = self::verbCount('Like');
                $dislikes = self::verbCount('Dislike');
                $total    = "($likes + $dislikes)";
                $balance  = "ABS($likes - $dislikes)";
                // Postgres does integer division on bigint counts — cast.
                $cast     = $pg ? '::numeric' : '';
                return "($total * (1 - $balance$cast / GREATEST($total, 1)))";

            case 'created':
            case 'unthreaded':
            default:
                return 'item.created';
        }
    }

    // Same shape as the like_count/dislike_count subqueries in
    // ReactionCounts::subqueries() — kept in step with them deliberately, so a
    // post's rank matches the counts the client renders on it.
    private static function verbCount(string $verb): string
    {
        $normal = ReactionCounts::normalFlags();
        return "(SELECT COUNT(DISTINCT r.author_xchan) FROM item r
                 WHERE r.uid = item.uid AND r.thr_parent = item.mid
                   AND r.item_thread_top = 0 AND r.obj_type != 'Answer'
                   AND r.verb = '$verb' AND $normal)";
    }
}
