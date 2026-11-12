<?php
namespace Utsukta\SpaCore\Api\Handlers;

use Utsukta\SpaCore\Api\Auth;
use Utsukta\SpaCore\Api\Response;

require_once 'include/items.php';

class Folders
{
    public function get(): void
    {
        Auth::RequireLocalGet();

        $uid = local_channel();

        if (($_GET['counts'] ?? '') === '1') {
            // These badges must agree with what the lists beside them show, so
            // every clause here mirrors HqMessages: thread tops only, the same
            // "unread" definition (the thread has an unseen message *anywhere*
            // in it, not just at the top), and trashed items excluded from
            // everything but Trash itself.
            $item_normal = HqMessages::itemNormalSql($uid, 'i');

            // Both of these are IN (...) against a *constant* uid rather than
            // EXISTS correlated on i.uid: every row here belongs to $uid
            // anyway, and the correlation was the whole cost. Correlated,
            // EXPLAIN showed two DEPENDENT SUBQUERYs re-run per candidate
            // thread top (i.e. per delivered thread in the channel, tens of
            // thousands of index lookups a request); uncorrelated, both
            // become a MATERIALIZED subquery evaluated once.
            $unseen = "(i.item_unseen = 1 OR i.parent IN (
                SELECT cu.parent FROM item cu
                WHERE cu.uid = " . intval($uid) . "
                  AND cu.item_unseen = 1 AND cu.item_thread_top = 0
            ))";

            $not_trash = "i.id NOT IN (SELECT oid FROM term
                WHERE ttype = " . intval(TERM_FILE) . " AND uid = " . intval($uid) . "
                AND term = '" . protect_sprintf(dbesc(HqMessages::TRASH)) . "')";

            // Per folder. LEFT JOIN, not an inner one: a folder you created
            // but whose contents aren't listable (or that you emptied) must
            // still appear in the sidebar with a zero, or it silently
            // disappears and there is no way to file anything into it again.
            // The Trash folder is the one place trashed items count.
            $counts = q(
                "SELECT t.term,
                        SUM(CASE WHEN i.id IS NOT NULL THEN 1 ELSE 0 END) AS cnt,
                        SUM(CASE WHEN i.id IS NOT NULL AND $unseen THEN 1 ELSE 0 END) AS unread
                 FROM term t
                 LEFT JOIN item i
                        ON i.id = t.oid AND i.uid = t.uid
                       AND i.item_thread_top = 1 $item_normal
                       AND (t.term = '" . protect_sprintf(dbesc(HqMessages::TRASH)) . "' OR $not_trash)
                 WHERE t.uid = %d AND t.ttype = %d
                 GROUP BY t.term
                 ORDER BY t.term ASC",
                intval($uid),
                intval(TERM_FILE)
            );
            $folders = array_map(fn($row) => [
                'name' => $row['term'],
                'count' => (int) $row['cnt'],
                'unread' => (int) $row['unread'],
            ], $counts ?: []);

            $sr = q(
                "SELECT COUNT(*) AS cnt FROM item i
                 WHERE i.uid = %d AND i.item_starred = 1 AND i.item_thread_top = 1
                   $item_normal AND $not_trash",
                intval($uid)
            );
            $starredCount = $sr ? (int) $sr[0]['cnt'] : 0;

            // DMs only. This used to count unread non-DM threads too, but
            // nothing rendered that number — an "all messages" badge lit by
            // every delivered post is permanently on and says nothing — and
            // counting it is what made this the expensive query here: without
            // the item_private filter the planner drives off
            // uid_item_thread_top, i.e. one pass over every thread ever
            // delivered to the channel, where the other two queries are
            // bounded by what you filed and what you starred. With it, this
            // rides uid_item_private and scales with your DMs.
            $cr = q(
                "SELECT COUNT(*) AS dm
                 FROM item i
                 WHERE i.uid = %d AND i.item_private = 2 AND i.item_thread_top = 1
                   $item_normal AND $unseen AND $not_trash",
                intval($uid)
            );

            Response::send($folders, [
                'starred_count' => $starredCount,
                'unread_direct' => $cr ? (int) $cr[0]['dm'] : 0,
            ]);
        } else {
            $r = q(
                "SELECT DISTINCT term FROM term WHERE uid = %d AND ttype = %d ORDER BY term ASC",
                intval($uid),
                intval(TERM_FILE)
            );
            $folders = $r ? array_column($r, 'term') : [];
        }

        Response::send($folders);
    }
}
