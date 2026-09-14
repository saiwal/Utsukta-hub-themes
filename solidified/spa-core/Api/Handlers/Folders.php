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

            $unseen = "(i.item_unseen = 1 OR EXISTS (
                SELECT 1 FROM item cu
                WHERE cu.uid = i.uid AND cu.parent = i.parent
                  AND cu.item_unseen = 1 AND cu.item_thread_top = 0
            ))";

            $not_trash = "i.id NOT IN (SELECT oid FROM term
                WHERE ttype = " . intval(TERM_FILE) . " AND uid = i.uid
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

            $cr = q(
                "SELECT SUM(CASE WHEN i.item_private = 2 THEN 1 ELSE 0 END) AS dm,
                        SUM(CASE WHEN i.item_private IN (0, 1) THEN 1 ELSE 0 END) AS all_
                 FROM item i
                 WHERE i.uid = %d AND i.item_thread_top = 1
                   $item_normal AND $unseen AND $not_trash",
                intval($uid)
            );

            Response::send($folders, [
                'starred_count' => $starredCount,
                'unread_all' => $cr ? (int) $cr[0]['all_'] : 0,
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
