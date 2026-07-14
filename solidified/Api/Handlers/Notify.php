<?php
/**
 * Theme\Solidified\Api\Handlers\Notify
 *
 * Routes:
 *   GET /api/notify/:id  → look up one `notify` table row, mark it seen
 *                          (mirrors Zotlabs/Module/Notify.php's `view` action),
 *                          and return its target link for the SPA to redirect to.
 */

namespace Theme\Solidified\Api\Handlers;

use Theme\Solidified\Api\Auth;
use Theme\Solidified\Api\Response;

class Notify
{
    public function get(): void
    {
        $uid = Auth::requireLocalGet();
        $id  = \App::$argv[2] ?? null;

        if ($id === null || !ctype_digit((string)$id)) {
            Response::error(400, 'id required');
        }

        $r = q(
            "SELECT * FROM notify WHERE id = %d AND uid = %d LIMIT 1",
            intval($id),
            intval($uid)
        );

        if (!$r) {
            Response::error(404, 'Not found');
        }

        $args = ['channel_id' => $uid, 'update' => 'unset'];
        call_hooks('update_unseen', $args);

        if ($args['update'] === 'unset' || intval($args['update'])) {
            q(
                "UPDATE notify SET seen = 1 WHERE ((parent != '' AND parent = '%s' AND otype = '%s') OR link = '%s') AND uid = %d",
                dbesc($r[0]['parent']),
                dbesc($r[0]['otype']),
                dbesc($r[0]['link']),
                intval($uid)
            );
        }

        Response::send(['link' => $r[0]['link']]);
    }
}
