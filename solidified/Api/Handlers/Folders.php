<?php
namespace Theme\Solidified\Api\Handlers;

use Theme\Solidified\Api\Auth;
use Theme\Solidified\Api\Response;

class Folders
{
    public function get(): void
    {
        Auth::RequireLocalGet();

        $uid = local_channel();

        if (($_GET['counts'] ?? '') === '1') {
            $r = q(
                "SELECT term, COUNT(*) AS cnt FROM term WHERE uid = %d AND ttype = %d GROUP BY term ORDER BY term ASC",
                intval($uid),
                intval(TERM_FILE)
            );
            $folders = $r ? array_map(fn($row) => ['name' => $row['term'], 'count' => (int) $row['cnt']], $r) : [];

            $sr = q(
                "SELECT COUNT(*) AS cnt FROM item WHERE uid = %d AND item_starred = 1 AND item_deleted = 0",
                intval($uid)
            );
            $starredCount = $sr ? (int) $sr[0]['cnt'] : 0;

            Response::send($folders, ['starred_count' => $starredCount]);
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
