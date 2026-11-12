<?php
if (PHP_SAPI !== 'cli') exit;   // deployed into the web root by the build; never runnable over HTTP
/**
 * Read-path permission guard: a private item must be invisible to anyone the
 * ACL does not name, through the SPA's own endpoints.
 *
 * Phase 3 of the core-parity audit read every handler that queries `item`,
 * `photo` or `attach` and found each one either owner-scoped or gated by
 * item_permissions_sql()/permissions_sql(). This is that conclusion made
 * runnable: reading the audit again next release is expensive, running this is
 * not.
 *
 * Three viewers per endpoint — the owner, another local channel, and nobody —
 * against one public and one private probe post. Both probes are deleted again.
 *
 * (Lockview.test.php covers the harder guest-token + privacy-group case.)
 *
 * Run inside the Hubzilla install:
 *   ddev exec php core/extend/theme/utsukta-themes/solidified/spa-core/Api/Handlers/Permissions.test.php
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

// ── Step mode: read one endpoint as one viewer ─────────────────────────────
//   php Permissions.test.php step <viewer-nick|-> <handler> <owner-nick> <arg>
if (($argv[1] ?? '') === 'step') {
    $viewer  = $argv[2];
    $handler = $argv[3];
    $owner   = $argv[4];
    $arg     = $argv[5] ?? '';

    // Always start a session, even for the anonymous viewer: local_channel()
    // reads $_SESSION and fatals on null. A viewer that crashes "sees" nothing,
    // which would make every "cannot see" assertion below pass vacuously — the
    // "anonymous sees the public post" check is what catches that.
    session_id('permissions-test-' . ($viewer === '-' ? 'anon' : $viewer));
    @session_start();
    $_SESSION = [];

    if ($viewer !== '-') {
        $vc = q("SELECT * FROM channel WHERE channel_address = '%s' LIMIT 1", dbesc($viewer));
        $_SESSION['uid']           = intval($vc[0]['channel_id']);
        $_SESSION['authenticated'] = 1;
        \App::$channel  = $vc[0];
        \App::$observer = q("SELECT * FROM xchan WHERE xchan_hash = '%s' LIMIT 1",
            dbesc($vc[0]['channel_hash']))[0];
    }

    \App::$argv = $handler === 'channel'
        ? ['spa', 'channel', $owner]
        : ['spa', 'display', $arg];
    \App::$argc = count(\App::$argv);

    $cls = '\\Utsukta\\SpaCore\\Api\\Handlers\\' . ucfirst($handler);
    (new $cls)->get();
    exit;
}

// ── Driver ─────────────────────────────────────────────────────────────────
$nick = $argv[1] ?? null;
if (!$nick) {
    $c = q("SELECT channel_address FROM channel WHERE channel_removed = 0
            AND channel_system = 0 ORDER BY channel_id LIMIT 1");
    if (!$c) { echo "SKIP  no local channel in this database\n"; exit(0); }
    $nick = $c[0]['channel_address'];
}
$owner = q("SELECT * FROM channel WHERE channel_address = '%s' LIMIT 1", dbesc($nick));
if (!$owner) { echo "no such channel: $nick\n"; exit(2); }
$owner = $owner[0];
$uid   = intval($owner['channel_id']);

$other = q("SELECT * FROM channel WHERE channel_id != %d AND channel_removed = 0
            AND channel_system = 0 ORDER BY channel_id LIMIT 1", intval($uid));
if (!$other) { echo "SKIP  need a second local channel to read as an outsider\n"; exit(0); }
$other = $other[0];

echo "owner: $nick   outsider: " . $other['channel_address'] . "\n";

$fail = 0;
function check(string $label, $got, $want = true): void
{
    global $fail;
    if ($got === $want) { echo "ok    $label\n"; return; }
    $fail++;
    echo "FAIL  $label\n      got  " . json_encode($got) . "\n      want " . json_encode($want) . "\n";
}

function step(string $viewer, string $handler, string $owner, string $arg = ''): string
{
    return (string) shell_exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__FILE__)
        . ' step ' . escapeshellarg($viewer) . ' ' . escapeshellarg($handler) . ' '
        . escapeshellarg($owner) . ' ' . escapeshellarg($arg) . ' 2>&1');
}

/** Store a probe post directly — this tests the READ paths, not posting. */
function probe(array $owner, bool $private): array
{
    $uuid = item_message_id();
    $mid  = z_root() . '/item/' . $uuid;
    $now  = datetime_convert();
    $arr  = [
        'aid' => intval($owner['channel_account_id']), 'uid' => intval($owner['channel_id']),
        'uuid' => $uuid, 'mid' => $mid, 'parent_mid' => $mid, 'thr_parent' => $mid,
        'owner_xchan' => $owner['channel_hash'], 'author_xchan' => $owner['channel_hash'],
        'created' => $now, 'edited' => $now, 'commented' => $now, 'received' => $now,
        'changed' => $now, 'verb' => 'Create', 'obj_type' => 'Note', 'mimetype' => 'text/bbcode',
        'title' => 'permprobe ' . ($private ? 'private' : 'public'),
        'body' => 'permprobe body ' . bin2hex(random_bytes(4)),
        'item_wall' => 1, 'item_origin' => 1, 'item_thread_top' => 1, 'item_unseen' => 0,
        'item_private' => $private ? 1 : 0, 'plink' => $mid,
        'allow_cid' => $private ? '<' . $owner['channel_hash'] . '>' : '',
        'allow_gid' => '', 'deny_cid' => '', 'deny_gid' => '',
    ];
    $r = item_store($arr, false, false, false);   // no deliver, no notifier
    return ['id' => intval($r['item_id'] ?? 0), 'uuid' => $uuid];
}

$pub  = probe($owner, false);
$priv = probe($owner, true);
echo "probes: public id={$pub['id']}  private id={$priv['id']}\n\n";

if (!$pub['id'] || !$priv['id']) {
    echo "FAIL  could not store the probes\n";
    exit(1);
}

$viewers = [
    'owner'    => $nick,
    'outsider' => $other['channel_address'],
    'anonymous'=> '-',
];

foreach ($viewers as $label => $viewer) {
    $out = step($viewer, 'channel', $nick);
    $sawPublic  = str_contains($out, $pub['uuid']);
    $sawPrivate = str_contains($out, $priv['uuid']);

    check("channel/$label sees the public post", $sawPublic, true);
    check("channel/$label " . ($label === 'owner' ? 'sees' : 'cannot see') . " the private post",
        $sawPrivate, $label === 'owner');
}

foreach ($viewers as $label => $viewer) {
    $out = step($viewer, 'display', $nick, $priv['uuid']);
    // Either an error envelope or simply no trace of the body.
    $leaked = str_contains($out, 'permprobe body') && str_contains($out, $priv['uuid']);
    check("display/$label " . ($label === 'owner' ? 'sees' : 'cannot see') . " the private post",
        $leaked, $label === 'owner');
}

q("DELETE FROM item WHERE id IN (%d, %d)", intval($pub['id']), intval($priv['id']));
echo "\nprobes removed\n";

echo $fail ? "\n$fail check(s) FAILED\n" : "\nall permission checks passed\n";
exit($fail ? 1 : 0);
