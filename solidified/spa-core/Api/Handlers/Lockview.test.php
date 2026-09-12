<?php
/**
 * Check for Handlers/Lockview's guest grant/revoke.
 *
 * The case that used to fail silently: an item made private to a *privacy
 * group* has an empty allow_cid, so a guest who can see it because they are a
 * member of that group has nothing to strip — the old revoke str_replace'd an
 * empty string, wrote nothing, and still answered 200. This synthesises that
 * exact shape (guest token + privacy group + group-private item) and asserts
 * the revoke actually removes the guest and the grant puts them back.
 *
 * Run inside the Hubzilla install:
 *   ddev exec php core/extend/theme/utsukta-themes/solidified/spa-core/Api/Handlers/Lockview.test.php
 *
 * Response::send()/error() exit, so each handler call is its own process —
 * the driver re-executes this file per step.
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

const PROBE = 'lockview-probe';

// ── Step mode: php Lockview.test.php step <nick> <get|post> <id> <action> <json>
if (($argv[1] ?? '') === 'step') {
    [$nick, $verb, $id, $action] = [$argv[2], $argv[3], $argv[4], $argv[5]];
    session_id('lockview-test');

    $ch = q("SELECT * FROM channel WHERE channel_address = '%s' LIMIT 1", dbesc($nick));
    $_SESSION['uid']              = intval($ch[0]['channel_id']);
    $_SESSION['authenticated']    = 1;
    $_SESSION['solidified_csrf']  = 'test-token';
    $_SERVER['HTTP_X_CSRF_TOKEN'] = 'test-token';
    $_SERVER['CONTENT_TYPE']      = 'multipart/form-data';
    $_POST                        = json_decode($argv[6] ?? '{}', true) ?: [];

    \App::$channel  = $ch[0];
    $x = q("SELECT * FROM xchan WHERE xchan_hash = '%s' LIMIT 1", dbesc($ch[0]['channel_hash']));
    \App::$observer = $x[0];
    \App::$argv = ['spa', 'lockview', 'item', $id, $action === '-' ? '' : $action];
    \App::$argc = 5;

    (new Utsukta\SpaCore\Api\Handlers\Lockview)->$verb();
    exit;
}

// ── Driver
$c = q("SELECT * FROM channel WHERE channel_removed = 0 AND channel_system = 0
        ORDER BY channel_id LIMIT 1");
if (!$c) { echo "SKIP  no local channel in this database\n"; exit(0); }
$chan = $c[0];
$uid  = intval($chan['channel_id']);
$nick = $chan['channel_address'];
echo "channel: $nick\n";

$fail = 0;
function check(string $label, $got, $want = true): void {
    global $fail;
    if ($got === $want) { echo "ok    $label\n"; return; }
    $fail++;
    echo "FAIL  $label\n      got  " . json_encode($got) . "\n      want " . json_encode($want) . "\n";
}
function step(string $nick, string $verb, $id, string $action = '', array $body = []): array {
    $out = shell_exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__FILE__) . ' step '
        . escapeshellarg($nick) . ' ' . escapeshellarg($verb) . ' ' . escapeshellarg((string) $id) . ' '
        . escapeshellarg($action ?: '-') . ' ' . escapeshellarg(json_encode($body)) . ' 2>&1');
    $j = json_decode((string) $out, true);
    if (!is_array($j)) { echo "      raw: " . substr((string) $out, 0, 400) . "\n"; return []; }
    return $j;
}
function cleanup(int $uid): void {
    q("DELETE FROM item WHERE uid = %d AND title = '%s'", $uid, dbesc(PROBE));
    $g = q("SELECT id FROM pgrp WHERE uid = %d AND gname = '%s'", $uid, dbesc(PROBE));
    foreach ($g ?: [] as $row) { q("DELETE FROM pgrp_member WHERE gid = %d", intval($row['id'])); }
    q("DELETE FROM pgrp WHERE uid = %d AND gname = '%s'", $uid, dbesc(PROBE));
    q("DELETE FROM atoken WHERE atoken_uid = %d AND atoken_name = '%s'", $uid, dbesc(PROBE));
}
cleanup($uid);

// A guest token…
$guid = new_uuid();
q("INSERT INTO atoken (atoken_aid, atoken_uid, atoken_guid, atoken_name, atoken_token, atoken_expires)
   VALUES (%d, %d, '%s', '%s', '%s', '%s')",
    intval($chan['channel_account_id']), $uid, dbesc($guid), dbesc(PROBE), dbesc('probe-secret'),
    dbesc(NULL_DATE));
$atoken = q("SELECT * FROM atoken WHERE atoken_uid = %d AND atoken_name = '%s' LIMIT 1",
    $uid, dbesc(PROBE))[0];
$guestId   = intval($atoken['atoken_id']);
$guestHash = atoken_xchan($atoken)['xchan_hash'];

// …in a privacy group…
$ghash = new_uuid();
q("INSERT INTO pgrp (uid, hash, gname, visible, deleted) VALUES (%d, '%s', '%s', 0, 0)",
    $uid, dbesc($ghash), dbesc(PROBE));
$gid = intval(q("SELECT id FROM pgrp WHERE uid = %d AND gname = '%s' LIMIT 1", $uid, dbesc(PROBE))[0]['id']);
q("INSERT INTO pgrp_member (uid, gid, xchan) VALUES (%d, %d, '%s')", $uid, $gid, dbesc($guestHash));

// …and an item private to that group and nothing else. item_store() fills in
// the two dozen NOT NULL columns a hand-written INSERT would trip over.
$mid = z_root() . '/item/' . new_uuid();
$stored = item_store([
    'uid'             => $uid,
    'mid'             => $mid,
    'parent_mid'      => $mid,
    'thr_parent'      => $mid,
    'title'           => PROBE,
    'body'            => 'probe',
    'mimetype'        => 'text/bbcode',
    'author_xchan'    => $chan['channel_hash'],
    'owner_xchan'     => $chan['channel_hash'],
    'allow_gid'       => '<' . $ghash . '>',
    'item_private'    => 1,
    'item_thread_top' => 1,
    'plink'           => $mid,
], false, false, true);
$iid = intval($stored['item_id'] ?? 0);
if (!$iid) { echo "FAIL  could not store probe item: " . json_encode($stored) . "\n"; cleanup($uid); exit(1); }
echo "item: $iid  guest: $guestId\n";

$guestIds = fn(array $r) => array_column($r['data']['guests'] ?? [], 'id');
$otherIds = fn(array $r) => array_column($r['data']['other_guests'] ?? [], 'id');

// Group membership alone makes the guest eligible — no allow_cid anywhere.
check('listed via group', in_array($guestId, $guestIds(step($nick, 'get', $iid)), true));

// The regression: allow_cid is empty, so there is nothing to strip.
step($nick, 'post', $iid, 'revoke', ['atoken_id' => $guestId]);
$row = q("SELECT allow_cid, deny_cid FROM item WHERE id = %d", $iid)[0];
check('revoke wrote deny_cid', str_contains($row['deny_cid'], '<' . $guestHash . '>'));
$after = step($nick, 'get', $iid);
check('revoked guest not listed', in_array($guestId, $guestIds($after), true), false);
check('revoked guest re-offered', in_array($guestId, $otherIds($after), true));

// And back again: granting must clear the deny it left behind.
step($nick, 'post', $iid, 'grant', ['atoken_id' => $guestId]);
$row = q("SELECT allow_cid, deny_cid FROM item WHERE id = %d", $iid)[0];
check('grant cleared deny_cid', str_contains($row['deny_cid'], $guestHash), false);
check('re-granted guest listed', in_array($guestId, $guestIds(step($nick, 'get', $iid)), true));

cleanup($uid);
echo $fail ? "\n$fail check(s) failed\n" : "\nall checks passed\n";
exit($fail ? 1 : 0);
