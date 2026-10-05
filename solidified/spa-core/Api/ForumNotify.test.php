<?php
if (PHP_SAPI !== 'cli') exit;   // deployed into the web root by the build; never runnable over HTTP
/**
 * ForumNotify::isRepostOfMine — the guard that decides whose forum threads a
 * channel hears about. Synthesises the forum's thread root around a real
 * local item, writes nothing.
 *
 *   ddev exec php core/extend/theme/utsukta-themes/solidified/spa-core/Api/ForumNotify.test.php
 */
for ($dir = __DIR__; $dir !== '/'; $dir = dirname($dir)) {
    if (file_exists("$dir/include/cli_startup.php")) { chdir($dir); break; }
}
require_once 'include/cli_startup.php';
cli_startup();
require_once __DIR__ . '/../../vendor/autoload.php';

use Utsukta\SpaCore\Api\ForumNotify;

$fail = 0;
function check(string $label, bool $ok): void
{
    global $fail;
    echo ($ok ? 'ok  ' : 'FAIL') . "  $label\n";
    if (!$ok) $fail++;
}

$r = q("select item.uid, item.mid, channel_hash from item join channel on channel_id = item.uid
    where item.author_xchan = channel_hash and item.item_thread_top = 1 and item.item_deleted = 0 limit 1");
if (!$r) {
    echo "skip  no local channel has a post of its own\n";
    exit(0);
}
[$uid, $mid, $me] = [intval($r[0]['uid']), $r[0]['mid'], $r[0]['channel_hash']];
$share = fn(string $m) => ['body' => "[share author='x' profile='x' message_id='$m']hi[/share]"];

check('forum share of my own post matches', ForumNotify::isRepostOfMine($share($mid), $uid, $me));
check("someone else's channel doesn't match", !ForumNotify::isRepostOfMine($share($mid), $uid, 'not-' . $me));
check('a mid the channel never wrote doesn\'t match', !ForumNotify::isRepostOfMine($share($mid . '-nope'), $uid, $me));
check('a body without a share doesn\'t match', !ForumNotify::isRepostOfMine(['body' => "message_id='$mid'"], $uid, $me));

exit($fail ? 1 : 0);
