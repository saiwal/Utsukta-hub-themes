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

        // iCal export
        if (isset($_GET['export']) && $_GET['export'] === 'ical') {
            $this->exportIcal($channel_id, $nick, $sql_extra);
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
            $this->importIcal($uid, $channel);
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

        if (!$title) {
            Response::error(400, 'Title is required');
        }
        if (!$startIso) {
            Response::error(400, 'Start time is required');
        }

        $adjust  = $allDay ? 0 : 1;
        $dtstart = datetime_convert('UTC', 'UTC', $startIso);
        $dtend   = ($nofinish || !$endIso) ? '' : datetime_convert('UTC', 'UTC', $endIso);

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
            'timezone'    => 'UTC',
            'allow_cid'   => '',
            'allow_gid'   => '',
            'deny_cid'    => '',
            'deny_gid'    => '',
        ];

        $event = event_store_event($datarray);

        if (!$event) {
            Response::error(500, 'Failed to create event');
        }

        $post = event_store_item($datarray, $event);

        if (!empty($post['item_id'])) {
            \Zotlabs\Daemon\Master::Summon(['Notifier', 'event', $post['item_id']]);
        }

        Response::send([
            'id'  => intval($event['id']),
            'uri' => $event['event_hash'] ?? '',
        ]);
    }

    // ── iCal export ───────────────────────────────────────────────────────────

    private function exportIcal(int $channel_id, string $nick, string $sql_extra): void
    {
        $r = q(
            "SELECT event.*
             FROM event
             LEFT JOIN item ON event.event_hash = item.resource_id
             WHERE item.resource_type = 'event'
               AND event.uid = %d
               AND event.uid = item.uid
             $sql_extra
             ORDER BY event.dtstart ASC",
            intval($channel_id)
        );

        $lines = [
            "BEGIN:VCALENDAR",
            "VERSION:2.0",
            "PRODID:-//Hubzilla SPA//Calendar//EN",
            "CALSCALE:GREGORIAN",
            "METHOD:PUBLISH",
            "X-WR-CALNAME:" . $this->icalEscape($nick),
        ];

        foreach (($r ?: []) as $rr) {
            $lines[] = "BEGIN:VEVENT";
            $lines[] = "UID:" . $rr['event_hash'];

            if ($rr['adjust'] == 0) {
                $d = substr($rr['dtstart'], 0, 10);
                $lines[] = "DTSTART;VALUE=DATE:" . str_replace('-', '', $d);
                if (!$rr['nofinish'] && $rr['dtend']) {
                    $de = substr($rr['dtend'], 0, 10);
                    $lines[] = "DTEND;VALUE=DATE:" . str_replace('-', '', $de);
                }
            } else {
                $lines[] = "DTSTART:" . datetime_convert('UTC', 'UTC', $rr['dtstart'], 'Ymd\THis\Z');
                if (!$rr['nofinish'] && $rr['dtend']) {
                    $lines[] = "DTEND:" . datetime_convert('UTC', 'UTC', $rr['dtend'], 'Ymd\THis\Z');
                }
            }

            $summary = html_entity_decode($rr['summary'] ?? '', ENT_COMPAT, 'UTF-8');
            $lines[] = "SUMMARY:" . $this->icalEscape($summary);

            if (!empty($rr['description'])) {
                $desc = html_entity_decode($rr['description'], ENT_COMPAT, 'UTF-8');
                $lines[] = "DESCRIPTION:" . $this->icalEscape($desc);
            }
            if (!empty($rr['location'])) {
                $loc = html_entity_decode($rr['location'], ENT_COMPAT, 'UTF-8');
                $lines[] = "LOCATION:" . $this->icalEscape($loc);
            }

            $lines[] = "END:VEVENT";
        }

        $lines[] = "END:VCALENDAR";

        $output = implode("\r\n", $lines) . "\r\n";

        header('Content-Type: text/calendar; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $nick . '-calendar.ics"');
        header('Content-Length: ' . strlen($output));
        echo $output;
        exit;
    }

    private function icalEscape(string $str): string
    {
        return str_replace(
            ['\\', ';',  ',',  "\r\n", "\n"],
            ['\\\\', '\\;', '\\,', '\\n',  '\\n'],
            $str
        );
    }

    // ── CalDAV event range fetch ──────────────────────────────────────────────

    /**
     * Fetch CalDAV events for the owner's enabled calendars within the given
     * UTC date range. Returns an array of event shapes compatible with the
     * channel-event format (but with calendarColor / calendarName extras).
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

        $startTs = strtotime($start);
        $endTs   = strtotime($finish);

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
            $displayname = $cal['{DAV:}displayname'] ?: 'Calendar';
            $editable    = ($cal['share-access'] !== 2);

            // Use SabreDAV's own API — avoids direct table/column name assumptions
            try {
                $allObjects = $caldavBackend->getCalendarObjects($cal['id']);
            } catch (\Exception $e) {
                continue;
            }

            foreach (($allObjects ?: []) as $obj) {
                $calData = $obj['calendardata'] ?? null;

                // getCalendarObjects may omit the blob; fetch individually if so
                if (empty($calData) && !empty($obj['uri'])) {
                    try {
                        $single  = $caldavBackend->getCalendarObject($cal['id'], $obj['uri']);
                        $calData = $single['calendardata'] ?? null;
                    } catch (\Exception $e) {
                        continue;
                    }
                }

                if (empty($calData)) {
                    continue;
                }

                $vevents = $this->parseIcal((string)$calData);
                foreach ($vevents as $ev) {
                    if (empty($ev['dtstart'])) {
                        continue;
                    }

                    try {
                        $evStartDt = new \DateTime($ev['dtstart'], new \DateTimeZone('UTC'));
                        $evEndDt   = !empty($ev['dtend'])
                            ? new \DateTime($ev['dtend'], new \DateTimeZone('UTC'))
                            : $evStartDt;
                    } catch (\Exception $e) {
                        continue;
                    }

                    // Skip events entirely outside the requested range
                    if ($evStartDt->getTimestamp() >= $endTs || $evEndDt->getTimestamp() < $startTs) {
                        continue;
                    }

                    $startIso = $evStartDt->format('c');
                    $endIso   = !empty($ev['dtend'])
                        ? (new \DateTime($ev['dtend'], new \DateTimeZone('UTC')))->format('c')
                        : null;

                    $calEvents[] = [
                        'id'            => intval($obj['id'] ?? 0),
                        'uri'           => $obj['uri'] ?? '',
                        'title'         => $ev['summary']     ?: '',
                        'description'   => $ev['description'] ?? '',
                        'location'      => $ev['location']    ?? '',
                        'start'         => $startIso,
                        'end'           => $endIso,
                        'allDay'        => $ev['allDay'],
                        'nofinish'      => empty($ev['dtend']),
                        'timezone'      => 'UTC',
                        'rw'            => $editable,
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

    private function importIcal(int $uid, array $channel): void
    {
        $body = Auth::$parsedBody;
        $icalContent = $body['ical'] ?? '';

        if (!$icalContent) {
            Response::error(400, 'iCal content required');
        }

        $vevents = $this->parseIcal((string)$icalContent);

        $imported = 0;
        $failed   = 0;

        foreach ($vevents as $ev) {
            if (empty($ev['dtstart'])) {
                $failed++;
                continue;
            }

            $adjust   = empty($ev['allDay']) ? 1 : 0;
            $nofinish = empty($ev['dtend']) ? 1 : 0;
            $dtstart  = datetime_convert('UTC', 'UTC', $ev['dtstart']);
            $dtend    = $nofinish ? '' : datetime_convert('UTC', 'UTC', $ev['dtend']);

            $datarray = [
                'uid'         => intval($uid),
                'account'     => get_account_id(),
                'event_xchan' => $channel['channel_hash'],
                'etype'       => 'event',
                'summary'     => $ev['summary'] ?: 'Imported Event',
                'description' => $ev['description'] ?? '',
                'location'    => $ev['location'] ?? '',
                'dtstart'     => $dtstart,
                'dtend'       => $dtend,
                'nofinish'    => $nofinish,
                'adjust'      => $adjust,
                'timezone'    => 'UTC',
                'allow_cid'   => '',
                'allow_gid'   => '',
                'deny_cid'    => '',
                'deny_gid'    => '',
            ];

            $event = event_store_event($datarray);
            if ($event) {
                event_store_item($datarray, $event);
                $imported++;
            } else {
                $failed++;
            }
        }

        Response::send(['imported' => $imported, 'failed' => $failed]);
    }

    private function parseIcal(string $content): array
    {
        $content = preg_replace("/\r?\n[ \t]/", '', $content);
        $lines   = preg_split("/\r?\n/", $content);

        $events   = [];
        $inEvent  = false;
        $props    = [];

        foreach ($lines as $line) {
            $line = rtrim($line);

            if ($line === 'BEGIN:VEVENT') {
                $inEvent = true;
                $props   = [];
                continue;
            }

            if ($line === 'END:VEVENT') {
                if ($inEvent) {
                    $parsed = $this->extractVEvent($props);
                    if ($parsed) {
                        $events[] = $parsed;
                    }
                }
                $inEvent = false;
                continue;
            }

            if (!$inEvent) {
                continue;
            }

            $colon = strpos($line, ':');
            if ($colon === false) {
                continue;
            }

            $keypart = substr($line, 0, $colon);
            $value   = substr($line, $colon + 1);

            $nameParts = explode(';', $keypart, 2);
            $name      = strtoupper(trim($nameParts[0]));
            $paramStr  = $nameParts[1] ?? '';

            $params = [];
            if ($paramStr) {
                foreach (explode(';', $paramStr) as $p) {
                    if (str_contains($p, '=')) {
                        [$pk, $pv] = explode('=', $p, 2);
                        $params[strtoupper(trim($pk))] = trim($pv);
                    }
                }
            }

            $props[$name] = ['value' => $value, 'params' => $params];
        }

        return $events;
    }

    private function extractVEvent(array $props): ?array
    {
        $dtstart_entry = $props['DTSTART'] ?? null;
        if (!$dtstart_entry) {
            return null;
        }

        $allDay  = isset($dtstart_entry['params']['VALUE'])
                   && $dtstart_entry['params']['VALUE'] === 'DATE';
        $dtstart = $this->parseIcalDate($dtstart_entry['value'], $allDay);

        $dtend = '';
        if (isset($props['DTEND'])) {
            $dtend = $this->parseIcalDate($props['DTEND']['value'], $allDay);
        }

        return [
            'summary'     => $this->icalUnescape($props['SUMMARY']['value']     ?? ''),
            'description' => $this->icalUnescape($props['DESCRIPTION']['value'] ?? ''),
            'location'    => $this->icalUnescape($props['LOCATION']['value']    ?? ''),
            'dtstart'     => $dtstart,
            'dtend'       => $dtend,
            'allDay'      => $allDay,
        ];
    }

    private function parseIcalDate(string $raw, bool $allDay = false): string
    {
        if (empty($raw)) {
            return '';
        }

        $raw = rtrim($raw, 'Z');

        if ($allDay || strlen($raw) === 8) {
            $raw = substr($raw, 0, 8);
            return substr($raw, 0, 4) . '-' . substr($raw, 4, 2) . '-' . substr($raw, 6, 2) . ' 00:00:00';
        }

        if (strlen($raw) >= 15 && $raw[8] === 'T') {
            return substr($raw, 0, 4) . '-' . substr($raw, 4, 2) . '-' . substr($raw, 6, 2)
                 . ' ' . substr($raw, 9, 2) . ':' . substr($raw, 11, 2) . ':' . substr($raw, 13, 2);
        }

        $ts = strtotime($raw);
        return $ts !== false ? date('Y-m-d H:i:s', $ts) : '';
    }

    private function icalUnescape(string $str): string
    {
        return str_replace(
            ['\\n', '\\N', '\\;', '\\,', '\\\\'],
            ["\n",  "\n",  ';',   ',',   '\\'],
            $str
        );
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

        if (!$title || !$startIso) {
            Response::error(400, 'Title and start are required');
        }

        $uid_str = strtoupper(random_string(32));
        $now     = gmdate('Ymd\THis\Z');

        $lines = [
            "BEGIN:VCALENDAR",
            "VERSION:2.0",
            "PRODID:-//Hubzilla SPA//Calendar//EN",
            "BEGIN:VEVENT",
            "UID:" . $uid_str,
            "DTSTAMP:" . $now,
            "CREATED:" . $now,
        ];

        if ($allDay) {
            $sd = str_replace('-', '', substr($startIso, 0, 10));
            $lines[] = "DTSTART;VALUE=DATE:" . $sd;
            if (!$nofinish && $endIso) {
                $ed = str_replace('-', '', substr($endIso, 0, 10));
                $lines[] = "DTEND;VALUE=DATE:" . $ed;
            }
        } else {
            $lines[] = "DTSTART:" . gmdate('Ymd\THis\Z', strtotime($startIso));
            if (!$nofinish && $endIso) {
                $lines[] = "DTEND:" . gmdate('Ymd\THis\Z', strtotime($endIso));
            }
        }

        $lines[] = "SUMMARY:" . $this->icalEscape($title);
        if ($description) $lines[] = "DESCRIPTION:" . $this->icalEscape($description);
        if ($location)    $lines[] = "LOCATION:"    . $this->icalEscape($location);

        $lines[] = "END:VEVENT";
        $lines[] = "END:VCALENDAR";

        $icalContent = implode("\r\n", $lines) . "\r\n";
        $objectUri   = $uid_str . '.ics';

        $caldavBackend->createCalendarObject([$calId, $instanceId], $objectUri, $icalContent);

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
                $displayname = $cal['{DAV:}displayname'] ?: 'Calendar';
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
                                'name'   => $shareeData['channel_name'],
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
            'displayname' => $channel['channel_name'],
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
            'displayname' => $name,
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

        Response::send(['displayname' => $name, 'color' => $color]);
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
