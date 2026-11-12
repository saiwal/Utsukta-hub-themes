<?php
if (PHP_SAPI !== 'cli') exit;   // deployed into the web root by the build; never runnable over HTTP
/**
 * ForumNotify::isRepostOfMine — the guard that decides whose forum threads a
 * channel hears about. Pure, no database.
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

$me = 'me-hash';
$share = fn(string $who, string $inner = 'hi') => ['body' => "[share author='x' profile='x' portable_id='$who' message_id='m']{$inner}[/share]"];

check('forum share of my post matches', ForumNotify::isRepostOfMine($share($me), $me));
check("forum share of someone else's post doesn't match", !ForumNotify::isRepostOfMine($share('other'), $me));
check("someone's DM quoting my post doesn't match", !ForumNotify::isRepostOfMine($share('other', $share($me)['body']), $me));
check("a body without a share doesn't match", !ForumNotify::isRepostOfMine(['body' => "portable_id='$me'"], $me));

exit($fail ? 1 : 0);
