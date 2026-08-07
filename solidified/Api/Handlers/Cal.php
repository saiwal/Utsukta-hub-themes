<?php
namespace Theme\Solidified\Api\Handlers;

use Theme\Solidified\Api\Auth;
use Theme\Solidified\Api\Response;
use App;

require_once('include/datetime.php');
require_once('include/event.php');
require_once('include/items.php');

/**
 * GET /api/cal/calendars                      — list CalDAV calendars + channel calendar
 * GET /api/cal/:nick                          — channel event feed (ISO date range or upcoming 60 days)
 *   ?start=2026-05-01&end=2026-06-01
 *   ?id=<event_id>                            — single event detail
 *   ?export=ical                              — download as iCal (.ics)
 *
 * POST /api/cal                               — create event (JSON body)
 * POST /api/cal/import                        — import iCal (JSON body: { ical: "..." })
 * POST /api/cal/calendar/create               — create CalDAV calendar
 * POST /api/cal/calendar/:id/toggle           — toggle pconfig visibility
 * POST /api/cal/calendar/:id/edit             — rename / recolor
 * POST /api/cal/calendar/:id/delete           — delete CalDAV calendar
 * POST /api/cal/calendar/:id/share            — invite sharee
 * POST /api/cal/calendar/:id/unshare          — remove sharee
 */
class Cal
{
    public function get(): void
    {
        $sub = \App::$argv[2] ?? '';

        // CalDAV calendar list — requires local auth
        if ($sub === 'calendars') {
            $uid = Auth::requireLocalGet();
            $channel = \App::get_channel();
            if (!$channel) {
                Response::error(403, 'Not logged in');
            }
            $this->listCalendars($uid, $channel);
            return;
        }


        // Channel event feed — public (respects site observer settings)
        if (observer_prohibited()) {
            Response::error(403, 'Observers prohibited');
        }

        $nick = $sub;
        if (!$nick) {
            Response::error(400, 'Channel nick required');
        }

        $channelx = channelx_by_nick($nick);
        if (!$channelx) {
            Response::error(404, 'Channel not found');
        }

        $channel_id = intval($channelx['channel_id']);

        if (!perm_is_allowed($channel_id, get_observer_hash(), 'view_stream')) {
            Response::error(403, 'Permission denied');
        }

        // Determine if the authenticated local user is the channel owner.
        // Only owners see their own CalDAV calendars merged in.
        $viewer_channel = \App::get_channel();
        $is_owner       = ($viewer_channel && intval($viewer_channel['channel_id']) === $channel_id);
        $local_uid      = $is_owner ? intval($viewer_channel['channel_id']) : 0;

        $sql_extra = permissions_sql($channel_id, get_observer_hash(), 'event');

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

        // iCal export — use the validated channel address (not the raw URL
        // segment) since it lands in a Content-Disposition header downstream.
        if (isset($_GET['export']) && $_GET['export'] === 'ical') {
            $this->exportIcal($channel_id, $channelx['channel_address'], $sql_extra);
            return;
        }

        // Date range
        if (isset($_GET['start']) && isset($_GET['end'])) {
            $start  = datetime_convert('UTC', 'UTC', $_GET['start']);
            $finish = datetime_convert('UTC', 'UTC', $_GET['end']);
        } else {
            $start  = datetime_convert('UTC', 'UTC', 'now');
            $finish = datetime_convert('UTC', 'UTC', '+60 days');
        }

        $adjust_start  = datetime_convert('UTC', date_default_timezone_get(), $start);
        $adjust_finish = datetime_convert('UTC', date_default_timezone_get(), $finish);

        // When the owner has explicitly disabled the channel calendar in the widget,
        // skip the native event query. Default (key not set) = enabled.
        $include_channel_cal = true;
        if ($is_owner) {
            $pval = get_pconfig($local_uid, 'cdav_calendar', 'channel_calendar');
            $include_channel_cal = !($pval !== false && intval($pval) === 0);
        }

        $r = [];
        if ($include_channel_cal) {
            if (isset($_GET['id'])) {
                $r = q(
                    "SELECT event.*, item.plink, item.item_flags, item.author_xchan,
                            item.owner_xchan, item.id as item_id
                     FROM event
                     LEFT JOIN item ON item.resource_id = event.event_hash
                                   AND item.resource_type = 'event'
                     WHERE event.uid = %d
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
                                   AND item.resource_type = 'event'
                                   AND event.uid = item.uid
                     WHERE event.uid = %d
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
        }

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

            $html = '';
            if (isset($_GET['id'])) {
                $rr['timezone'] = $tz;
                $html = format_event_html($rr);
            }

            $event = [
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
                    'name'   => Response::decodeEntities($rr['xchan_name'] ?? ''),
                    'avatar' => $rr['xchan_photo_s'] ?? '',
                    'url'    => $rr['xchan_url']     ?? '',
                ],
            ];

            // Audience detail is only meaningful (and only safe to expose) to
            // the channel owner — everyone else already only sees events
            // permissions_sql() let through, they don't need the raw list.
            if ($is_owner) {
                $ownHash = '<' . $channelx['channel_hash'] . '>';
                $event['contactAllow'] = expand_acl($rr['allow_cid']);
                $event['groupAllow']   = expand_acl($rr['allow_gid']);
                $event['contactDeny']  = expand_acl($rr['deny_cid']);
                $event['groupDeny']    = expand_acl($rr['deny_gid']);
                $event['scope'] = (!$rr['allow_cid'] && !$rr['allow_gid'] && !$rr['deny_cid'] && !$rr['deny_gid'])
                    ? 'public'
                    : (($rr['allow_cid'] === $ownHash && !$rr['allow_gid'] && !$rr['deny_cid'] && !$rr['deny_gid'])
                        ? 'private'
                        : 'custom');
            }

            $events[] = $event;
        }

        // Merge CalDAV events for the channel owner (range queries only — skip for ?id=)
        if ($is_owner && !isset($_GET['id'])) {
            $cdav = $this->fetchCalDavEventsForRange(
                intval($local_uid),
                $channelx,
                $start,
                $finish
            );
            $events = array_merge($events, $cdav);
        }

        Response::send($events);
    }

