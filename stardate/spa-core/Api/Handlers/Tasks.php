<?php
namespace Utsukta\SpaCore\Api\Handlers;

use Utsukta\SpaCore\Api\Auth;
use Utsukta\SpaCore\Api\Response;
use DBA;

require_once 'include/event.php';

/**
 * Owner-only task list over both places Hubzilla keeps to-dos:
 *
 *  - channel tasks: `event` rows with etype 'task' — what core's Tasklist
 *    widget (Zotlabs/Module/Tasks) and iCal VTODO import write. Always
 *    private (allow_cid = the owner), never federated.
 *  - CalDAV to-dos: VTODO objects in the user's SabreDAV calendars (which
 *    core creates with components VEVENT,VTODO), as any CalDAV client
 *    (Thunderbird, DAVx5/jtx Board…) sees them.
 *
 * GET  /spa/tasks[?all=1]   → open tasks from both (all=1 adds completed)
 * POST /spa/tasks           → create { summary, due?, priority?, calendarId? }
 * POST /spa/tasks/complete  → toggle done { source, id } | { source, calendarId, uri }
 * POST /spa/tasks/edit      → same ref + { summary, due, priority } (moving a
 *                              task to another list is delete + create)
 *
 * Deleting goes through the calendar's own endpoint (POST /spa/cal/:id/delete,
 * which already handles both an event row and a CalDAV object by uri).
 *
 * Completion mirrors core's Tasks::post() for channel tasks — status
 * COMPLETED / IN-PROCESS, percent 100 / 0, sequence bumped — and RFC 5545's
 * STATUS / PERCENT-COMPLETE / COMPLETED for VTODO.
 */
class Tasks
{
    public function get(): void
    {
        $uid     = Auth::requireLocalGet();
        $channel = \App::get_channel();
        $all     = !empty($_GET['all']);

        $tasks = [];
        $r = q("SELECT * FROM event WHERE etype = 'task' AND uid = %d "
            . ($all ? '' : " AND event_status != 'COMPLETED' ") . " ORDER BY created DESC",
            intval($uid)) ?: [];
        foreach ($r as $e) {
            $tasks[] = [
                'source'    => 'channel',
                'id'        => intval($e['id']),
                'summary'   => Response::decodeEntities($e['summary']),
                'done'      => $e['event_status'] === 'COMPLETED',
                'percent'   => intval($e['event_percent']),
                'priority'  => intval($e['event_priority']),
                'due'       => intval($e['nofinish']) ? null : substr($e['dtend'], 0, 10),
                'created'   => $e['created'],
                'editable'  => true,
            ];
        }

        [$backend, $cals] = $this->caldav($channel);
        foreach ($cals as $cal) {
            // A calendar hidden in the calendar widget hides its tasks too.
            $pval = get_pconfig($uid, 'cdav_calendar', $cal['id'][0]);
            if ($pval !== false && intval($pval) === 0) continue;

            try {
                $uris = $backend->calendarQuery($cal['id'], [
                    'name' => 'VCALENDAR', 'prop-filters' => [], 'is-not-defined' => false, 'time-range' => null,
                    'comp-filters' => [['name' => 'VTODO', 'is-not-defined' => false, 'time-range' => null,
                                        'comp-filters' => [], 'prop-filters' => []]],
                ]);
            } catch (\Exception $e) {
                continue;
            }
            if (!$uris) continue;

            foreach ($backend->getMultipleCalendarObjects($cal['id'], $uris) as $obj) {
                try {
                    $todo = \Sabre\VObject\Reader::read($obj['calendardata'])->VTODO;
                } catch (\Exception $e) {
                    continue;
                }
                if (!$todo) continue;
                $done = $this->vtodoDone($todo);
                if ($done && !$all) continue;
                $tasks[] = [
                    'source'     => 'caldav',
                    'calendarId' => intval($cal['id'][0]),
                    'uri'        => $obj['uri'],
                    'calendar'   => Response::decodeEntities($cal['{DAV:}displayname'] ?: 'Calendar'),
                    'color'      => $cal['{http://apple.com/ns/ical/}calendar-color'] ?: '#6cad39',
                    'summary'    => (string) ($todo->SUMMARY ?? ''),
                    'done'       => $done,
                    'percent'    => intval((string) ($todo->{'PERCENT-COMPLETE'} ?? 0)),
                    'priority'   => intval((string) ($todo->PRIORITY ?? 0)),
                    'due'        => isset($todo->DUE) ? $todo->DUE->getDateTime()->format('Y-m-d') : null,
                    'created'    => isset($todo->CREATED) ? $todo->CREATED->getDateTime()->format('Y-m-d H:i:s') : '',
                    'editable'   => intval($cal['share-access']) !== 2,
                ];
            }
        }

        Response::send($tasks);
    }

