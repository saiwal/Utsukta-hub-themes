<?php
/**
 * End-to-end check for the event-category term round-trip in the SPA's Cal.php.
 *
 * One step per process invocation — Response::send() calls killme(), and forking with
 * a live PDO connection corrupts the parent's connection, so the driver script runs
 * each step separately. State lives in the DB between steps.
 *
 * Steps:
 *   seed         reset the item's terms + timezone iconfig to a known set
 *   edit:cats    edit sending categories='Alpha, Beta'
 *   edit:omit    edit with NO categories key (title change only)
 *   edit:empty   edit sending categories=''
 *   edit:gamma   edit sending categories='Gamma'
 *   assert:N     verify expected state
 *
 * Usage — copy to the ddev project root, then from hz-ddev/:
 *
 *   for s in seed edit:cats assert:replaced edit:omit assert:kept \
 *            edit:empty assert:cleared edit:gamma assert:url; do
 *     ddev exec php /var/www/html/event-terms-test.php "$s" 2>&1 | grep -v '^{'
 *   done
 *
 * Assert steps exit non-zero on failure. UID/EVENT below are dev-fixture ids —
 * point them at any channel-calendar event that has a companion item row.
 */

chdir('/var/www/html/core');
require_once('boot.php');
sys_boot();

$spa = '/var/www/html/core/extend/theme/utsukta-themes/solidified/spa-core/Api/';
require_once($spa . 'Response.php');
require_once($spa . 'Auth.php');
require_once($spa . 'Handlers/Cal.php');

use Utsukta\SpaCore\Api\Auth;

const UID     = 26;
const EVENT   = 95;
const MENTION = 'https://hz-ddev.ddev.site/channel/plato';

if (!session_id()) { @session_start(); }
$_SESSION['authenticated'] = 1;
$_SESSION['uid']           = UID;
\App::$channel             = channelx_by_n(UID);
$channel                   = \App::$channel;

$ev = q("SELECT * FROM event WHERE id = %d AND uid = %d LIMIT 1", EVENT, UID);
if (!$ev) { exit("FAIL: event " . EVENT . " not found\n"); }
$ev = $ev[0];

$ir = q("SELECT id FROM item WHERE resource_id = '%s' AND resource_type = 'event' AND uid = %d LIMIT 1",
    dbesc($ev['event_hash']), UID);
if (!$ir) { exit("FAIL: no companion item\n"); }
$itemId = intval($ir[0]['id']);

function termState(int $itemId): array {
    $rows = q("SELECT ttype, term FROM term WHERE oid = %d AND otype = %d ORDER BY ttype, term",
        $itemId, intval(TERM_OBJ_POST));
    return array_map(fn($x) => intval($x['ttype']) . ':' . $x['term'], $rows ?: []);
}
function mentionUrl(int $itemId): string {
    $r = q("SELECT url FROM term WHERE oid = %d AND otype = %d AND ttype = %d LIMIT 1",
        $itemId, intval(TERM_OBJ_POST), intval(TERM_MENTION));
    return $r ? $r[0]['url'] : '(none)';
}

function runEdit(array $extra, array $ev, array $channel): void {
    $base = [
        'title'       => $ev['summary'],
        'description' => $ev['description'],
        'location'    => $ev['location'],
        'start'    => datetime_convert('UTC', 'UTC', $ev['dtstart'], 'c'),
        'end'      => intval($ev['nofinish']) ? null : datetime_convert('UTC', 'UTC', $ev['dtend'], 'c'),
        'allDay'   => !intval($ev['adjust']),
        'nofinish' => (bool) intval($ev['nofinish']),
        'timezone' => 'Asia/Kolkata',
    ];
    Auth::$parsedBody = array_merge($base, $extra);

    $h = new \Utsukta\SpaCore\Api\Handlers\Cal();
    $m = (new ReflectionClass($h))->getMethod('editEvent');
    $m->setAccessible(true);
    $m->invoke($h, (int) UID, $channel, (int) EVENT);   // Response::send() exits here
}

$step = $argv[1] ?? '';

switch ($step) {
    case 'seed':
        q("DELETE FROM term WHERE oid = %d AND otype = %d", $itemId, intval(TERM_OBJ_POST));
        foreach ([
            [TERM_CATEGORY, 'SeedCat', 'https://hz-ddev.ddev.site/channel/aristotle?f=&cat=SeedCat'],
            [TERM_HASHTAG,  'seedtag', 'https://hz-ddev.ddev.site/search?tag=seedtag'],
            [TERM_MENTION,  'plato',   MENTION],
        ] as [$tt, $tm, $url]) {
            q("INSERT INTO term (uid, oid, otype, ttype, term, url, imgurl) VALUES (%d,%d,%d,%d,'%s','%s','')",
                intval(UID), $itemId, intval(TERM_OBJ_POST), intval($tt), dbesc($tm), dbesc($url));
        }
        set_iconfig($itemId, 'event', 'timezone', 'Asia/Kolkata', true);
        echo "seeded item $itemId: " . implode(' ', termState($itemId)) . "\n";
        break;

    case 'edit:cats':  runEdit(['categories' => 'Alpha, Beta'], $ev, $channel); break;
    case 'edit:omit':  runEdit(['title' => $ev['summary']],     $ev, $channel); break;
    case 'edit:empty': runEdit(['categories' => ''],            $ev, $channel); break;
    case 'edit:gamma': runEdit(['categories' => 'Gamma'],       $ev, $channel); break;

    case 'assert:replaced':
        // Categories replaced; hashtag + mention preserved, mention url untouched.
        assertEq('categories replaced, others kept', termState($itemId),
            ['1:seedtag', '2:plato', '3:Alpha', '3:Beta']);
        assertEq('mention url not rewritten', mentionUrl($itemId), MENTION);
        assertEq('timezone iconfig survives', get_iconfig($itemId, 'event', 'timezone'), 'Asia/Kolkata');
        break;

    case 'assert:kept':
        assertEq('omitted key keeps categories', termState($itemId),
            ['1:seedtag', '2:plato', '3:Alpha', '3:Beta']);
        break;

    case 'assert:cleared':
        assertEq('empty string clears categories only', termState($itemId),
            ['1:seedtag', '2:plato']);
        break;

    case 'assert:url':
        $r = q("SELECT url FROM term WHERE oid = %d AND ttype = %d LIMIT 1",
            $itemId, intval(TERM_CATEGORY));
        // Compare decoded: core's shared event->item store path sometimes writes the
        // ampersand raw and sometimes HTML-escaped (observed both ways from core's own
        // Channel_calendar::post AND from this handler, through the identical
        // event_store_item -> item_store_update call). That nondeterminism is core's,
        // not ours — what this asserts is that we feed core the same string core's own
        // editor feeds it: <xchan_url>?f=&cat=<name>.
        $got = $r ? html_entity_decode($r[0]['url'], ENT_QUOTES, 'UTF-8') : null;
        assertEq('category url matches core form', $got,
            $channel['xchan_url'] . '?f=&cat=Gamma');
        break;

    default:
        exit("unknown step: $step\n");
}

function assertEq(string $label, $got, $want): void {
    $ok = $got === $want;
    printf("  %-42s %s\n", $label, $ok ? 'PASS' : 'FAIL');
    if (!$ok) {
        echo "      got:  " . json_encode($got) . "\n";
        echo "      want: " . json_encode($want) . "\n";
        exit(1);
    }
}