    public function post(): void
    {
        $uid = Auth::requireLocalJson();

        $channel = \App::get_channel();
        if (!$channel) {
            Response::error(403, 'Not logged in');
        }

        $sub    = \App::$argv[2] ?? '';
        $idStr  = \App::$argv[3] ?? '';
        $action = \App::$argv[4] ?? '';

        if ($sub === 'import') {
            $this->importIcal($uid);
            return;
        }

        // CalDAV calendar management
        if ($sub === 'calendar') {
            if ($idStr === 'create') {
                $this->createCalendar($uid, $channel);
                return;
            }

            $calId = intval($idStr);
            if (!$calId && $idStr !== '0') {
                Response::error(400, 'Invalid calendar id');
            }

            switch ($action) {
                case 'toggle':  $this->toggleCalendar($uid, $calId);            return;
                case 'edit':    $this->editCalendar($uid, $channel, $calId);    return;
                case 'delete':  $this->deleteCalendar($uid, $channel, $calId);  return;
                case 'share':   $this->shareCalendar($uid, $channel, $calId);   return;
                case 'unshare': $this->unshareCalendar($uid, $channel, $calId); return;
            }

            Response::error(400, 'Unknown calendar action');
        }

        // Edit event
        if ($idStr === 'edit') {
            $this->editEvent($uid, $channel, intval($sub));
            return;
        }

        // Delete event
        if ($idStr === 'delete') {
            $this->deleteEvent($uid, $channel, intval($sub));
            return;
        }

        // Create event
        $body = Auth::$parsedBody;

        // Route to CalDAV creation when a specific calendar id is supplied
        $calendarId = $body['calendarId'] ?? null;
        if ($calendarId !== null && $calendarId !== 'channel_calendar') {
            $this->createCalDavEvent($uid, $channel, intval($calendarId), intval($body['calendarInstanceId'] ?? 0));
            return;
        }

        $title       = trim($body['title'] ?? '');
        $description = trim($body['description'] ?? '');
        $location    = trim($body['location'] ?? '');
        $startIso    = $body['start'] ?? '';
        $endIso      = $body['end'] ?? null;
        $allDay      = (bool)($body['allDay'] ?? false);
        $nofinish    = (bool)($body['nofinish'] ?? false);
        $timezone    = trim((string)($body['timezone'] ?? '')) ?: 'UTC';

        if (!$title) {
            Response::error(400, 'Title is required');
        }
        if (!$startIso) {
            Response::error(400, 'Start time is required');
        }

        $adjust  = $allDay ? 0 : 1;
        $dtstart = datetime_convert('UTC', 'UTC', $startIso);
        $dtend   = ($nofinish || !$endIso) ? '' : datetime_convert('UTC', 'UTC', $endIso);
        $acl     = $this->resolveEventAcl($uid, (string)($body['scope'] ?? 'public'), $body);

        $datarray = [
            'uid'         => intval($uid),
            'account'     => get_account_id(),
            'event_xchan' => $channel['channel_hash'],
            'etype'       => 'event',
            'summary'     => $title,
            'description' => $description,
            'location'    => $location,
            'dtstart'     => $dtstart,
            'dtend'       => $dtend,
            'nofinish'    => ($nofinish || !$endIso) ? 1 : 0,
            'adjust'      => $adjust,
            'timezone'    => $timezone,
            'allow_cid'   => $acl['allow_cid'],
            'allow_gid'   => $acl['allow_gid'],
            'deny_cid'    => $acl['deny_cid'],
            'deny_gid'    => $acl['deny_gid'],
        ];

        $event = event_store_event($datarray);

        if (!$event) {
            Response::error(500, 'Failed to create event');
        }

        $post = event_store_item($datarray, $event);

        if (!empty($post['item_id'])) {
            \Zotlabs\Daemon\Master::Summon(['Notifier', 'event', $post['item_id']]);
        }
        if (!empty($post['approval_id'])) {
            \Zotlabs\Daemon\Master::Summon(['Notifier', 'event', $post['approval_id']]);
        }

        Response::send([
            'id'  => intval($event['id']),
            'uri' => $event['event_hash'] ?? '',
        ]);
    }

    // ── Edit event ───────────────────────────────────────────────────────────

