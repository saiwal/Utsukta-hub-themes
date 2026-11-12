<?php
if (PHP_SAPI !== 'cli') exit;   // deployed into the web root by the build; never runnable over HTTP
/**
 * Round-trip check for Handlers/ChatFed over real HTTP, on one hub: two local
 * channels stand in for "owner's hub" and "subscriber's hub" — the handler
 * never assumes the other side is remote, so the signatures, owner check,
 * bookmark gate and throttle are all exercised for real.
 *
 * Creates a throwaway public room and a chat bookmark, removes both again.
 *
 *   ddev exec php core/extend/theme/utsukta-themes/solidified/spa-core/Api/Handlers/ChatFed.test.php [owner] [subscriber]
 */

for ($dir = __DIR__; $dir !== '/'; $dir = dirname($dir)) {
    if (file_exists("$dir/include/cli_startup.php")) { chdir($dir); break; }
}
require_once 'include/cli_startup.php';
cli_startup();
require_once __DIR__ . '/../../../vendor/autoload.php';
require_once 'include/bookmarks.php';
require_once 'include/menu.php';
session_id('chatfed-test'); // before any output, or PHP refuses it; see the bookmark step

use Utsukta\SpaCore\Api\Handlers\ChatFed;

$fail = 0;
function check(string $what, bool $ok): void {
    global $fail;
    echo ($ok ? 'ok   ' : 'FAIL ') . $what . "\n";
    if (!$ok) $fail++;
}
$call = fn(string $m, ...$a) => (new ReflectionMethod(ChatFed::class, $m))->invoke(null, ...$a);

// ── parseRoomUrl ────────────────────────────────────────────────────────────
$p = ChatFed::parseRoomUrl('https://Hub.Example/chat/bob/7/?zid=x@y');
check('parseRoomUrl canonicalises host, slash and query', $p && $p['url'] === 'https://hub.example/chat/bob/7');
check('parseRoomUrl keeps a port', ChatFed::parseRoomUrl('https://h:8443/chat/b/1')['host'] === 'h:8443');
check('parseRoomUrl rejects the room list', ChatFed::parseRoomUrl('https://h/chat/bob') === null);
check('parseRoomUrl rejects non-http', ChatFed::parseRoomUrl('ftp://h/chat/b/1') === null);

// ── fixtures ────────────────────────────────────────────────────────────────
$owner = channelx_by_nick($argv[1] ?? 'aristotle');
$sub   = channelx_by_nick($argv[2] ?? 'plato');
if (!$owner || !$sub) { fwrite(STDERR, "usage: ChatFed.test.php <owner nick> <subscriber nick>\n"); exit(2); }
$oid = intval($owner['channel_id']);
$sid = intval($sub['channel_id']);

// A run killed by a fatal skips `finally`; clear what it left behind.
foreach (q("SELECT cr_id FROM chatroom WHERE cr_uid = %d AND cr_name = 'chatfed-probe'", $oid) ?: [] as $old) {
    q("DELETE FROM chat WHERE chat_room = %d", intval($old['cr_id']));
    q("DELETE FROM chatroom WHERE cr_id = %d", intval($old['cr_id']));
    q("DELETE FROM pconfig WHERE uid = %d AND cat = 'spa_chat_subs' AND k LIKE '%s'", $oid, dbesc($old['cr_id'] . '.%'));
}

q("INSERT INTO chatroom (cr_aid, cr_uid, cr_name, cr_created, cr_edited, cr_expire, allow_cid, allow_gid, deny_cid, deny_gid)
   VALUES (%d, %d, 'chatfed-probe', '%s', '%s', 0, '', '', '', '')",
    intval($owner['channel_account_id']), $oid, datetime_convert(), datetime_convert());
$room = q("SELECT cr_id FROM chatroom WHERE cr_uid = %d AND cr_name = 'chatfed-probe' ORDER BY cr_id DESC LIMIT 1", $oid);
$rid  = intval($room[0]['cr_id']);
$url  = z_root() . '/chat/' . $owner['channel_address'] . '/' . $rid;
$k    = md5($url);

$base = z_root() . '/spa/chatfed/';
$post = fn(array $as, string $route, array $body) => $call('signedPost', $as, $base . $route, $body);

