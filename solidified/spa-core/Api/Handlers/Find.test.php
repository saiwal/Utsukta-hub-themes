<?php
if (PHP_SAPI !== 'cli') exit;   // deployed into the web root by the build; never runnable over HTTP
/**
 * Find::visiblePath() — the ancestor walk that keeps a visitor's file search
 * from naming anything inside a folder they can't see. Pure, no database.
 *
 *   ddev exec php core/extend/theme/utsukta-themes/solidified/spa-core/Api/Handlers/Find.test.php
 */

for ($dir = __DIR__; $dir !== '/'; $dir = dirname($dir)) {
    if (file_exists("$dir/include/cli_startup.php")) { chdir($dir); break; }
}
require_once 'include/cli_startup.php';
cli_startup();
require_once __DIR__ . '/../../../vendor/autoload.php';

use Utsukta\SpaCore\Api\Handlers\Find;

$fail = 0;
function check(string $what, bool $ok): void {
    global $fail;
    echo ($ok ? 'ok   ' : 'FAIL ') . $what . "\n";
    if (!$ok) $fail++;
}

// hash => parent hash of the folders the observer may see; "secret" is absent.
$dirs = ['pub' => '', 'sub' => 'pub', 'leak' => 'secret', 'a' => 'b', 'b' => 'a'];

check('root file is visible', Find::visiblePath('', $dirs));
check('file in a visible folder', Find::visiblePath('pub', $dirs));
check('file in a nested visible folder', Find::visiblePath('sub', $dirs));
check('file in a hidden folder', !Find::visiblePath('secret', $dirs));
check('visible folder under a hidden one', !Find::visiblePath('leak', $dirs));
check('folder cycle terminates as hidden', !Find::visiblePath('a', $dirs));

exit($fail ? 1 : 0);