    private function editEvent(int $uid, array $channel, int $eventId): void
    {
        $body = Auth::$parsedBody;

        // CalDAV event path: calendarId + uri present in body
        $calendarId = $body['calendarId'] ?? null;
        if ($calendarId !== null && $calendarId !== 'channel_calendar') {
            $this->editCalDavEvent($uid, $channel, intval($calendarId), (string)($body['uri'] ?? ''));
            return;
        }

        // Channel calendar event
        $r = q("SELECT * FROM event WHERE id = %d AND uid = %d LIMIT 1",
            $eventId, intval($uid));
        if (!$r) {
            Response::error(404, 'Event not found');
        }
        $existing = $r[0];

        $title       = trim($body['title'] ?? '');
        $description = trim($body['description'] ?? '');
        $location    = trim($body['location'] ?? '');
        $startIso    = $body['start'] ?? '';
        $endIso      = $body['end'] ?? null;
        $allDay      = (bool)($body['allDay'] ?? false);
        $nofinish    = (bool)($body['nofinish'] ?? false);
        $timezone    = trim((string)($body['timezone'] ?? '')) ?: 'UTC';

        if (!$title) {
            Response::error(400, 'Title is required');
        }
        if (!$startIso) {
            Response::error(400, 'Start time is required');
        }

        $adjust  = $allDay ? 0 : 1;
        $dtstart = datetime_convert('UTC', 'UTC', $startIso);
        $dtend   = ($nofinish || !$endIso) ? '' : datetime_convert('UTC', 'UTC', $endIso);

        // Only re-resolve the audience when the client actually sent a scope
        // (the edit modal always does) — keeps non-form callers that omit it
        // from silently wiping an existing custom audience.
        $acl = isset($body['scope'])
            ? $this->resolveEventAcl($uid, (string)$body['scope'], $body)
            : [
                'allow_cid' => $existing['allow_cid'],
                'allow_gid' => $existing['allow_gid'],
                'deny_cid'  => $existing['deny_cid'],
                'deny_gid'  => $existing['deny_gid'],
            ];

        $datarray = [
            'id'               => $eventId,
            'uid'              => intval($uid),
            'event_xchan'      => $channel['channel_hash'],
            'etype'            => $existing['etype'],
            'event_hash'       => $existing['event_hash'],
            'summary'          => $title,
            'description'      => $description,
            'location'         => $location,
            'dtstart'          => $dtstart,
            'dtend'            => $dtend,
            'nofinish'         => ($nofinish || !$endIso) ? 1 : 0,
            'adjust'           => $adjust,
            'timezone'         => $timezone,
            'edited'           => datetime_convert(),
            'allow_cid'        => $acl['allow_cid'],
            'allow_gid'        => $acl['allow_gid'],
            'deny_cid'         => $acl['deny_cid'],
            'deny_gid'         => $acl['deny_gid'],
            'event_status'     => $existing['event_status'],
            'event_percent'    => intval($existing['event_percent']),
            'event_repeat'     => $existing['event_repeat'],
            'event_sequence'   => intval($existing['event_sequence']),
            'event_priority'   => intval($existing['event_priority']),
            'event_vdata'      => $existing['event_vdata'],
        ];

        $event = event_store_event($datarray);
        if (!$event) {
            Response::error(500, 'Failed to update event');
        }

        $post = event_store_item($datarray, $event);

        if (!empty($post['item_id'])) {
            \Zotlabs\Daemon\Master::Summon(['Notifier', 'edit_post', $post['item_id']]);
        }

        Response::send(['success' => true]);
    }

    // Same scope-string convention (public/private/connections/custom) and
    // per-handler-private-resolver pattern as Menus::resolveAcl() /
    // Webpages::resolveWebpageAcl() / Blocks::resolveBlockAcl() — but returns
    // the ready-to-store "<hash1><hash2>" bracket strings directly, since
    // event/item rows (unlike menu items) store ACL that way, not as arrays.
    private function resolveEventAcl(int $uid, string $scope, array $body): array
    {
        $pack = fn(array $hashes) => implode('', array_map(fn($h) => '<' . $h . '>', $hashes));

        if ($scope === 'custom') {
            $contactAllow = is_array($body['contact_allow'] ?? null) ? $body['contact_allow'] : [];
            $groupAllow   = is_array($body['group_allow']   ?? null) ? $body['group_allow']   : [];
            $contactDeny  = is_array($body['contact_deny']  ?? null) ? $body['contact_deny']  : [];
            $groupDeny    = is_array($body['group_deny']    ?? null) ? $body['group_deny']    : [];
            return [
                'allow_cid' => $pack($contactAllow),
                'allow_gid' => $pack($groupAllow),
                'deny_cid'  => $pack($contactDeny),
                'deny_gid'  => $pack($groupDeny),
            ];
        }

        if ($scope === 'private') {
            $ch = q("SELECT channel_hash FROM channel WHERE channel_id = %d LIMIT 1", intval($uid));
            return [
                'allow_cid' => $ch ? '<' . $ch[0]['channel_hash'] . '>' : '',
                'allow_gid' => '',
                'deny_cid'  => '',
                'deny_gid'  => '',
            ];
        }

        if ($scope === 'connections') {
            $ch = q(
                "SELECT channel_allow_cid, channel_allow_gid, channel_deny_cid, channel_deny_gid
                 FROM channel WHERE channel_id = %d LIMIT 1",
                intval($uid)
            );
            if ($ch) {
                return [
                    'allow_cid' => $ch[0]['channel_allow_cid'],
                    'allow_gid' => $ch[0]['channel_allow_gid'],
                    'deny_cid'  => $ch[0]['channel_deny_cid'],
                    'deny_gid'  => $ch[0]['channel_deny_gid'],
                ];
            }
        }

        // 'public' (default/fallback): fully open, no ACL.
        return ['allow_cid' => '', 'allow_gid' => '', 'deny_cid' => '', 'deny_gid' => ''];
    }

