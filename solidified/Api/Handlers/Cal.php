<?php
namespace Theme\Solidified\Api\Handlers;

use Theme\Solidified\Api\Auth;
use Theme\Solidified\Api\Response;
use App;

require_once('include/datetime.php');
require_once('include/event.php');
require_once('include/items.php');

/**
 * GET /api/cal/:nick
 *   ?start=2026-05-01&end=2026-06-01   — calendar feed (ISO dates)
 *   ?id=<event_id>                      — single event detail (HTML + data)
 *   (no params)                         — upcoming events, next 60 days
 *
 * Response envelope:
 *   { data: CalEvent[] }
 */
class Cal
{
    public function get(): void
    {
        if (observer_prohibited()) {
            Response::error(403, 'Observers prohibited');
        }

        // ── Resolve channel ───────────────────────────────────────────────────
        $nick = \App::$argv[2] ?? '';
        if (!$nick) {
            Response::error(400, 'Channel nick required');
        }

        $channelx = channelx_by_nick($nick);
        if (!$channelx) {
            Response::error(404, 'Channel not found');
        }

        $channel_id = intval($channelx['channel_id']);

        // ── Permission check — reuse stream permission (same as core Cal) ─────
        if (!perm_is_allowed($channel_id, get_observer_hash(), 'view_stream')) {
            Response::error(403, 'Permission denied');
        }

        // ── SQL permission clause ─────────────────────────────────────────────
        $sql_extra = permissions_sql($channel_id, get_observer_hash(), 'event');

        // Suppress birthdays when the observer cannot see contacts, or when
        // the channel owner hides their friends list. Direct query avoids
        // the unavailable Profile::load() helper.
        if (!perm_is_allowed($channel_id, get_observer_hash(), 'view_contacts')) {
            $sql_extra .= " and event.etype != 'birthday' ";
        } else {
            $prow = q(
                "SELECT hide_friends FROM profile WHERE uid = %d AND is_default = 1 LIMIT 1",
                intval($channel_id)
            );
            if ($prow && !empty($prow[0]['hide_friends'])) {
                $sql_extra .= " and event.etype != 'birthday' ";
            }
        }

        // ── Date range ────────────────────────────────────────────────────────
        if (isset($_GET['start']) && isset($_GET['end'])) {
            $start  = datetime_convert('UTC', 'UTC', $_GET['start']);
            $finish = datetime_convert('UTC', 'UTC', $_GET['end']);
        } else {
            // Default: today → +60 days
            $start  = datetime_convert('UTC', 'UTC', 'now');
            $finish = datetime_convert('UTC', 'UTC', '+60 days');
        }

        $adjust_start  = datetime_convert('UTC', date_default_timezone_get(), $start);
        $adjust_finish = datetime_convert('UTC', date_default_timezone_get(), $finish);

        // ── Query ─────────────────────────────────────────────────────────────
        if (isset($_GET['id'])) {
            $r = q(
                "SELECT event.*, item.plink, item.item_flags, item.author_xchan,
                        item.owner_xchan, item.id as item_id
                 FROM event
                 LEFT JOIN item ON item.resource_id = event.event_hash
                 WHERE item.resource_type = 'event'
                   AND event.uid = %d
                   AND event.id = %d
                 $sql_extra
                 LIMIT 1",
                intval($channel_id),
                intval($_GET['id'])
            );
        } else {
            $r = q(
                "SELECT event.*, item.plink, item.item_flags, item.author_xchan,
                        item.owner_xchan, item.id as item_id
                 FROM event
                 LEFT JOIN item ON event.event_hash = item.resource_id
                 WHERE item.resource_type = 'event'
                   AND event.uid = %d
                   AND event.uid = item.uid
                   AND ((  event.adjust = 0
                           AND ( event.dtend >= '%s' OR event.nofinish = 1 )
                           AND event.dtstart <= '%s' )
                       OR (event.adjust = 1
                           AND ( event.dtend >= '%s' OR event.nofinish = 1 )
                           AND event.dtstart <= '%s' ))
                 $sql_extra",
                intval($channel_id),
                dbesc($start),
                dbesc($finish),
                dbesc($adjust_start),
                dbesc($adjust_finish)
            );
        }

        if ($r) {
            xchan_query($r);
            $r = fetch_post_tags($r, true);
            $r = sort_by_date($r);
        }

        // ── Shape events ──────────────────────────────────────────────────────
        $events = [];

        foreach (($r ?: []) as $rr) {
            $tz = get_iconfig($rr, 'event', 'timezone') ?: 'UTC';

            $startIso = $rr['adjust']
                ? datetime_convert('UTC', date_default_timezone_get(), $rr['dtstart'], 'c')
                : datetime_convert('UTC', 'UTC', $rr['dtstart'], 'c');

            $endIso = null;
            if (!$rr['nofinish']) {
                $endIso = $rr['adjust']
                    ? datetime_convert('UTC', date_default_timezone_get(), $rr['dtend'], 'c')
                    : datetime_convert('UTC', 'UTC', $rr['dtend'], 'c');
            }

            // HTML detail only when ?id= is present
            $html = '';
            if (isset($_GET['id'])) {
                $rr['timezone'] = $tz;
                $html = format_event_html($rr);
            }

            $events[] = [
                'id'          => intval($rr['id']),
                'uri'         => $rr['event_hash'],
                'title'       => html_entity_decode($rr['summary'],     ENT_COMPAT, 'UTF-8'),
                'description' => html_entity_decode($rr['description'], ENT_COMPAT, 'UTF-8'),
                'location'    => html_entity_decode($rr['location'],    ENT_COMPAT, 'UTF-8'),
                'start'       => $startIso,
                'end'         => $endIso,
                'allDay'      => !$rr['adjust'],
                'nofinish'    => (bool) $rr['nofinish'],
                'timezone'    => $tz,
                'rw'          => true,
                'plink'       => $rr['plink'] ?? '',
                'html'        => $html,
                'author'      => [
                    'name'   => $rr['xchan_name']    ?? '',
                    'avatar' => $rr['xchan_photo_s'] ?? '',
                    'url'    => $rr['xchan_url']     ?? '',
                ],
            ];
        }

        Response::send($events);
    }
}