    public function post(): void
    {
        $uid     = Auth::requireLocalJson();
        $channel = \App::get_channel();
        $body    = Auth::$parsedBody;

        $action = \App::$argv[2] ?? '';
        $caldav = ($body['source'] ?? '') === 'caldav';
        $calId  = intval($body['calendarId'] ?? 0);
        $uri    = (string) ($body['uri'] ?? '');

        if ($action === 'complete') {
            $done = $caldav
                ? $this->withTodo($channel, $calId, $uri, fn($todo, $now) => $this->toggleTodo($todo, $now))
                : $this->toggleChannel($uid, intval($body['id'] ?? 0));
            Response::send(['done' => $done]);
        }

        [$summary, $due, $priority] = $this->fields($body);

        if ($action === 'edit') {
            if ($caldav) {
                $this->withTodo($channel, $calId, $uri, function ($todo) use ($summary, $due, $priority) {
                    $todo->SUMMARY = $summary;
                    unset($todo->DUE, $todo->PRIORITY);
                    if ($due) $todo->add('DUE', new \DateTime($due), ['VALUE' => 'DATE']);
                    if ($priority) $todo->add('PRIORITY', $priority);
                });
            } else {
                $ev = $this->channelTask($uid, intval($body['id'] ?? 0));
                $ev['summary']        = escape_tags($summary);
                $ev['event_priority'] = $priority;
                $ev['dtend']          = $due ? "$due 00:00:00" : DBA::$dba->get_null_date();
                $ev['nofinish']       = $due ? 0 : 1;
                $this->storeChannel($ev);
            }
            Response::send(['ok' => true]);
        }

        $calId = $body['calendarId'] ?? 'channel';

        if ($calId === 'channel' || $calId === 'channel_calendar') {
            $now = datetime_convert();
            $x = event_store_event([
                'account'        => $channel['channel_account_id'],
                'uid'            => $uid,
                'event_xchan'    => $channel['channel_hash'],
                'etype'          => 'task',
                'created'        => $now,
                'edited'         => $now,
                'dtstart'        => $now,
                'dtend'          => $due ? "$due 00:00:00" : DBA::$dba->get_null_date(),
                'nofinish'       => $due ? 0 : 1,
                'adjust'         => 1,
                'allow_cid'      => '<' . $channel['channel_hash'] . '>',
                'summary'        => escape_tags($summary),
                'event_priority' => $priority,
                'event_status'   => 'NEEDS-ACTION',
            ]);
            if (!$x) Response::error(500, 'Could not store task');
            Response::send(['id' => intval($x['id'])]);
        }

        [$backend, , $instanceId] = $this->caldav($channel, intval($calId), true);
        $vcal = new \Sabre\VObject\Component\VCalendar(['VTODO' => [
            'SUMMARY' => $summary,
            'STATUS'  => 'NEEDS-ACTION',
            'CREATED' => new \DateTime('now', new \DateTimeZone('UTC')),
        ]]);
        if ($due) {
            $vcal->VTODO->add('DUE', new \DateTime($due), ['VALUE' => 'DATE']);
        }
        if ($priority) {
            $vcal->VTODO->add('PRIORITY', $priority);
        }
        $uri = strtoupper(random_string(32)) . '.ics';
        $backend->createCalendarObject([intval($calId), $instanceId], $uri, $vcal->serialize());
        Response::send(['uri' => $uri]);
    }

