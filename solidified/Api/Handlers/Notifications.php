<?php
/**
 * Theme\Solidified\Api\Handlers\Notifications
 *
 * Routes:
 *   GET /api/notifications → full "System Notifications" list (mirrors
 *   Zotlabs/Module/Notifications.php::get()): up to 50 `notify` rows, unseen
 *   first, padded with seen rows so the SPA can show more than the
 *   unseen-only set /sse_bs/notify (and the SPA's /notify page) return.
 */

namespace Theme\Solidified\Api\Handlers;

use Theme\Solidified\Api\Auth;
use Theme\Solidified\Api\Response;

class Notifications
{
    public function get(): void
    {
        $uid = Auth::requireLocalGet();

        require_once('include/bbcode.php');

        $countRow = q(
            "SELECT count(*) AS total FROM notify WHERE uid = %d AND seen = 0",
            intval($uid)
        );
        $unseenTotal = intval($countRow[0]['total'] ?? 0);

        if ($unseenTotal > 49) {
            $rows = q(
                "SELECT * FROM notify WHERE uid = %d AND seen = 0 ORDER BY created DESC LIMIT 50",
                intval($uid)
            );
        } else {
            $unseen = q(
                "SELECT * FROM notify WHERE uid = %d AND seen = 0 ORDER BY created DESC LIMIT 50",
                intval($uid)
            );
            $seen = q(
                "SELECT * FROM notify WHERE uid = %d AND seen = 1 ORDER BY created DESC LIMIT %d",
                intval($uid),
                intval(50 - $unseenTotal)
            );
            $rows = array_merge($unseen ?: [], $seen ?: []);
        }

        $entries = array_map(static function (array $row): array {
            $message = trim(strip_tags(bbcode($row['msg'])));
            if (strpos($message, $row['xname']) === 0) {
                $message = substr($message, strlen($row['xname']) + 1);
            }

            return [
                'notify_id'   => intval($row['id']),
                'notify_link' => ($row['ntype'] == NOTIFY_INTRO)
                    ? z_root() . '/notify/view/' . $row['id']
                    : $row['link'],
                'name'        => $row['xname'],
                'url'         => $row['url'],
                'photo'       => $row['photo'],
                'when'        => datetime_convert('UTC', date_default_timezone_get(), $row['created']),
                'message'     => $message,
                'seen'        => (bool) $row['seen'],
                'b64mid'      => ($row['otype'] == 'item') ? $row['hash'] : '',
            ];
        }, $rows ?: []);

        Response::send($entries);
    }
}
