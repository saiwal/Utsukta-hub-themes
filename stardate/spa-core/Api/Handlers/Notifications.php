<?php
/**
 * Utsukta\SpaCore\Api\Handlers\Notifications
 *
 * Routes:
 *   GET /spa/notifications → full "System Notifications" list (mirrors
 *   Zotlabs/Module/Notifications.php::get()): up to 50 `notify` rows, unseen
 *   first, padded with seen rows so the SPA can show more than the
 *   unseen-only set /sse_bs/notify (and the SPA's /notify page) return.
 *   POST /spa/notifications/markread {keys: [...]} → core's
 *   /notifications?markRead=<key>, several buckets in one request.
 */

namespace Utsukta\SpaCore\Api\Handlers;

use Utsukta\SpaCore\Api\Auth;
use Utsukta\SpaCore\Api\Response;
use Utsukta\SpaCore\Api\Concerns\FiltersBlockedChannels;

class Notifications
{
    use FiltersBlockedChannels;

    public function get(): void
    {
        $uid = Auth::requireLocalGet();

        require_once('include/bbcode.php');

        // superblock's enotify_format hook hides a blocked sender's notices;
        // this list reads `notify` directly and never runs it.
        $bsql = protect_sprintf($this->blockedSqlClause('hash', $this->blockedXchans($uid)));

        $countRow = q(
            "SELECT count(*) AS total FROM notify WHERE uid = %d AND seen = 0 $bsql",
            intval($uid)
        );
        $unseenTotal = intval($countRow[0]['total'] ?? 0);

        if ($unseenTotal > 49) {
            $rows = q(
                "SELECT * FROM notify WHERE uid = %d AND seen = 0 $bsql ORDER BY created DESC LIMIT 50",
                intval($uid)
            );
        } else {
            $unseen = q(
                "SELECT * FROM notify WHERE uid = %d AND seen = 0 $bsql ORDER BY created DESC LIMIT 50",
                intval($uid)
            );
            $seen = q(
                "SELECT * FROM notify WHERE uid = %d AND seen = 1 $bsql ORDER BY created DESC LIMIT %d",
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
                // ntype is a bitmask (boot.php NOTIFY_*), so == misses any
                // composite value. HqMessages::sendNotices() gets this right.
                'notify_link' => (intval($row['ntype']) & NOTIFY_INTRO)
                    ? z_root() . '/notify/view/' . $row['id']
                    : $row['link'],
                'name'        => Response::decodeEntities($row['xname']),
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

    public function post(): void
    {
        $uid = Auth::requireLocalJson();
        if ((\App::$argv[2] ?? '') !== 'markread') {
            Response::error(404, 'Not found');
        }
        $keys = array_values(array_filter((array) (Auth::$parsedBody['keys'] ?? []), 'is_string'));

        // Same buckets as core's Notifications::get() markRead switch, but
        // core runs `UPDATE item … WHERE uid AND item_unseen = 1 AND …`, which
        // ranges over uid_item_unseen and next-key-locks *every* unseen row of
        // the channel — so the per-forum requests of "mark all read" queued
        // behind each other. A plain SELECT takes no locks; updating by
        // primary key then locks only the rows that actually change.
        $where = [];
        $forums = [];
        foreach ($keys as $key) {
            if (str_starts_with($key, 'forum_')) {
                $forums[] = intval(substr($key, 6));
                continue;
            }
            switch ($key) {
                case 'dm':
                    $where[] = 'item_private = 2';
                    break;
                case 'network':
                    $where[] = 'item_wall = 0 AND item_private IN (0, 1)';
                    break;
                case 'home':
                    $where[] = 'item_wall = 1 AND item_private IN (0, 1)';
                    break;
                case 'all_events':
                    $evdays = intval(get_pconfig($uid, 'system', 'evdays', 3));
                    q("UPDATE event SET dismissed = 1 WHERE uid = %d AND dismissed = 0 AND dtstart < '%s' AND dtstart > '%s'",
                        intval($uid),
                        dbesc(datetime_convert('UTC', date_default_timezone_get(), 'now + ' . $evdays . ' days')),
                        dbesc(datetime_convert('UTC', date_default_timezone_get(), 'now - 1 days'))
                    );
                    break;
                case 'notify':
                    q("UPDATE notify SET seen = 1 WHERE seen = 0 AND uid = %d", intval($uid));
                    break;
                case 'pubs':
                    $_SESSION['sse_loadtime'] = datetime_convert();
                    $_SESSION['static_loadtime'] = datetime_convert();
                    break;
            }
        }

        if ($forums) {
            $abooks = q("SELECT abook_xchan FROM abook WHERE abook_channel = %d AND abook_id IN (%s)",
                intval($uid),
                implode(',', $forums)
            );
            if ($abooks) {
                $where[] = "owner_xchan IN ('" . implode("','", array_map(fn($a) => dbesc($a['abook_xchan']), $abooks)) . "') AND item_wall = 0 AND item_private IN (0, 1)";
            }
        }

        if ($where) {
            $rows = q("SELECT id FROM item WHERE uid = %d AND item_unseen = 1 AND ((" . implode(') OR (', $where) . "))",
                intval($uid)
            );
            foreach (array_chunk(array_column($rows ?: [], 'id'), 500) as $ids) {
                q("UPDATE item SET item_unseen = 0 WHERE id IN (%s)", implode(',', array_map('intval', $ids)));
            }
        }

        Response::send(['ok' => true]);
    }
}
