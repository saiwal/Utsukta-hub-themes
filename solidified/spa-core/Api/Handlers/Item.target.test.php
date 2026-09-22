<?php
if (PHP_SAPI !== 'cli') exit;   // deployed into the web root by the build; never runnable over HTTP
/**
 * Check for the conversation Collection (item.target / item.tgt_type) that
 * Item::conversationTarget puts on a poll answer.
 *
 * Posts, comments and reshares no longer come through here at all — they go
 * through core's Zotlabs\Module\Item, which sets the target itself; see
 * src/docs/dev/en/core-parity.md. conversationTarget() remains for the
 * poll-vote path, and these cases pin its contract.
 *
 * Why it matters: Activity::store() and Libzot::process_delivery() both *drop* a
 * relayed comment whose parent carries a Collection target unless the receiving
 * channel owns the conversation — follower copies are meant to wait for the
 * owner's canonical relay. An item stored without one has its replies accepted
 * out of band instead, and each remote hub ends up with its own partial version
 * of the thread. The SPA used to store none at all.
 *
 * The trap this pins: the SPA lets you reply to a *comment* (core only ever
 * replies to the thread root), so the collection id has to come from the
 * parent's parent_mid, never its mid, or every nested reply opens a new
 * conversation.
 *
 * Nothing is written to the database — the parent rows are synthesised.
 *
 * Run inside the Hubzilla install:
 *   ddev exec php core/extend/theme/utsukta-themes/solidified/spa-core/Api/Handlers/Item.target.test.php
 */

for ($dir = __DIR__; $dir !== '/'; $dir = dirname($dir)) {
    if (file_exists("$dir/include/cli_startup.php")) { chdir($dir); break; }
}
require_once('include/cli_startup.php');
cli_startup();

$fail = 0;
function check(string $label, bool $cond): void {
    global $fail;
    if ($cond) { echo "ok    $label\n"; return; }
    $fail++; echo "FAIL  $label\n";
}

$c = q("SELECT * FROM channel WHERE channel_removed = 0 AND channel_system = 0 ORDER BY channel_id LIMIT 1");
if (!$c) { echo "SKIP  no channel in this database\n"; exit(0); }
$channel = $c[0];
$x = q("SELECT * FROM xchan WHERE xchan_hash = '%s' LIMIT 1", dbesc($channel['channel_hash']));

@session_start();
$_SESSION['authenticated'] = 1;
$_SESSION['uid'] = intval($channel['channel_id']);
App::$channel  = $channel;
App::$observer = $x[0];

$autoload = dirname(__DIR__, 3) . '/vendor/autoload.php';
if (!file_exists($autoload)) {
    fwrite(STDERR, "run this from the deployed theme (expected $autoload)\n");
    exit(2);
}
require_once($autoload);

$cls     = \Utsukta\SpaCore\Api\Handlers\Item::class;
$target  = new ReflectionMethod($cls, 'conversationTarget');
$target->setAccessible(true);

$uid       = intval($channel['channel_id']);
$mine      = $channel['channel_hash'];
$rootMid   = z_root() . '/item/' . item_message_id();
$newMid    = z_root() . '/item/' . item_message_id();
$conv      = fn(string $mid) => str_replace('/item/', '/conversation/', $mid);
$chanUrl   = z_root() . '/channel/' . $channel['channel_address'];

// 1. A new thread of my own.
$t = $target->invoke(null, $uid, $mine, $newMid);
check('new thread is a Collection',        ($t['tgt_type'] ?? '') === 'Collection');
check('new thread collection is its own',  ($t['target']['id'] ?? '') === $conv($newMid));
check('new thread attributedTo is me',     ($t['target']['attributedTo'] ?? '') === $chanUrl);

// 2. A comment on the root of my own thread.
$root = ['mid' => $rootMid, 'parent_mid' => $rootMid, 'owner_xchan' => $mine, 'target' => '', 'tgt_type' => ''];
$t = $target->invoke(null, $uid, $mine, $newMid, $root);
check('comment joins the root collection', ($t['target']['id'] ?? '') === $conv($rootMid));

// 3. The regression guard: a reply to a *comment* must join the root's
//    collection, not open one of its own. Only the SPA can produce this.
$comment = ['mid' => z_root() . '/item/' . item_message_id(), 'parent_mid' => $rootMid,
            'owner_xchan' => $mine, 'target' => '', 'tgt_type' => ''];
$t = $target->invoke(null, $uid, $mine, $newMid, $comment);
check('nested reply joins the ROOT collection, not the comment',
    ($t['target']['id'] ?? '') === $conv($rootMid));

// 4. Someone else's conversation: carry their collection through verbatim, and
//    never mint one in their name.
$theirs = json_encode(['id' => 'https://remote.example/conversation/abc', 'type' => 'Collection',
                       'attributedTo' => 'https://remote.example/channel/them']);
$remote = ['mid' => 'https://remote.example/item/abc', 'parent_mid' => 'https://remote.example/item/abc',
           'owner_xchan' => 'remote-hash', 'target' => $theirs, 'tgt_type' => 'Collection'];
// buildItemArray passes the *parent's* owner hash for a comment, which for a
// received thread is the remote owner — so both calls below use that.
$t = $target->invoke(null, $uid, $remote['owner_xchan'], $newMid, $remote);
check('remote thread target passed through', $t['target'] === $theirs && $t['tgt_type'] === 'Collection');

$remote['target'] = $remote['tgt_type'] = '';
$t = $target->invoke(null, $uid, $remote['owner_xchan'], $newMid, $remote);
check('remote thread without one stays empty', $t['target'] === '' && $t['tgt_type'] === '');

// 5. buildItemArray() is gone: posts, comments and reshares go through core's
//    Zotlabs\Module\Item now, which sets the target itself. parity.test.php
//    asserts the resulting thread shape (root / reply / nested reply) end to
//    end, so there is nothing left to reflect on here. conversationTarget()
//    survives for the poll-vote path, which is what the cases above cover.

// 6. The wire: prove the column is what puts `target` in the delivered activity.
$r = q("SELECT * FROM item WHERE item_origin = 1 AND item_deleted = 0 AND tgt_type = 'Collection'
        AND uid = %d ORDER BY id DESC LIMIT 1", intval($uid));
if (!$r) {
    echo "SKIP  no item with a Collection target to encode\n";
} else {
    $rows = q("SELECT * FROM item WHERE id = %d", intval($r[0]['id']));
    xchan_query($rows, true);
    $rows = fetch_post_tags($rows, true);
    $e = \Zotlabs\Lib\Activity::encode_item($rows[0]);
    check('encode_item emits target for a Collection item', !empty($e['target']['id']));

}

// A real target-less item (one the SPA stored before this fix) must encode
// without one — blanking the column on a row that has it proves nothing, since
// item.obj still carries the target it was built with.
$r = q("SELECT * FROM item WHERE item_origin = 1 AND item_deleted = 0 AND tgt_type = ''
        AND uid = %d ORDER BY id DESC LIMIT 1", intval($uid));
if (!$r) {
    echo "SKIP  no target-less item left to compare against\n";
} else {
    $rows = q("SELECT * FROM item WHERE id = %d", intval($r[0]['id']));
    xchan_query($rows, true);
    $rows = fetch_post_tags($rows, true);
    $e = \Zotlabs\Lib\Activity::encode_item($rows[0]);
    check('encode_item emits no target without the column', empty($e['target']));
}

echo $fail ? "\n$fail check(s) FAILED\n" : "\nall passed\n";
exit($fail ? 1 : 0);
