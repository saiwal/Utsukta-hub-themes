<?php
if (PHP_SAPI !== 'cli') exit;   // deployed into the web root by the build; never runnable over HTTP
/**
 * Self-check for polls against core's rules (Module/Item, Module/Vote,
 * format_poll): a multiple-answer poll stores anyOf and closes comments at its
 * end time; a vote needs the voter's own copy, not being the poll's owner, and
 * an open poll; an empty answer and a reshare of a poll are refused.
 *
 * canVoteOn() is checked against synthetic rows, the real endpoints against a
 * poll created through the handler, which is deleted again.
 *
 * Run inside the Hubzilla install:
 *   ddev exec php core/extend/theme/utsukta-themes/solidified/spa-core/Api/Handlers/Poll.test.php
 */

for ($dir = __DIR__; $dir !== '/'; $dir = dirname($dir)) {
    if (file_exists("$dir/include/cli_startup.php")) { chdir($dir); break; }
}
require_once 'include/cli_startup.php';
cli_startup();
require_once dirname(__DIR__, 3) . '/vendor/autoload.php';

use Utsukta\SpaCore\Api\Handlers\Item;

$ch = q("SELECT * FROM channel LEFT JOIN xchan ON xchan_hash = channel_hash
         WHERE channel_system = 0 AND channel_removed = 0 ORDER BY channel_id LIMIT 1")[0];

function polltestLogin(array $ch): void
{
    session_id('polltest');
    $_SESSION['authenticated'] = 1;
    $_SESSION['uid'] = intval($ch['channel_id']);
    $_SESSION['solidified_csrf'] = $_SERVER['HTTP_X_CSRF_TOKEN'] = 'polltest';
    App::$channel  = $ch;
    App::$observer = $ch;
}

// ── child step: one handler POST per process (Response exits) ────────────────

if (($argv[1] ?? '') === 'post') {
    $arg = json_decode($argv[2], true);
    polltestLogin($ch);
    App::$argv = $arg['argv'];
    App::$argc = count(App::$argv);
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_SERVER['CONTENT_TYPE']   = 'multipart/form-data';
    $_POST = $arg['body'];
    (new Item())->post();
    exit;
}

function call(array $argv, array $body): array
{
    $out = shell_exec('php ' . escapeshellarg(__FILE__) . ' post '
        . escapeshellarg(json_encode(['argv' => $argv, 'body' => $body])));
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

// ── canVoteOn: core's format_poll $activated && $commentable ─────────────────

polltestLogin($ch);
$can = new ReflectionMethod(Item::class, 'canVoteOn');
$can->setAccessible(true);
$me  = $ch['channel_hash'];
$row = [
    'uid' => intval($ch['channel_id']), 'owner_xchan' => 'someone-else', 'author_xchan' => 'someone-else',
    'comment_policy' => 'public', 'comments_closed' => DBA::$dba->get_null_date(), 'item_wall' => 0,
];
check('own copy, open, not owner → can vote', $can->invoke(null, $row, $me), true);
check('poll owner → cannot', $can->invoke(null, ['owner_xchan' => $me] + $row, $me), false);
check('another channel\'s copy → cannot', $can->invoke(null, ['uid' => 0] + $row, $me), false);
check('ended (comments_closed past) → cannot',
    $can->invoke(null, ['comments_closed' => datetime_convert('UTC', 'UTC', '-1 hour')] + $row, $me), false);
check('still open (comments_closed future) → can',
    $can->invoke(null, ['comments_closed' => datetime_convert('UTC', 'UTC', '+1 hour')] + $row, $me), true);
check('no observer → cannot', $can->invoke(null, $row, ''), false);

// ── a real poll through the handler ──────────────────────────────────────────

$c = call(['spa', 'item'], [
    'body' => 'Poll test question?', 'poll_answers' => ['Red', 'Blue'],
    'poll_multiple_answers' => 1, 'poll_expire_value' => 1, 'poll_expire_unit' => 'Days',
]);
$post = $c['data']['post'] ?? [];
$iid  = intval($post['iid'] ?? 0);
check('poll created', $iid > 0, true);

$stored = q("SELECT obj, comments_closed FROM item WHERE id = %d", $iid)[0] ?? [];
$obj    = json_decode($stored['obj'] ?? '', true) ?: [];
check('multiple answers → anyOf', array_column($obj['anyOf'] ?? [], 'name'), ['Red', 'Blue']);
check('comments close at endTime', ($stored['comments_closed'] ?? '') > datetime_convert(), true);
check('formatted poll is multiple', $post['poll']['multiple'] ?? null, true);
check('owner gets can_vote = false', $post['poll']['can_vote'] ?? null, false);

$uuid = $post['uuid'] ?? '';
check('owner vote refused', call(['spa', 'item', $uuid, 'vote'], ['answer' => ['Red']])['error'] ?? null,
    'You cannot vote on this poll');
check('empty answer refused', call(['spa', 'item', $uuid, 'vote'], ['answer' => ''])['error'] ?? null,
    'answer is required');
check('reshare refused', call(['spa', 'item', $uuid, 'reshare'], ['body' => ''])['error'] ?? null,
    'Polls cannot be reshared');
check('no Answer stored', (bool) q("SELECT id FROM item WHERE parent = %d AND obj_type = 'Answer'", $iid), false);

if ($iid) {
    drop_item($iid);
}

echo $fail ? "\n$fail FAILED\n" : "\nall passed\n";
exit($fail ? 1 : 0);
