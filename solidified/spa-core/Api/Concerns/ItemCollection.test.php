<?php
/**
 * Round-trip check for Concerns/ItemCollection — the machinery shared by the
 * Articles and Cards handlers.
 *
 * The point of interest is what a *second* save preserves. item_store_update()
 * deletes all of an item's term rows and all of its iconfig rows and re-inserts
 * only what the datarray carries, so buildEditDatarray()'s pre-loads are the
 * only thing standing between an ordinary title edit and silently losing the
 * item's slug, its group membership, its hashtags and its categories. This
 * creates a real article and a real card, edits each, and asserts nothing
 * fell off. Both are deleted again at the end.
 *
 * Run inside the Hubzilla install:
 *   ddev exec php core/extend/theme/utsukta-themes/solidified/spa-core/Api/Concerns/ItemCollection.test.php
 *
 * Response::send()/error() exit, so each handler call has to be its own
 * process — the driver below re-executes this file per step.
 */

for ($dir = __DIR__; $dir !== '/'; $dir = dirname($dir)) {
    if (file_exists("$dir/include/cli_startup.php")) { chdir($dir); break; }
}
require_once 'include/cli_startup.php';
cli_startup();

// The handlers are autoloaded by the *theme's* composer setup (the same one
// src/mod/spa.php pulls in), not Hubzilla's — so this only runs from the
// deployed theme, two levels above spa-core/Api.
$autoload = __DIR__ . '/../../../vendor/autoload.php';
if (!file_exists($autoload)) {
    fwrite(STDERR, "run this from the deployed theme (expected $autoload)\n");
    exit(2);
}
require_once $autoload;

const SLUG_A = 'itemcollection-test-article';
const SLUG_C = 'itemcollection-test-card';
const TITLE_A = 'ItemCollection probe article';
const TITLE_C = 'ItemCollection probe card';

// ---------------------------------------------------------------------------
// Step mode: authenticate as $nick and run one handler call.
//   php ItemCollection.test.php step <Articles|Cards> <nick> <get|post> <argv3> <json>
// ---------------------------------------------------------------------------

if (($argv[1] ?? '') === 'step') {
    [$cls, $nick, $verb, $a3, $a4] = [$argv[2], $argv[3], $argv[4], $argv[5], $argv[6]];
    $payload = json_decode($argv[7] ?? '{}', true) ?: [];

    // local_channel() also requires a non-empty session_id(), which a CLI
    // process has none of — naming one is enough, no store needs to exist.
    session_id('itemcollection-test');

    $ch = q("SELECT * FROM channel WHERE channel_address = '%s' LIMIT 1", dbesc($nick));
    $_SESSION['uid']           = intval($ch[0]['channel_id']);
    $_SESSION['authenticated'] = 1;
    $_SESSION['solidified_csrf']      = 'test-token';
    $_SERVER['HTTP_X_CSRF_TOKEN']     = 'test-token';
    // The form-data branch of Auth::parseJsonBody() reads $_POST, which a CLI
    // process can populate — php://input cannot be written to.
    $_SERVER['CONTENT_TYPE']   = 'multipart/form-data';
    $_POST                     = $payload;

    \App::$channel = $ch[0];
    $x = q("SELECT * FROM xchan WHERE xchan_hash = '%s' LIMIT 1", dbesc($ch[0]['channel_hash']));
    \App::$observer = $x[0];
    \App::$argv = ['spa', strtolower($cls), $nick,
        $a3 === '-' ? '' : $a3, $a4 === '-' ? '' : $a4];
    \App::$argc = 5;

    $fqcn = "Utsukta\\SpaCore\\Api\\Handlers\\$cls";
    (new $fqcn)->$verb();
    exit;
}

// ---------------------------------------------------------------------------
// Driver
// ---------------------------------------------------------------------------

