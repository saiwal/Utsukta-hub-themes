<?php
/**
 * Channel.php verb whitelist — an Announce wall post must reach the channel
 * page.
 *
 * Core's channel module (Zotlabs/Module/Channel.php) has no verb filter at all;
 * it hides the FEP-171b conversation-container rows (verb 'Add') with
 * `item.id = item.parent`. The SPA's whitelist exists because $sql_extra also
 * feeds the flat/nouveau query, which has no thread-top constraint — but it
 * used to omit Announce, so a boost made in the classic UI (or synced from a
 * clone) was on the wall in core and missing here.
 *
 * Creates a real Announce wall item modelled on a Hubzilla-federated boost
 * (bbcode source, zrl/zmg image, attach), asserts /spa/channel returns it in
 * both the threaded and the flat branch, and deletes it again.
 *
 * Run inside the Hubzilla install:
 *   ddev exec php core/extend/theme/utsukta-themes/solidified/spa-core/Api/Handlers/Channel.test.php
 */

for ($dir = __DIR__; $dir !== '/'; $dir = dirname($dir)) {
    if (file_exists("$dir/include/cli_startup.php")) { chdir($dir); break; }
}
require_once 'include/cli_startup.php';
cli_startup();

$autoload = dirname(__DIR__, 3) . '/vendor/autoload.php';
if (!file_exists($autoload)) {
    fwrite(STDERR, "run this from the deployed theme (expected $autoload)\n");
    exit(2);
}
require_once $autoload;

const TITLE = 'OWFDMUS: Donnerstag, 10. September * 11h - 19h in Kölner Stadtteilbibliotheken';
// A probe uuid of this test's own. Never reuse the uuid of a post that might
// actually be in the database — cleanup() deletes by uuid and would eat it.
const UUID  = 'ffffffff-0000-4000-8000-c4a11e1701ce';

// ── Step mode: authenticate as $nick and run one Channel::get() ─────────────
//   php Channel.test.php step <nick> <query string>
// Response::send() exits, so the handler gets its own process.

if (($argv[1] ?? '') === 'step') {
    session_id('channel-test');
    $ch = q("SELECT * FROM channel WHERE channel_address = '%s' LIMIT 1", dbesc($argv[2]));
    $_SESSION['uid']           = intval($ch[0]['channel_id']);
    $_SESSION['authenticated'] = 1;
    parse_str($argv[3] ?? '', $_GET);

    \App::$channel  = $ch[0];
    \App::$observer = q("SELECT * FROM xchan WHERE xchan_hash = '%s' LIMIT 1",
        dbesc($ch[0]['channel_hash']))[0];
    \App::$argv = ['spa', 'channel', $argv[2]];
    \App::$argc = 3;

    (new \Utsukta\SpaCore\Api\Handlers\Channel)->get();
    exit;
}

// ── Driver ──────────────────────────────────────────────────────────────────

$nick = $argv[1] ?? null;
if (!$nick) {
    $c = q("SELECT channel_address FROM channel WHERE channel_removed = 0
            AND channel_system = 0 ORDER BY channel_id LIMIT 1");
    if (!$c) { echo "SKIP  no local channel in this database\n"; exit(0); }
    $nick = $c[0]['channel_address'];
}
$channel = q("SELECT * FROM channel WHERE channel_address = '%s' LIMIT 1", dbesc($nick))[0];
echo "channel: $nick\n";

$fail = 0;
function check(string $label, $got, $want = true): void
{
    global $fail;
    if ($got === $want) { echo "ok    $label\n"; return; }
    $fail++;
    echo "FAIL  $label\n      got  " . json_encode($got) . "\n      want " . json_encode($want) . "\n";
}

function step(string $nick, string $query): array
{
    $out = shell_exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__FILE__)
        . ' step ' . escapeshellarg($nick) . ' ' . escapeshellarg($query) . ' 2>&1');
    $j = json_decode((string) $out, true);
    if (!is_array($j)) {
        echo "      raw: " . substr((string) $out, 0, 400) . "\n";
        return [];
    }
    return $j;
}

