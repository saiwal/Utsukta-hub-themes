<?php
if (PHP_SAPI !== 'cli') exit;   // deployed into the web root by the build; never runnable over HTTP
/**
 * POST /spa/item/:uuid/edit is refused unless the caller is the item's author
 * or owner, mirroring core's /editpost. Matching on uid alone accepted any row
 * in the channel's stream — every post merely delivered to it.
 *
 * Writes two rows with deliver off (one foreign, one own), edits both, then
 * deletes them again.
 *
 *   ddev exec php core/extend/theme/utsukta-themes/solidified/spa-core/Api/Handlers/Item.edit.test.php
 */

for ($dir = __DIR__; $dir !== '/'; $dir = dirname($dir)) {
    if (file_exists("$dir/include/cli_startup.php")) { chdir($dir); break; }
}
require_once 'include/cli_startup.php';
cli_startup();
require_once dirname(__DIR__, 3) . '/vendor/autoload.php';

// ── Step mode: one edit as channel $uid ────────────────────────────────────
if (($argv[1] ?? '') === 'step') {
    [, , $uid, $uuid] = $argv;
    $ch = channelx_by_n(intval($uid));
    $x  = q("SELECT * FROM xchan WHERE xchan_hash = '%s'", dbesc($ch['channel_hash']));

    @session_start();
    $_SESSION['uid']              = intval($ch['channel_id']);
    $_SESSION['account_id']       = intval($ch['channel_account_id']);
    $_SESSION['authenticated']    = 1;
    $_SESSION['solidified_csrf']  = 'test-token';
    $_SERVER['HTTP_X_CSRF_TOKEN'] = 'test-token';
    $_SERVER['CONTENT_TYPE']      = 'multipart/form-data';
    $_POST                        = ['body' => 'edited by test', 'mimetype' => 'text/bbcode'];

    App::$channel  = $ch;
    App::$observer = $x[0];
    App::$argv     = ['spa', 'item', $uuid, 'edit'];
    App::$argc     = 4;

    (new \Utsukta\SpaCore\Api\Handlers\Item())->post();
    exit;
}

// ── Driver ─────────────────────────────────────────────────────────────────
// $me must have no connections so the allowed edit federates nowhere.
$me = q("SELECT * FROM channel c WHERE channel_removed = 0 AND channel_system = 0
         AND (SELECT COUNT(*) FROM abook a WHERE a.abook_channel = c.channel_id AND a.abook_self = 0) = 0
         ORDER BY channel_id LIMIT 1");
$other = $me ? q("SELECT * FROM channel WHERE channel_removed = 0 AND channel_id != %d LIMIT 1",
                 intval($me[0]['channel_id'])) : [];
if (!$me || !$other) { echo "SKIP  need a connection-free channel and a second channel\n"; exit(0); }
$me = $me[0]; $other = $other[0];

$fail = 0;
$check = function (bool $cond, string $label, string $detail = '') use (&$fail) {
    echo ($cond ? 'ok    ' : 'FAIL  ') . $label . "\n" . ($cond ? '' : $detail);
    if (!$cond) $fail++;
};

$store = function (string $author) use ($me): array {
    $uuid = new_uuid();
    $mid  = z_root() . '/item/' . $uuid;
    $x = item_store([
        'aid' => $me['channel_account_id'], 'uid' => $me['channel_id'],
        'uuid' => $uuid, 'mid' => $mid, 'parent_mid' => $mid, 'thr_parent' => $mid,
        'author_xchan' => $author, 'owner_xchan' => $author,
        'item_wall' => 0, 'item_origin' => 0, 'item_thread_top' => 1,
        'verb' => 'Create', 'obj_type' => 'Note', 'body' => 'original',
    ], deliver: false, addAndSync: false);
    return [$uuid, intval($x['item_id'])];
};

$edit = fn(string $uuid): array => json_decode((string) shell_exec(
    escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__FILE__) . ' step '
    . intval($me['channel_id']) . ' ' . escapeshellarg($uuid) . ' 2>&1'), true) ?: [];

$bodyOf = fn(int $id): string => q("SELECT body FROM item WHERE id = %d", $id)[0]['body'] ?? '';

[$foreignUuid, $foreignId] = $store($other['channel_hash']);
[$ownUuid, $ownId]         = $store($me['channel_hash']);
try {
    $r = $edit($foreignUuid);
    $check(($r['error']['status'] ?? 0) === 403, 'editing a delivered post by someone else is refused',
        '      ' . json_encode($r) . "\n");
    $check($bodyOf($foreignId) === 'original', 'the refused post is unchanged');

    $r = $edit($ownUuid);
    $check(!empty($r['data']['success']), 'editing your own post still works', '      ' . json_encode($r) . "\n");
    $check($bodyOf($ownId) === 'edited by test', 'the own post carries the new body');
} finally {
    foreach ([$foreignId, $ownId] as $id) {
        q("DELETE FROM term WHERE oid = %d AND otype = %d", $id, TERM_OBJ_POST);
        q("DELETE FROM iconfig WHERE iid = %d", $id);
        q("DELETE FROM item WHERE id = %d", $id);
    }
}
exit($fail ? 1 : 0);