$nick = $argv[1] ?? null;
if (!$nick) {
    // Any local channel with write_pages on itself will do; prefer the admin's.
    $c = q("SELECT channel_address FROM channel WHERE channel_removed = 0
            AND channel_system = 0 ORDER BY channel_id LIMIT 1");
    if (!$c) { echo "SKIP  no local channel in this database\n"; exit(0); }
    $nick = $c[0]['channel_address'];
}
echo "channel: $nick\n";

$fail = 0;
function check(string $label, $got, $want = true): void
{
    global $fail;
    if ($got === $want) { echo "ok    $label\n"; return; }
    $fail++;
    echo "FAIL  $label\n      got  " . json_encode($got) . "\n      want " . json_encode($want) . "\n";
}

function step(string $cls, string $nick, string $verb, string $a3, array $payload = [], string $a4 = ''): array
{
    $cmd = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__FILE__) . ' step '
        . escapeshellarg($cls) . ' ' . escapeshellarg($nick) . ' ' . escapeshellarg($verb) . ' '
        . escapeshellarg($a3 ?: '-') . ' ' . escapeshellarg($a4 ?: '-') . ' '
        . escapeshellarg(json_encode($payload));
    $out = shell_exec($cmd . ' 2>&1');
    $j   = json_decode((string) $out, true);
    if (!is_array($j)) {
        echo "      raw: " . substr((string) $out, 0, 400) . "\n";
        return ['error' => ['message' => 'unparseable response']];
    }
    return $j;
}

/**
 * Delete the probe items and everything hanging off them. Matched on the
 * probe titles rather than on the slug iconfig row — a create that fails
 * before its iconfig is written would otherwise leave the item behind
 * forever, invisible to the next run's cleanup.
 */
function cleanup(): void
{
    $r = q("SELECT id FROM item WHERE title IN ('%s', '%s', '%s', '%s')",
        dbesc(TITLE_A), dbesc(TITLE_A . ' edited'),
        dbesc(TITLE_C), dbesc(TITLE_C . ' edited'));

    foreach ($r ?: [] as $row) {
        $iid = intval($row['id']);
        q("DELETE FROM iconfig WHERE iid = %d", $iid);
        q("DELETE FROM term WHERE oid = %d AND otype = %d", $iid, intval(TERM_OBJ_POST));
        q("DELETE FROM item WHERE id = %d OR parent = %d", $iid, $iid);
    }
}

cleanup();

// ── Articles ────────────────────────────────────────────────────────────────

$created = step('Articles', $nick, 'post', '', [
    'body'         => 'A body.',
    'title'        => TITLE_A,
    'slug'         => SLUG_A,
    'lang'         => 'en',
    'mimetype'     => 'text/bbcode',
    'category'     => 'probe-one,probe-two',
    'series'       => 'probe series',
    'series_order' => 3,
]);
check('article created', isset($created['data']['iid']));
$iid = intval($created['data']['iid'] ?? 0);
if (!$iid) { echo json_encode($created) . "\n"; cleanup(); exit(1); }

// A hashtag term, inserted directly: neither handler parses bare "#word" out
// of a body (real ones arrive from the composer as bbcode zrl tags), and what
// matters here is only that a later edit does not delete a term row it didn't
// create. item_store_update() deletes every term row for the item and
// re-inserts the datarray's, so this is the row buildEditDatarray() has to
// carry forward.
$owner = q("SELECT uid FROM item WHERE id = %d LIMIT 1", $iid);
q("INSERT INTO term (uid, oid, otype, ttype, term, url) VALUES (%d, %d, %d, %d, '%s', '')",
    intval($owner[0]['uid']), $iid, intval(TERM_OBJ_POST),
    intval(TERM_HASHTAG), dbesc('itemcollectiontag'));

$got = step('Articles', $nick, 'get', SLUG_A)['data']['article'] ?? [];
check('article slug',       $got['slug']            ?? null, SLUG_A);
check('article series',     $got['series']          ?? null, ['name' => 'probe series', 'order' => 3]);
check('article categories', $got['categories']      ?? null, ['probe-one', 'probe-two']);
check('article lang',       $got['lang']            ?? null, 'en');
check('article view_url',   $got['view_url']        ?? null, z_root() . '/articles/' . $nick . '/' . SLUG_A);
check('article not private', $got['item_private']   ?? null, 0);