    private function editCalDavEvent(int $uid, array $channel, int $calId, string $uri): void
    {
        require_once 'vendor/autoload.php';

        $principalUri = 'principals/' . $channel['channel_address'];
        if (!cdav_principal($principalUri)) {
            Response::error(403, 'CalDAV not available');
        }

        $pdo           = \DBA::$dba->db;
        $caldavBackend = new \Sabre\CalDAV\Backend\PDO($pdo);

        $cals = $caldavBackend->getCalendarsForUser($principalUri);
        if (!cdav_perms($calId, $cals)) {
            Response::error(403, 'Permission denied');
        }

        // Resolve instanceId from calendar list
        $calInstanceId = 0;
        foreach ($cals as $cal) {
            if (intval($cal['id'][0]) === $calId) {
                $calInstanceId = intval($cal['id'][1]);
                break;
            }
        }

        $body        = Auth::$parsedBody;
        $title       = trim(escape_tags($body['title'] ?? ''));
        $description = escape_tags($body['description'] ?? '');
        $location    = escape_tags($body['location'] ?? '');
        $startIso    = $body['start'] ?? '';
        $endIso      = $body['end'] ?? null;
        $allDay      = !empty($body['allDay']);
        $nofinish    = !empty($body['nofinish']);
        $tz          = trim((string)($body['timezone'] ?? '')) ?: date_default_timezone_get();

        if (!$title || !$startIso) {
            Response::error(400, 'Title and start are required');
        }

        $object = $caldavBackend->getCalendarObject([$calId, $calInstanceId], $uri);
        if (empty($object['calendardata'])) {
            Response::error(404, 'Event not found');
        }

        $vcalendar = \Sabre\VObject\Reader::read($object['calendardata']);

        $vcalendar->VEVENT->SUMMARY = $title;

        $dtstart = new \DateTime(datetime_convert('UTC', 'UTC', $startIso));
        $vcalendar->VEVENT->DTSTART = $dtstart;
        if ($allDay) {
            $vcalendar->VEVENT->DTSTART['VALUE'] = 'DATE';
        } else {
            $vcalendar->VEVENT->DTSTART['TZID'] = $tz;
        }

        if (!$nofinish && $endIso) {
            $dtend = new \DateTime(datetime_convert('UTC', 'UTC', $endIso));
            $vcalendar->VEVENT->DTEND = $dtend;
            if ($allDay) {
                $vcalendar->VEVENT->DTEND['VALUE'] = 'DATE';
            } else {
                $vcalendar->VEVENT->DTEND['TZID'] = $tz;
            }
        } else {
            unset($vcalendar->VEVENT->DTEND);
        }

        if ($description) {
            $vcalendar->VEVENT->DESCRIPTION = $description;
        }
        if ($location) {
            $vcalendar->VEVENT->LOCATION = $location;
        }

        $caldavBackend->updateCalendarObject([$calId, $calInstanceId], $uri, $vcalendar->serialize());

        Response::send(['success' => true]);
    }

    // ── Delete event ─────────────────────────────────────────────────────────

    private function deleteEvent(int $uid, array $channel, int $eventId): void
    {
        $body = Auth::$parsedBody;

        // CalDAV event path
        $calendarId = $body['calendarId'] ?? null;
        if ($calendarId !== null && $calendarId !== 'channel_calendar') {
            $this->deleteCalDavEvent($uid, $channel, intval($calendarId), (string)($body['uri'] ?? ''));
            return;
        }

        // Channel calendar event — verify ownership
        $r = q("SELECT * FROM event WHERE id = %d AND uid = %d LIMIT 1",
            $eventId, intval($uid));
        if (!$r) {
            Response::error(404, 'Event not found');
        }
        $existing = $r[0];

        // Find and drop the associated item (handles federation)
        $item = q("SELECT * FROM item
                   WHERE resource_id = '%s' AND resource_type = 'event'
                     AND uid = %d AND item_deleted = 0 LIMIT 1",
            dbesc($existing['event_hash']),
            intval($uid)
        );

        if ($item) {
            drop_item($item[0]['id'], DROPITEM_PHASE1);
        }

        // Remove the event record
        q("DELETE FROM event WHERE id = %d AND uid = %d",
            $eventId, intval($uid));

        Response::send(['deleted' => true]);
    }

    private function deleteCalDavEvent(int $uid, array $channel, int $calId, string $uri): void
    {
        require_once 'vendor/autoload.php';

        $principalUri = 'principals/' . $channel['channel_address'];
        if (!cdav_principal($principalUri)) {
            Response::error(403, 'CalDAV not available');
        }

        $pdo           = \DBA::$dba->db;
        $caldavBackend = new \Sabre\CalDAV\Backend\PDO($pdo);

        $cals = $caldavBackend->getCalendarsForUser($principalUri);
        if (!cdav_perms($calId, $cals)) {
            Response::error(403, 'Permission denied');
        }

        $calInstanceId = 0;
        foreach ($cals as $cal) {
            if (intval($cal['id'][0]) === $calId) {
                $calInstanceId = intval($cal['id'][1]);
                break;
            }
        }

        $caldavBackend->deleteCalendarObject([$calId, $calInstanceId], $uri);

        Response::send(['deleted' => true]);
    }

    // ── iCal export ───────────────────────────────────────────────────────────

