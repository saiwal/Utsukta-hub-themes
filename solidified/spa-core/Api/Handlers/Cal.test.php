<?php
if (PHP_SAPI !== 'cli') exit;   // deployed into the web root by the build; never runnable over HTTP
/**
 * Self-check for Handlers/Cal against core's calendar semantics.
 *
 * Creates an event through the handler (escaped + #tag-linkified like core's
 * Channel_calendar), plants a connection's birthday and a two-year-old
 * open-ended event straight into the event table, then asserts the feed:
 * author hydrated, etype emitted, visitors see only the owner's own events,
 * stale nofinish rows don't pile into every range. Deletes everything again.
 *
 * Writes (and removes) rows. Run inside the Hubzilla install:
 *   ddev exec php core/extend/theme/utsukta-themes/solidified/spa-core/Api/Handlers/Cal.test.php
 */

for ($dir = __DIR__; $dir !== '/'; $dir = dirname($dir)) {
    if (file_exists("$dir/include/cli_startup.php")) { chdir($dir); break; }
}
require_once 'include/cli_startup.php';
cli_startup();
require_once dirname(__DIR__, 3) . '/vendor/autoload.php';
require_once 'include/event.php';

use Utsukta\SpaCore\Api\Handlers\Cal;

$ch = q("SELECT * FROM channel LEFT JOIN xchan ON xchan_hash = channel_hash
         WHERE channel_system = 0 AND channel_removed = 0 ORDER BY channel_id LIMIT 1")[0];

// ── child steps: one handler call per process (Response exits) ───────────────

function caltestLogin(array $ch): void
{
    session_id('caltest');
    $_SESSION['authenticated'] = 1;
    $_SESSION['uid'] = intval($ch['channel_id']);
    $_SESSION['solidified_csrf'] = $_SERVER['HTTP_X_CSRF_TOKEN'] = 'caltest';
    App::$channel  = $ch;
    App::$observer = $ch;
}

if (($step = $argv[1] ?? '') !== '') {
    $arg = json_decode($argv[2] ?? '{}', true);
    if ($arg['login'] ?? false) caltestLogin($ch);
    App::$argv = $arg['argv'];
    App::$argc = count(App::$argv);
    $_GET = $arg['get'] ?? [];
    if ($step === 'post') {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['CONTENT_TYPE']   = 'multipart/form-data';
        $_POST = $arg['body'];
        (new Cal())->post();
    } else {
        (new Cal())->get();
    }
    exit;
}

function call(string $step, array $arg): array
{
    $out = shell_exec('php ' . escapeshellarg(__FILE__) . ' ' . $step . ' ' . escapeshellarg(json_encode($arg)));
    $j = json_decode(substr($out, strpos($out, '{')), true);
    return $j ?? ['raw' => $out];
}

$fail = 0;
function check(string $label, $got, $want): void
{
    global $fail;
    if ($got === $want) { echo "ok    $label\n"; return; }
    $fail++;
    echo "FAIL  $label\n      got  " . json_encode($got) . "\n      want " . json_encode($want) . "\n";
}

$nick  = $ch['channel_address'];
$start = gmdate('Y-m-d\TH:i:s\Z', time() + 86400);
$end   = gmdate('Y-m-d\TH:i:s\Z', time() + 90000);
$range = ['start' => gmdate('Y-m-d'), 'end' => gmdate('Y-m-d', time() + 3 * 86400)];

// ── create through the handler ───────────────────────────────────────────────

$c = call('post', ['login' => true, 'argv' => ['spa', 'cal'], 'body' => [
    'title' => 'Cal <b> & test', 'description' => 'party #caltesttag', 'location' => '',
    'start' => $start, 'end' => $end, 'scope' => 'public', 'timezone' => 'UTC',
]]);
$id = intval($c['data']['id'] ?? 0);
check('created', $id > 0, true);

$row = q("SELECT * FROM event WHERE id = %d", $id)[0] ?? [];
check('summary stored escaped like core', $row['summary'] ?? null, 'Cal &lt;b&gt; &amp; test');
check('hashtag linkified', str_contains($row['description'] ?? '', '[zrl='), true);
$tag = q("SELECT term.term FROM term JOIN item ON item.id = term.oid
          WHERE item.resource_id = '%s' AND item.uid = %d AND term.ttype = %d",
    dbesc($row['event_hash'] ?? ''), intval($ch['channel_id']), TERM_HASHTAG);
check('hashtag term row', $tag[0]['term'] ?? null, 'caltesttag');

// ── rows a calendar collects without authoring them ──────────────────────────

$other = q("SELECT xchan_hash FROM xchan WHERE xchan_hash != '%s' LIMIT 1", dbesc($ch['channel_hash']))[0]['xchan_hash'];
caltestLogin($ch);   // event_store_event_end hooks (sse) expect a session
$base = ['uid' => $ch['channel_id'], 'account' => $ch['channel_account_id'], 'adjust' => 0, 'description' => ''];
$bday = event_store_event($base + [
    'event_xchan' => $other, 'etype' => 'birthday', 'summary' => 'Someone\'s birthday',
    'dtstart' => gmdate('Y-m-d 00:00:00', time() + 86400), 'dtend' => gmdate('Y-m-d 00:00:00', time() + 2 * 86400),
]);
$stale = event_store_event($base + [
    'event_xchan' => $ch['channel_hash'], 'etype' => 'event', 'summary' => 'Old open-ended',
    'dtstart' => gmdate('Y-m-d 00:00:00', time() - 2 * 365 * 86400), 'dtend' => '',
]);

// ── feed ─────────────────────────────────────────────────────────────────────

$own = call('get', ['login' => true, 'argv' => ['spa', 'cal', $nick], 'get' => $range]);
$byId = array_column($own['data'] ?? [], null, 'id');
$mine = $byId[$id] ?? [];
check('owner sees own event', isset($byId[$id]), true);
check('title decoded on read', $mine['title'] ?? null, 'Cal <b> & test');
check('author hydrated', $mine['author']['name'] ?? null, $ch['channel_name']);
check('etype emitted', $mine['etype'] ?? null, 'event');
check('owner sees birthday', ($byId[$bday['id']]['etype'] ?? null), 'birthday');
check('stale nofinish not in range', isset($byId[$stale['id']]), false);

$one = call('get', ['login' => true, 'argv' => ['spa', 'cal', $nick], 'get' => ['id' => $id]]);
check('?id= editable for author', $one['data'][0]['editable'] ?? null, true);

$vis = call('get', ['argv' => ['spa', 'cal', $nick], 'get' => $range]);
if (isset($vis['data'])) {
    $vIds = array_column($vis['data'], 'id');
    check('visitor sees own event', in_array($id, $vIds, true), true);
    check('visitor never sees collected events', in_array(intval($bday['id']), $vIds, true), false);
} else {
    echo "skip  visitor checks (channel not publicly viewable)\n";
}

// ── delete + cleanup ─────────────────────────────────────────────────────────

$d = call('post', ['login' => true, 'argv' => ['spa', 'cal', (string) $id, 'delete'], 'body' => ['x' => 1]]);
check('deleted', $d['data']['deleted'] ?? null, true);
check('event row gone', (bool) q("SELECT id FROM event WHERE id = %d", $id), false);

q("DELETE FROM event WHERE id IN (%d, %d)", intval($bday['id']), intval($stale['id']));

echo $fail ? "\n$fail FAILED\n" : "\nall passed\n";
exit($fail ? 1 : 0);