// Edit with NO 'category' key: categories must survive untouched, and so must
// the slug, the series and the hashtag that item_store_update() would drop.
step('Articles', $nick, 'post', '', [
    'body'     => 'A body.',
    'title'    => TITLE_A . ' edited',
    'lang'     => 'de',
    'mimetype' => 'text/bbcode',
    'post_id'  => $iid,
    'series'   => 'probe series',
    'series_order' => 3,
    'slug'     => SLUG_A,
]);
$got = step('Articles', $nick, 'get', SLUG_A)['data']['article'] ?? [];
check('edit kept slug',       $got['slug']       ?? null, SLUG_A);
check('edit kept series',     $got['series']     ?? null, ['name' => 'probe series', 'order' => 3]);
check('edit kept categories', $got['categories'] ?? null, ['probe-one', 'probe-two']);
check('edit kept tags',       $got['tags']       ?? null, ['itemcollectiontag']);
check('edit applied title',   $got['title']      ?? null, TITLE_A . ' edited');
check('edit applied lang',    $got['lang']       ?? null, 'de');

// Edit with category='': categories cleared, hashtags still not collateral.
step('Articles', $nick, 'post', '', [
    'body'     => 'A body.',
    'title'    => TITLE_A . ' edited',
    'lang'     => 'de',
    'mimetype' => 'text/bbcode',
    'post_id'  => $iid,
    'category' => '',
    'slug'     => SLUG_A,
]);
$got = step('Articles', $nick, 'get', SLUG_A)['data']['article'] ?? [];
check('empty category clears categories', $got['categories'] ?? null, []);
check('empty category keeps tags',        $got['tags']       ?? null, ['itemcollectiontag']);
check('dropping series clears it',        $got['series']     ?? null, null);

// ── Cards ───────────────────────────────────────────────────────────────────

$created = step('Cards', $nick, 'post', '', [
    'body'       => 'Card body.',
    'title'      => TITLE_C,
    'slug'       => SLUG_C,
    'mimetype'   => 'text/bbcode',
    'deck'       => 'probe deck',
    'deck_order' => 2,
    'template'   => 'quote',
]);
check('card created', isset($created['data']['iid']));
$cid = intval($created['data']['iid'] ?? 0);

if ($cid) {
    $got = step('Cards', $nick, 'get', SLUG_C)['data']['card'] ?? [];
    check('card slug',     $got['slug']     ?? null, SLUG_C);
    check('card deck',     $got['deck']     ?? null, ['name' => 'probe deck', 'order' => 2]);
    check('card template', $got['template'] ?? null, 'quote');

    // A drag is not an edit: deck-move rewrites two iconfig rows and must leave
    // the template and the slug alone.
    step('Cards', $nick, 'post', 'deck-move', [
        'uuid'  => $got['uuid'],
        'deck'  => 'probe deck two',
        'order' => 7,
    ]);
    $got = step('Cards', $nick, 'get', SLUG_C)['data']['card'] ?? [];
    check('move changed deck',     $got['deck']     ?? null, ['name' => 'probe deck two', 'order' => 7]);
    check('move kept template',    $got['template'] ?? null, 'quote');
    check('move kept slug',        $got['slug']     ?? null, SLUG_C);

    step('Cards', $nick, 'post', '', [
        'body'       => 'Card body.',
        'title'      => TITLE_C . ' edited',
        'mimetype'   => 'text/bbcode',
        'post_id'    => $cid,
        'slug'       => SLUG_C,
        'deck'       => 'probe deck two',
        'deck_order' => 7,
        'template'   => 'quote',
    ]);
    $got = step('Cards', $nick, 'get', SLUG_C)['data']['card'] ?? [];
    check('card edit kept deck',     $got['deck']     ?? null, ['name' => 'probe deck two', 'order' => 7]);
    check('card edit kept template', $got['template'] ?? null, 'quote');
    check('card edit applied title', $got['title']    ?? null, TITLE_C . ' edited');

    // The deck overview must see the card, and the deck detail must return it.
    $decks = step('Cards', $nick, 'get', 'deck')['data']['decks'] ?? [];
    check('deck overview lists the deck',
        in_array('probe deck two', array_column($decks, 'name'), true));
    $detail = step('Cards', $nick, 'get', 'deck', [], 'probe deck two');
    check('deck detail returns the card',
        array_column($detail['data']['cards'] ?? [], 'slug'), [SLUG_C]);

    $list = step('Cards', $nick, 'get', '');
    check('list still returns cards', is_array($list['data'] ?? null));
}

cleanup();
echo "\n" . ($fail ? "$fail check(s) failed\n" : "all checks passed\n");
exit($fail ? 1 : 0);