function cleanup(): void
{
    foreach (q("SELECT id FROM item WHERE uuid = '%s'", dbesc(UUID)) ?: [] as $row) {
        q("DELETE FROM iconfig WHERE iid = %d", intval($row['id']));
        q("DELETE FROM item WHERE id = %d OR parent = %d", intval($row['id']), intval($row['id']));
    }
}

cleanup();

// A boost as Hubzilla stores it on the booster's own wall: verb Announce,
// item_wall = 1, bbcode source with the zrl/zmg image the AP content carried.
$mid  = z_root() . '/item/' . UUID;
$body = '[zrl=https://im.allmendenetz.de/photos/chris/image/b469d119-06be-4c80-8a1b-18a5f278f8a1]'
    . '[zmg=https://im.allmendenetz.de/photo/b469d119-06be-4c80-8a1b-18a5f278f8a1-1.jpg]'
    . "plakat_10.09.2026.jpg[/zmg][/zrl]\n\n[h1]" . TITLE . "[/h1]\n\n"
    . "Sie sind neugierig, möchten dazulernen und neue Dinge mit FREIER Software ausprobieren?\n\n"
    . "[hr]\n\n10.09.2026 11:00 - 13:00 Uhr\nStadtteilbibliothek Nippes\n50733 Köln\n\n"
    . '[url=https://owfdmus.im.allmendenetz.de]https://owfdmus.im.allmendenetz.de[/url]';

$stored = item_store([
    'aid'             => intval($channel['channel_account_id']),
    'uid'             => intval($channel['channel_id']),
    'mid'             => $mid,
    'uuid'            => UUID,
    'parent_mid'      => $mid,
    'thr_parent'      => $mid,
    'author_xchan'    => $channel['channel_hash'],
    'owner_xchan'     => $channel['channel_hash'],
    'verb'            => ACTIVITY_SHARE,
    'obj_type'        => 'Note',
    'title'           => TITLE,
    'body'            => $body,
    'mimetype'        => 'text/bbcode',
    'plink'           => 'https://im.allmendenetz.de/item/' . UUID,
    'item_wall'       => 1,
    'item_origin'     => 1,
    'item_thread_top' => 1,
    'item_private'    => 0,
    'attach'          => json_encode([[
        'href'   => 'https://im.allmendenetz.de/photo/b469d119-06be-4c80-8a1b-18a5f278f8a1-1.jpg',
        'type'   => 'image/jpeg',
        'title'  => 'plakat_10.09.2026.jpg',
        'width'  => 842,
        'height' => 659,
    ]]),
], false, false);

check('announce wall post stored', $stored['success'] ?? false);
if (!($stored['success'] ?? false)) { cleanup(); exit(1); }

$row = q("SELECT verb, item_wall, item_thread_top FROM item WHERE id = %d",
    intval($stored['item_id']))[0];
check('stored as Announce', $row['verb'], ACTIVITY_SHARE);
check('stored on the wall',  intval($row['item_wall']), 1);

// Threaded branch (the default channel page).
$uuids = array_column(step($nick, '')['data'] ?? [], 'uuid');
check('threaded channel page returns the announce', in_array(UUID, $uuids, true));

// Flat branch — a search filter forces nouveau mode, where $sql_extra is the
// only thing standing between the whitelist and the 'Add' container rows.
$flat = step($nick, 'search=' . rawurlencode('OWFDMUS'))['data'] ?? [];
check('flat channel page returns the announce',
    in_array(UUID, array_column($flat, 'uuid'), true));
check('flat channel page has no container rows',
    array_values(array_unique(array_column($flat, 'verb'))), [ACTIVITY_SHARE]);

cleanup();
check('probe removed', q("SELECT id FROM item WHERE uuid = '%s'", dbesc(UUID)), []);

echo $fail ? "\n$fail check(s) failed\n" : "\nall checks passed\n";
exit($fail ? 1 : 0);