try {
    // ── subscribe ───────────────────────────────────────────────────────────
    $x = z_post_url($base . 'subscribe', json_encode(['room_url' => $url]));
    check('unsigned subscribe is 401', intval($x['return_code']) === 401);

    check('signed subscribe is 200', $post($sub, 'subscribe', ['room_url' => $url]) === 200);
    PConfig_reload($oid);
    check('subscriber stored on the owner', get_pconfig($oid, 'spa_chat_subs', $rid . '.' . md5($sub['channel_hash'])) === $sub['channel_hash']);
    check('subscribe to a missing room is 404', $post($sub, 'subscribe', ['room_url' => z_root() . '/chat/' . $owner['channel_address'] . '/999999']) === 404);

    // ── notice ──────────────────────────────────────────────────────────────
    $n = ['room_url' => $url, 'created' => '2026-01-02 03:04:05', 'recipient' => $sub['channel_hash']];
    check('notice signed by a non-owner is 403', $post($sub, 'notice', $n) === 403);
    check('notice for an unbookmarked room is 404', $post($owner, 'notice', $n) === 404);

    // menu_add_item() only builds its ACL from App::get_channel() when
    // local_channel() is the owner, so log in as the subscriber for this call.
    $_SESSION['uid'] = $sid;
    $_SESSION['authenticated'] = 1;
    App::$channel = $sub;
    bookmark_add($sub, $sub, ['url' => $url . '?zid=probe', 'term' => 'chatfed-probe'], 0, ['ischat' => 1]);
    check('notice for a bookmarked room is 200', $post($owner, 'notice', $n) === 200);
    PConfig_reload($sid);
    check('last_other stored', get_pconfig($sid, 'spa_chat_last', $k) === '2026-01-02 03:04:05');

    $post($owner, 'notice', ['created' => '2025-01-01 00:00:00'] + $n);
    PConfig_reload($sid);
    check('an older notice does not move it back', get_pconfig($sid, 'spa_chat_last', $k) === '2026-01-02 03:04:05');

    $post($owner, 'notice', ['created' => '2099-01-01 00:00:00'] + $n);
    PConfig_reload($sid);
    check('a future time is clamped to now', get_pconfig($sid, 'spa_chat_last', $k) < '2099-01-01 00:00:00');

    // ── Web Push opt-in ─────────────────────────────────────────────────────
    check('setPush refuses a room on this hub', !ChatFed::setPush($sid, $url, true));
    $remoteUrl = 'https://chatfed-probe.example/chat/bob/7';
    check('setPush on for a remote room', ChatFed::setPush($sid, $remoteUrl, true));
    $state = ChatFed::remoteRoomState($sid, [$remoteUrl . '?zid=x']);
    check('remoteRoomState reports push', ($state[$remoteUrl . '?zid=x']['push'] ?? null) === true);
    ChatFed::setPush($sid, $remoteUrl, false);
    $state = ChatFed::remoteRoomState($sid, [$remoteUrl]);
    check('setPush off clears it', ($state[$remoteUrl]['push'] ?? null) === false);

    $GLOBALS['pushed'] = null;
    App::$hooks['spa_webpush'] = [['', 'chatfed_test_capture']];
    $call('push', $sub, ChatFed::parseRoomUrl($url), 'My room', '<b>Bob</b>');
    $pd = $GLOBALS['pushed'];
    check('push hook gets the recipient uid', ($pd['uid'] ?? 0) === $sid);
    check('push title is the bookmark title', ($pd['xname'] ?? '') === 'My room');
    check('push body escapes the remote sender name', str_contains($pd['msg'] ?? '', '&lt;b&gt;Bob'));
    check('push link is the room, zid-ed for the recipient', str_starts_with($pd['link'] ?? '', $url) && str_contains($pd['link'], 'zid='));
    check('push tag collapses per room', ($pd['hash'] ?? '') === 'chat:' . md5($url));

    // ── the daemon job: owner's hub sends, throttle holds the second one ────
    del_pconfig($sid, 'spa_chat_last', $k);
    q("INSERT INTO chat (chat_room, chat_xchan, created, chat_text) VALUES (%d, '%s', '%s', '')",
        $rid, dbesc($owner['channel_hash']), dbesc(datetime_convert()));
    ChatFed::onDaemon(['Addon', 'spa_chatfed', 'notice', $rid, $owner['channel_hash']]);
    PConfig_reload($sid);
    $first = get_pconfig($sid, 'spa_chat_last', $k);
    check('daemon delivered a notice', (bool)$first);

    del_pconfig($sid, 'spa_chat_last', $k);
    ChatFed::onDaemon(['Addon', 'spa_chatfed', 'notice', $rid, $owner['channel_hash']]);
    PConfig_reload($sid);
    check('second notice within the window is throttled', !get_pconfig($sid, 'spa_chat_last', $k));

    // ── unbookmarking drops the subscription on the next send ───────────────
    q("DELETE FROM cache WHERE k = '%s'", dbesc(uuid_from_url("spa_chatfed:$rid:{$sub['channel_hash']}")));
    q("DELETE FROM menu_item WHERE mitem_channel_id = %d AND mitem_desc = 'chatfed-probe'", $sid);
    ChatFed::onDaemon(['Addon', 'spa_chatfed', 'notice', $rid, $owner['channel_hash']]);
    PConfig_reload($oid);
    check('404 from the subscriber drops the subscription', !get_pconfig($oid, 'spa_chat_subs', $rid . '.' . md5($sub['channel_hash'])));
} finally {
    q("DELETE FROM chat WHERE chat_room = %d", $rid);
    q("DELETE FROM chatroom WHERE cr_id = %d", $rid);
    q("DELETE FROM menu_item WHERE mitem_channel_id = %d AND mitem_desc = 'chatfed-probe'", $sid);
    q("DELETE FROM pconfig WHERE uid = %d AND cat = 'spa_chat_subs' AND k LIKE '%s'", $oid, dbesc($rid . '.%'));
    q("DELETE FROM pconfig WHERE uid = %d AND cat IN ('spa_chat_last', 'spa_chat_subscribed', 'spa_chat_push') AND k IN ('%s', '%s')",
        $sid, dbesc($k), dbesc(md5('https://chatfed-probe.example/chat/bob/7')));
    q("DELETE FROM cache WHERE k = '%s'", dbesc(uuid_from_url("spa_chatfed:$rid:{$sub['channel_hash']}")));
}

function chatfed_test_capture(&$arr): void { $GLOBALS['pushed'] = $arr; }

/** Values were written by another process (the HTTP request); drop the cache. */
function PConfig_reload(int $uid): void {
    unset(App::$config[$uid]);
}

echo $fail ? "$fail failed\n" : "all checks passed\n";
exit($fail ? 1 : 0);