    private function exportIcal(int $channel_id, string $nick, string $sql_extra): void
    {
        $r = q(
            "SELECT event.*, item.id AS item_id
             FROM event
             LEFT JOIN item ON event.event_hash = item.resource_id
             WHERE item.resource_type = 'event'
               AND event.uid = %d
               AND event.uid = item.uid
             $sql_extra
             ORDER BY event.dtstart ASC",
            intval($channel_id)
        );

        $output = ical_wrapper($r ?: []);

        header('Content-Type: text/calendar; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $nick . '-calendar.ics"');
        header('Content-Length: ' . strlen($output));
        echo $output;
        exit;
    }

    // ── CalDAV event range fetch ──────────────────────────────────────────────

    /**
     * Fetch CalDAV events for the owner's enabled calendars within the given
     * UTC date range. Returns an array of event shapes compatible with the
     * channel-event format (but with calendarColor / calendarName extras).
     *
     * Mirrors core's Zotlabs\Module\Cdav::calendar/json path (redbasic's own
     * event source): time-range filtering via calendarQuery() (DB-side, using
     * SabreDAV's maintained firstoccurence/lastoccurence columns) and
     * Sabre\VObject expand() for recurring events. A hand-rolled ICS parser
     * without RRULE expansion would silently drop every recurring event whose
     * master DTSTART falls outside the viewed range.
     */
    private function fetchCalDavEventsForRange(int $uid, array $channel, string $start, string $finish): array
    {
        require_once 'vendor/autoload.php';

        $principalUri = 'principals/' . $channel['channel_address'];
        if (!cdav_principal($principalUri)) {
            return [];
        }

        $pdo           = \DBA::$dba->db;
        $caldavBackend = new \Sabre\CalDAV\Backend\PDO($pdo);
        $sabrecals     = $caldavBackend->getCalendarsForUser($principalUri);

        if (!$sabrecals) {
            return [];
        }

        $startDt = new \DateTime($start, new \DateTimeZone('UTC'));
        $endDt   = new \DateTime($finish, new \DateTimeZone('UTC'));

        $filters = [
            'name'         => 'VCALENDAR',
            'prop-filters' => [],
            'comp-filters' => [
                [
                    'name'           => 'VEVENT',
                    'is-not-defined' => null,
                    'time-range'     => ['start' => $startDt, 'end' => $endDt],
                    'comp-filters'   => [],
                    'prop-filters'   => [],
                ],
            ],
        ];

        $calEvents = [];

        foreach ($sabrecals as $cal) {
            $calId = intval($cal['id'][0]);  // calendars.id (pconfig key)

            // Include unless explicitly disabled (pconfig = 0).
            // false = key not set = default = enabled.
            $pval = get_pconfig($uid, 'cdav_calendar', $calId);
            if ($pval !== false && intval($pval) === 0) {
                continue;
            }

            $color       = $cal['{http://apple.com/ns/ical/}calendar-color'] ?: '#6cad39';
            $displayname = Response::decodeEntities($cal['{DAV:}displayname'] ?: 'Calendar');
            $editable    = ($cal['share-access'] !== 2);

            try {
                $uris = $caldavBackend->calendarQuery($cal['id'], $filters);
            } catch (\Exception $e) {
                continue;
            }

            if (!$uris) {
                continue;
            }

            $objects = $caldavBackend->getMultipleCalendarObjects($cal['id'], $uris);

            foreach ($objects as $obj) {
                if (empty($obj['calendardata'])) {
                    continue;
                }

                try {
                    $vcalendar = \Sabre\VObject\Reader::read($obj['calendardata']);
                } catch (\Exception $e) {
                    continue;
                }

                if (empty($vcalendar->VEVENT)) {
                    continue;
                }

                // expand() drops the master TZID, so remember it first
                $recurrentTimezone = null;
                if (isset($vcalendar->VEVENT->RRULE)) {
                    $recurrentTimezone = (string)$vcalendar->VEVENT->DTSTART['TZID'];
                    try {
                        $vcalendar = $vcalendar->expand($startDt, $endDt);
                    } catch (\Exception $e) {
                        continue;
                    }
                }

                foreach ($vcalendar->VEVENT as $vevent) {
                    $dtstart = (string)$vevent->DTSTART;
                    if (!$dtstart) {
                        continue;
                    }
                    $dtend = (string)$vevent->DTEND;

                    $recurrent   = isset($vevent->{'RECURRENCE-ID'});
                    $timezoneStr = ($recurrent && $recurrentTimezone)
                        ? $recurrentTimezone
                        : (string)$vevent->DTSTART['TZID'];

                    // Empty TZID (bare/all-day DTSTART) intentionally falls
                    // through to TimeZoneUtil's own default (PHP's default
                    // timezone) — matching core's Cdav.php, not UTC — so an
                    // all-day event's midnight isn't shifted by the server's
                    // UTC offset.
                    $timezoneObj = \Sabre\VObject\TimeZoneUtil::getTimeZone($timezoneStr, $vcalendar);
                    $timezone    = $timezoneObj->getName() ?: 'UTC';

                    $allDay = ((string)$vevent->DTSTART['VALUE'] === 'DATE');

                    $calEvents[] = [
                        'id'            => intval($obj['id'] ?? 0),
                        'uri'           => $obj['uri'] ?? '',
                        'title'         => (string)$vevent->SUMMARY,
                        'description'   => (string)$vevent->DESCRIPTION,
                        'location'      => (string)$vevent->LOCATION,
                        'start'         => datetime_convert($timezone, date_default_timezone_get(), $dtstart, 'c'),
                        'end'           => $dtend ? datetime_convert($timezone, date_default_timezone_get(), $dtend, 'c') : null,
                        'allDay'        => $allDay,
                        'nofinish'      => empty($dtend),
                        'timezone'      => $timezone,
                        // Individual occurrences of a recurring event aren't
                        // safely single-editable — keep them read-only, same
                        // as redbasic's own calendar (core Cdav.php).
                        'rw'            => $editable && !$recurrent,
                        'plink'         => '',
                        'html'          => '',
                        'calendarId'    => $calId,
                        'calendarColor' => $color,
                        'calendarName'  => $displayname,
                        'author'        => [
                            'name'   => $channel['channel_name'] ?? '',
                            'avatar' => '',
                            'url'    => '',
                        ],
                    ];
                }
            }
        }

        return $calEvents;
    }

    // ── iCal import ───────────────────────────────────────────────────────────

    private function importIcal(int $uid): void
    {
        $body = Auth::$parsedBody;
        $icalContent = $body['ical'] ?? '';

        if (!$icalContent) {
            Response::error(400, 'iCal content required');
        }

        require_once 'vendor/autoload.php';

        $imported = 0;
        $failed   = 0;

        try {
            $vcalendar = \Sabre\VObject\Reader::read((string)$icalContent);
        } catch (\Exception $e) {
            $vcalendar = null;
        }

        if ($vcalendar && $vcalendar->VEVENT) {
            foreach ($vcalendar->VEVENT as $vevent) {
                if (event_import_ical($vevent, $uid)) {
                    $imported++;
                } else {
                    $failed++;
                }
            }
        }

        Response::send(['imported' => $imported, 'failed' => $failed]);
    }

    // ── CalDAV: create event ──────────────────────────────────────────────────

    private function createCalDavEvent(int $uid, array $channel, int $calId, int $instanceId): void
    {
        require_once 'vendor/autoload.php';

        $principalUri = 'principals/' . $channel['channel_address'];
        if (!cdav_principal($principalUri)) {
            Response::error(403, 'CalDAV not available');
        }

        $pdo           = \DBA::$dba->db;
        $caldavBackend = new \Sabre\CalDAV\Backend\PDO($pdo);

        $cals = $caldavBackend->getCalendarsForUser($principalUri);
        if (!cdav_perms($calId, $cals)) {
            Response::error(403, 'Permission denied');
        }

        $body        = Auth::$parsedBody;
        $title       = trim(escape_tags($body['title'] ?? ''));
        $description = escape_tags($body['description'] ?? '');
        $location    = escape_tags($body['location'] ?? '');
        $startIso    = $body['start'] ?? '';
        $endIso      = $body['end'] ?? null;
        $allDay      = !empty($body['allDay']);
        $nofinish    = !empty($body['nofinish']);
        $tz          = trim((string)($body['timezone'] ?? '')) ?: date_default_timezone_get();

        if (!$title || !$startIso) {
            Response::error(400, 'Title and start are required');
        }

        $dtstart = new \DateTime(datetime_convert('UTC', 'UTC', $startIso));

        $vcalendar = new \Sabre\VObject\Component\VCalendar([
            'VEVENT' => [
                'SUMMARY' => $title,
                'DTSTART' => $dtstart,
            ],
        ]);

        if (!$nofinish && $endIso) {
            $dtend = new \DateTime(datetime_convert('UTC', 'UTC', $endIso));
            $vcalendar->VEVENT->add('DTEND', $dtend);
            if ($allDay) {
                $vcalendar->VEVENT->DTEND['VALUE'] = 'DATE';
            } else {
                $vcalendar->VEVENT->DTEND['TZID'] = $tz;
            }
        }
        if ($description) {
            $vcalendar->VEVENT->add('DESCRIPTION', $description);
        }
        if ($location) {
            $vcalendar->VEVENT->add('LOCATION', $location);
        }

        if ($allDay) {
            $vcalendar->VEVENT->DTSTART['VALUE'] = 'DATE';
        } else {
            $vcalendar->VEVENT->DTSTART['TZID'] = $tz;
        }

        $objectUri = strtoupper(random_string(32)) . '.ics';
        $caldavBackend->createCalendarObject([$calId, $instanceId], $objectUri, $vcalendar->serialize());

        Response::send(['uri' => $objectUri]);
    }

    // ── CalDAV: list ──────────────────────────────────────────────────────────

    private function listCalendars(int $uid, array $channel): void
    {
        require_once 'vendor/autoload.php';

        $principalUri = 'principals/' . $channel['channel_address'];
        $hasCdav      = cdav_principal($principalUri);

        $my_calendars       = [];
        $shared_calendars   = [];
        $writable_calendars = [];

        if ($hasCdav) {
            $pdo           = \DBA::$dba->db;
            $caldavBackend = new \Sabre\CalDAV\Backend\PDO($pdo);
            $sabrecals     = $caldavBackend->getCalendarsForUser($principalUri);

            foreach ($sabrecals as $cal) {
                $access      = $cal['share-access'];   // 1=own, 2=read-only, 3=read-write
                $color       = $cal['{http://apple.com/ns/ical/}calendar-color'] ?: '#6cad39';
                $displayname = Response::decodeEntities($cal['{DAV:}displayname'] ?: 'Calendar');
                $cpval       = get_pconfig($uid, 'cdav_calendar', $cal['id'][0]);
                $enabled     = !($cpval !== false && intval($cpval) === 0);
                $editable    = ($access !== 2);

                $invites = $caldavBackend->getInvites($cal['id']);
                $sharees = [];
                foreach ($invites as $invite) {
                    if (strpos($invite->href, 'mailto:') !== false) {
                        $shareeData = channelx_by_nick(substr($invite->principal, 11));
                        if ($shareeData) {
                            $sharees[] = [
                                'name'   => Response::decodeEntities($shareeData['channel_name']),
                                'hash'   => $shareeData['channel_hash'],
                                'access' => $invite->access,
                            ];
                        }
                    }
                }

                $entry = [
                    'id'          => intval($cal['id'][0]),
                    'instanceId'  => intval($cal['id'][1]),
                    'uri'         => $cal['uri'],
                    'displayname' => $displayname,
                    'color'       => $color,
                    'editable'    => $editable,
                    'enabled'     => $enabled,
                    'exportUrl'   => '/cdav/calendars/' . $channel['channel_address'] . '/' . $cal['uri'] . '/?export',
                    'sharees'     => $sharees,
                ];

                if ($access == 1) {
                    $my_calendars[] = $entry;
                } else {
                    $entry['sharer'] = $cal['{urn:ietf:params:xml:ns:caldav}calendar-description'] ?? '';
                    $entry['access'] = ($access == 2) ? 'read' : 'read-write';
                    $shared_calendars[] = $entry;
                }

                if ($editable) {
                    $writable_calendars[] = [
                        'id'          => intval($cal['id'][0]),
                        'instanceId'  => intval($cal['id'][1]),
                        'displayname' => $displayname,
                    ];
                }
            }
        }

        $chcal_pval       = get_pconfig($uid, 'cdav_calendar', 'channel_calendar');
        $chcal_enabled    = !($chcal_pval !== false && intval($chcal_pval) === 0);
        $channel_calendar = [
            'id'          => 'channel_calendar',
            'displayname' => Response::decodeEntities($channel['channel_name']),
            'color'       => '#3a87ad',
            'enabled'     => $chcal_enabled,
            'exportUrl'   => '/api/cal/' . $channel['channel_address'] . '?export=ical',
        ];

        $local_channels = [];
        $rows = q(
            "SELECT channel_name, channel_hash FROM channel
             LEFT JOIN abook ON abook_xchan = channel_hash
             WHERE channel_system = 0
               AND channel_removed = 0
               AND channel_hash != '%s'
               AND abook_channel = %d",
            dbesc($channel['channel_hash']),
            intval($channel['channel_id'])
        );
        foreach (($rows ?: []) as $row) {
            $local_channels[] = [
                'name' => $row['channel_name'],
                'hash' => $row['channel_hash'],
            ];
        }

        Response::send([
            'has_cdav'           => $hasCdav,
            'channel_calendar'   => $channel_calendar,
            'my_calendars'       => $my_calendars,
            'shared_calendars'   => $shared_calendars,
            'writable_calendars' => $writable_calendars,
            'local_channels'     => $local_channels,
        ]);
    }

    // ── CalDAV: create ────────────────────────────────────────────────────────

    private function createCalendar(int $uid, array $channel): void
    {
        require_once 'vendor/autoload.php';

        $principalUri = 'principals/' . $channel['channel_address'];
        if (!cdav_principal($principalUri)) {
            Response::error(403, 'CalDAV not available for this channel');
        }

        $body  = Auth::$parsedBody;
        $name  = trim(escape_tags($body['name'] ?? ''));
        $color = trim(escape_tags($body['color'] ?? '#6cad39'));

        if (!$name) {
            Response::error(400, 'Calendar name is required');
        }

        $pdo           = \DBA::$dba->db;
        $caldavBackend = new \Sabre\CalDAV\Backend\PDO($pdo);

        do {
            $uri = random_string(40);
            $dup = q(
                "SELECT uri FROM calendarinstances WHERE principaluri = '%s' AND uri = '%s' LIMIT 1",
                dbesc($principalUri), dbesc($uri)
            );
        } while ($dup);

        $properties = [
            '{DAV:}displayname'                                   => $name,
            '{http://apple.com/ns/ical/}calendar-color'           => $color,
            '{urn:ietf:params:xml:ns:caldav}calendar-description' => $channel['channel_name'],
        ];

        $id = $caldavBackend->createCalendar($principalUri, $uri, $properties);

        set_pconfig($uid, 'cdav_calendar', $id[0], 1);

        Response::send([
            'id'          => intval($id[0]),
            'instanceId'  => intval($id[1]),
            'uri'         => $uri,
            'displayname' => Response::decodeEntities($name),
            'color'       => $color,
        ]);
    }

    // ── CalDAV: toggle ────────────────────────────────────────────────────────

    private function toggleCalendar(int $uid, int $calId): void
    {
        $body    = Auth::$parsedBody;
        $enabled = !empty($body['enabled']);

        // calId=0 is the special channel_calendar key
        if ($calId === 0) {
            set_pconfig($uid, 'cdav_calendar', 'channel_calendar', $enabled ? 1 : 0);
        } else {
            set_pconfig($uid, 'cdav_calendar', $calId, $enabled ? 1 : 0);
        }

        Response::send(['enabled' => $enabled]);
    }

    // ── CalDAV: edit ──────────────────────────────────────────────────────────

    private function editCalendar(int $uid, array $channel, int $calId): void
    {
        require_once 'vendor/autoload.php';

        $principalUri = 'principals/' . $channel['channel_address'];
        if (!cdav_principal($principalUri)) {
            Response::error(403, 'CalDAV not available');
        }

        $body       = Auth::$parsedBody;
        $name       = trim(escape_tags($body['name'] ?? ''));
        $color      = trim(escape_tags($body['color'] ?? ''));
        $instanceId = intval($body['instanceId'] ?? 0);

        if (!$name) {
            Response::error(400, 'Calendar name required');
        }

        $pdo           = \DBA::$dba->db;
        $caldavBackend = new \Sabre\CalDAV\Backend\PDO($pdo);

        $cals = $caldavBackend->getCalendarsForUser($principalUri);
        if (!cdav_perms($calId, $cals)) {
            Response::error(403, 'Permission denied');
        }

        $mutations = [
            '{DAV:}displayname'                         => $name,
            '{http://apple.com/ns/ical/}calendar-color' => $color,
        ];
        $patch = new \Sabre\DAV\PropPatch($mutations);
        $caldavBackend->updateCalendar([$calId, $instanceId], $patch);
        $patch->commit();

        Response::send(['displayname' => Response::decodeEntities($name), 'color' => $color]);
    }

    // ── CalDAV: delete ────────────────────────────────────────────────────────

    private function deleteCalendar(int $uid, array $channel, int $calId): void
    {
        require_once 'vendor/autoload.php';

        $principalUri = 'principals/' . $channel['channel_address'];
        if (!cdav_principal($principalUri)) {
            Response::error(403, 'CalDAV not available');
        }

        $body       = Auth::$parsedBody;
        $instanceId = intval($body['instanceId'] ?? 0);

        $pdo           = \DBA::$dba->db;
        $caldavBackend = new \Sabre\CalDAV\Backend\PDO($pdo);

        $cals = $caldavBackend->getCalendarsForUser($principalUri);
        if (!cdav_perms($calId, $cals)) {
            Response::error(403, 'Permission denied');
        }

        $caldavBackend->deleteCalendar([$calId, $instanceId]);
        del_pconfig($uid, 'cdav_calendar', $calId);

        Response::send(['deleted' => true]);
    }

    // ── CalDAV: share ─────────────────────────────────────────────────────────

    private function shareCalendar(int $uid, array $channel, int $calId): void
    {
        require_once 'vendor/autoload.php';

        $principalUri = 'principals/' . $channel['channel_address'];
        if (!cdav_principal($principalUri)) {
            Response::error(403, 'CalDAV not available');
        }

        $body       = Auth::$parsedBody;
        $instanceId = intval($body['instanceId'] ?? 0);
        $shareeHash = trim($body['shareeHash'] ?? '');
        $access     = intval($body['access'] ?? 2); // 2=read, 3=read-write

        if (!$shareeHash) {
            Response::error(400, 'Sharee required');
        }

        $shareeData = channelx_by_hash($shareeHash);
        if (!$shareeData) {
            Response::error(404, 'Channel not found');
        }

        $pdo           = \DBA::$dba->db;
        $caldavBackend = new \Sabre\CalDAV\Backend\PDO($pdo);

        $cals = $caldavBackend->getCalendarsForUser($principalUri);
        if (!cdav_perms($calId, $cals)) {
            Response::error(403, 'Permission denied');
        }

        $sharee             = new \Sabre\DAV\Xml\Element\Sharee();
        $sharee->href       = 'mailto:' . $shareeData['xchan_addr'];
        $sharee->principal  = 'principals/' . $shareeData['channel_address'];
        $sharee->access     = $access;
        $sharee->properties = ['{DAV:}displayname' => $channel['channel_name']];

        $caldavBackend->updateInvites([$calId, $instanceId], [$sharee]);

        Response::send([
            'name'   => $shareeData['channel_name'],
            'hash'   => $shareeData['channel_hash'],
            'access' => $access,
        ]);
    }

    // ── CalDAV: unshare ───────────────────────────────────────────────────────

    private function unshareCalendar(int $uid, array $channel, int $calId): void
    {
        require_once 'vendor/autoload.php';

        $principalUri = 'principals/' . $channel['channel_address'];
        if (!cdav_principal($principalUri)) {
            Response::error(403, 'CalDAV not available');
        }

        $body       = Auth::$parsedBody;
        $instanceId = intval($body['instanceId'] ?? 0);
        $shareeHash = trim($body['shareeHash'] ?? '');

        $shareeData = channelx_by_hash($shareeHash);
        if (!$shareeData) {
            Response::error(404, 'Channel not found');
        }

        $pdo           = \DBA::$dba->db;
        $caldavBackend = new \Sabre\CalDAV\Backend\PDO($pdo);

        $cals = $caldavBackend->getCalendarsForUser($principalUri);
        if (!cdav_perms($calId, $cals)) {
            Response::error(403, 'Permission denied');
        }

        $sharee            = new \Sabre\DAV\Xml\Element\Sharee();
        $sharee->href      = 'mailto:' . $shareeData['xchan_addr'];
        $sharee->principal = 'principals/' . $shareeData['channel_address'];
        $sharee->access    = 4; // 4=remove

        $caldavBackend->updateInvites([$calId, $instanceId], [$sharee]);

        Response::send(['removed' => true]);
    }
}