    /**
     * Summary comes back as plain text: VTODO is plain text and any CalDAV
     * client shows it verbatim, so only the channel-task paths escape it
     * (core stores event.summary escaped, and the list decodes it).
     * @return array{0:string,1:?string,2:int} summary, due (Y-m-d), priority
     */
    private function fields(array $body): array
    {
        $summary = trim((string) ($body['summary'] ?? ''));
        if ($summary === '') Response::error(400, 'Summary is required');
        $due = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($body['due'] ?? '')) ? $body['due'] : null;
        // iCal PRIORITY: 0 none, 1 highest … 9 lowest.
        return [$summary, $due, max(0, min(9, intval($body['priority'] ?? 0)))];
    }

    private function channelTask(int $uid, int $id): array
    {
        $r = q("SELECT * FROM event WHERE etype = 'task' AND uid = %d AND id = %d LIMIT 1", intval($uid), intval($id));
        if (!$r) Response::error(404, 'Task not found');
        return $r[0];
    }

    // Core bumps the sequence on every change so CalDAV/iCal consumers see it.
    private function storeChannel(array $ev): void
    {
        $ev['event_sequence'] = intval($ev['event_sequence']) + 1;
        $ev['edited']         = datetime_convert();
        if (!event_store_event($ev)) Response::error(500, 'Could not update task');
    }

    private function toggleChannel(int $uid, int $id): bool
    {
        $ev   = $this->channelTask($uid, $id);
        $done = $ev['event_status'] !== 'COMPLETED';
        $ev['event_status']      = $done ? 'COMPLETED' : 'IN-PROCESS';
        $ev['event_status_date'] = $done ? datetime_convert() : DBA::$dba->get_null_date();
        $ev['event_percent']     = $done ? 100 : 0;
        $this->storeChannel($ev);
        return $done;
    }

    private function toggleTodo($todo, \DateTime $now): bool
    {
        $done = !$this->vtodoDone($todo);
        $todo->STATUS = $done ? 'COMPLETED' : 'NEEDS-ACTION';
        $todo->{'PERCENT-COMPLETE'} = $done ? 100 : 0;
        unset($todo->COMPLETED);
        if ($done) $todo->add('COMPLETED', $now);
        return $done;
    }

    /**
     * Load a CalDAV to-do, let $change edit its VTODO, stamp it (SEQUENCE,
     * LAST-MODIFIED, DTSTAMP — what clients use to notice a change) and save.
     */
    private function withTodo(array $channel, int $calId, string $uri, callable $change): mixed
    {
        [$backend, , $instanceId] = $this->caldav($channel, $calId, true);
        $obj = $backend->getCalendarObject([$calId, $instanceId], $uri);
        if (!$obj) Response::error(404, 'Task not found');

        $vcal = \Sabre\VObject\Reader::read($obj['calendardata']);
        $todo = $vcal->VTODO;
        if (!$todo) Response::error(400, 'Not a task');

        $now = new \DateTime('now', new \DateTimeZone('UTC'));
        $out = $change($todo, $now);
        $todo->SEQUENCE = intval((string) ($todo->SEQUENCE ?? 0)) + 1;
        $todo->{'LAST-MODIFIED'} = $now;
        $todo->DTSTAMP = $now;

        $backend->updateCalendarObject([$calId, $instanceId], $uri, $vcal->serialize());
        return $out;
    }

    private function vtodoDone($todo): bool
    {
        return strtoupper((string) ($todo->STATUS ?? '')) === 'COMPLETED' || isset($todo->COMPLETED);
    }

    /**
     * SabreDAV backend + the user's calendars; with $calId, also checks write
     * access to that calendar and resolves its instance id.
     * @return array{0:?\Sabre\CalDAV\Backend\PDO,1:array,2:int}
     */
    private function caldav(array $channel, ?int $calId = null, bool $write = false): array
    {
        require_once 'vendor/autoload.php';

        $principal = 'principals/' . $channel['channel_address'];
        if (!cdav_principal($principal)) {
            if ($calId !== null) Response::error(403, 'CalDAV not available');
            return [null, [], 0];
        }
        $backend = new \Sabre\CalDAV\Backend\PDO(DBA::$dba->db);
        $cals    = $backend->getCalendarsForUser($principal);
        if ($calId === null) return [$backend, $cals, 0];

        if (!cdav_perms($calId, $cals, $write)) Response::error(403, 'Permission denied');
        foreach ($cals as $cal) {
            if (intval($cal['id'][0]) === $calId) return [$backend, $cals, intval($cal['id'][1])];
        }
        Response::error(404, 'Calendar not found');
    }
}
