<?php
if (PHP_SAPI !== 'cli') exit;   // deployed into the web root by the build; never runnable over HTTP
/**
 * Check for GET /spa/item/counts — the stream's live-count poll.
 *
 * Asserts the counts match an independent COUNT over the reaction rows, that
 * an anonymous observer gets nothing back for a private item (the endpoint is
 * a GET with no auth guard, so item_permissions_sql is the only fence), and
 * that more than COUNTS_MAX uuids are truncated rather than queried.
 *
 * Run inside the Hubzilla install:
 *   ddev exec php core/extend/theme/utsukta-themes/solidified/spa-core/Api/Handlers/Item.counts.test.php
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

// ── Step mode: php Item.counts.test.php step <nick|-> <uid> <uuid,uuid,…>
if (($argv[1] ?? '') === 'step') {
    [$nick, $uid, $uuids] = [$argv[2], $argv[3], $argv[4]];
    session_id('item-counts-test');
    $_SESSION = [];
    if ($nick !== '-') {
        $ch = q("SELECT * FROM channel WHERE channel_address = '%s' LIMIT 1", dbesc($nick));
        $_SESSION['uid']           = intval($ch[0]['channel_id']);
        $_SESSION['authenticated'] = 1;
        \App::$channel  = $ch[0];
        $x = q("SELECT * FROM xchan WHERE xchan_hash = '%s' LIMIT 1", dbesc($ch[0]['channel_hash']));
        \App::$observer = $x[0];
    }
    $_GET = ['uid' => $uid, 'uuids' => explode(',', $uuids)];
    \App::$argv = ['spa', 'item', 'counts'];
    \App::$argc = 3;
    (new Utsukta\SpaCore\Api\Handlers\Item)->get();
    exit;
}

// ── Driver
$fail = 0;
function check(string $label, $got, $want = true): void {
    global $fail;
    if ($got === $want) { echo "ok    $label\n"; return; }
    $fail++;
    echo "FAIL  $label\n      got  " . json_encode($got) . "\n      want " . json_encode($want) . "\n";
}
function step(string $nick, int $uid, array $uuids): ?array {
    $out = shell_exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__FILE__) . ' step '
        . escapeshellarg($nick) . ' ' . $uid . ' ' . escapeshellarg(implode(',', $uuids)) . ' 2>&1');
    $j = json_decode((string) $out, true);
    if (!is_array($j)) { echo "      raw: " . substr((string) $out, 0, 400) . "\n"; return null; }
    return $j['data'] ?? [];
}

// A thread top that has at least one Like, so the count is non-trivial.
$r = q("SELECT i.uuid, i.mid, i.uid, c.channel_address FROM item i
        JOIN channel c ON c.channel_id = i.uid
        WHERE i.item_thread_top = 1 AND i.item_deleted = 0
          AND EXISTS (SELECT 1 FROM item r WHERE r.uid = i.uid AND r.thr_parent = i.mid AND r.verb = 'Like' AND r.item_deleted = 0)
        ORDER BY i.id DESC LIMIT 1");
if (!$r) { echo "SKIP  no liked thread top in this database\n"; exit(0); }
[$uuid, $mid, $uid, $nick] = [$r[0]['uuid'], $r[0]['mid'], intval($r[0]['uid']), $r[0]['channel_address']];
echo "item: $uuid (channel $nick)\n";

$want = q("SELECT COUNT(DISTINCT author_xchan) AS n FROM item
           WHERE uid = %d AND thr_parent = '%s' AND item_thread_top = 0 AND verb = 'Like'
             AND item_deleted = 0 AND item_hidden = 0 AND item_unpublished = 0
             AND item_pending_remove = 0 AND item_blocked = 0 AND item_delayed = 0",
    $uid, dbesc($mid));
$got = step($nick, $uid, [$uuid]);
check('owner gets the item', isset($got[$uuid]));
check('like_count matches an independent count', $got[$uuid]['like_count'] ?? null, intval($want[0]['n']));
check('viewer flags are booleans', is_bool($got[$uuid]['viewer_liked'] ?? null));

// Anonymous observer on a private item → nothing.
$p = q("SELECT uuid, uid FROM item WHERE item_private > 0 AND item_thread_top = 1 AND item_deleted = 0
        ORDER BY id DESC LIMIT 1");
if ($p) {
    check('anonymous gets nothing for a private item', step('-', intval($p[0]['uid']), [$p[0]['uuid']]), []);
} else {
    echo "SKIP  no private thread top\n";
}

// Cap: 60 real uuids in, at most 50 out.
$many = q("SELECT uuid FROM item WHERE uid = %d AND item_deleted = 0 AND item_thread_top = 1
           ORDER BY id DESC LIMIT 60", $uid);
if (count($many) > 50) {
    check('more than COUNTS_MAX uuids are truncated', count(step($nick, $uid, array_column($many, 'uuid'))) <= 50);
} else {
    echo "SKIP  fewer than 51 thread tops for the cap check\n";
}

echo $fail ? "\n$fail failed\n" : "\nall ok\n";
exit($fail ? 1 : 0);
