<?php
if (PHP_SAPI !== 'cli') exit;   // deployed into the web root by the build; never runnable over HTTP
/**
 * SpaTheme: with two spa-core themes enabled exactly one holds /spa and the
 * shared hooks, a non-owner's enable/disable changes nothing, and disabling
 * the owner hands both to the other. Uses a throwaway view/theme/<tmp> and
 * restores routes, hooks and allowed_themes after.
 *
 *   ddev exec php core/extend/theme/utsukta-themes/solidified/spa-core/Api/SpaTheme.test.php
 */

for ($dir = __DIR__; $dir !== '/'; $dir = dirname($dir)) {
    if (file_exists("$dir/include/cli_startup.php")) { chdir($dir); break; }
}
require_once 'include/cli_startup.php';
cli_startup();
require_once __DIR__ . '/../../vendor/autoload.php';

use Utsukta\SpaCore\Api\SpaTheme;
use Zotlabs\Extend\Route;
use Zotlabs\Lib\Config;

$fail = 0;
function check(string $what, bool $ok): void {
    global $fail;
    echo ($ok ? 'ok   ' : 'FAIL ') . $what . "\n";
    if (!$ok) $fail++;
}
function spaRoutes(): int {
    return count(array_filter(Route::get(), fn($r) => is_array($r) && $r[1] === 'spa'));
}
function hookRows(string $t): int {
    return count(q("SELECT id FROM hook WHERE file LIKE '%s'", dbesc("view/theme/$t/hooks/%")) ?: []);
}

$me  = basename(dirname(__DIR__, 2));   // this theme's slug, without naming it
$tmp = 'zzspatest';
@mkdir("view/theme/$tmp/mod", 0777, true);
touch("view/theme/$tmp/mod/spa.php");

$routes  = Route::get();
$allowed = Config::Get('system', 'allowed_themes', '');

try {
    SpaTheme::enable($me);
    $n = hookRows($me);
    check("$me owns /spa", SpaTheme::owner() === $me && spaRoutes() === 1);
    check("$me has its hooks ($n)", $n === 8);

    Config::Set('system', 'allowed_themes', "$allowed,$tmp");
    SpaTheme::enable($tmp);
    check('second enable leaves owner', SpaTheme::owner() === $me && spaRoutes() === 1 && hookRows($tmp) === 0);

    SpaTheme::disable($me);
    check('owner disable hands over route', SpaTheme::owner() === $tmp && spaRoutes() === 1);
    check('…and hooks', hookRows($me) === 0 && hookRows($tmp) === $n);

    SpaTheme::enable($me);
    check('re-enable of non-owner is a no-op', SpaTheme::owner() === $tmp && hookRows($me) === 0);

    SpaTheme::disable($tmp);
    check('hands back on the other disable', SpaTheme::owner() === $me && hookRows($me) === $n && hookRows($tmp) === 0);

    // Owner vanished from disk without a disable: next enable takes over.
    Config::Set('system', 'allowed_themes', $allowed);
    SpaTheme::disable($me);
    SpaTheme::enable($tmp);
    unlink("view/theme/$tmp/mod/spa.php");
    SpaTheme::enable($me);
    check('dead owner is replaced', SpaTheme::owner() === $me && spaRoutes() === 1 && hookRows($tmp) === 0);
} finally {
    q("DELETE FROM hook WHERE file LIKE '%s'", dbesc("view/theme/$tmp/%"));
    Config::Set('system', 'routes', $routes);
    Config::Set('system', 'allowed_themes', $allowed);
    SpaTheme::enable($me);
    @unlink("view/theme/$tmp/mod/spa.php");
    @rmdir("view/theme/$tmp/mod");
    @rmdir("view/theme/$tmp");
}

echo $fail ? "$fail failed\n" : "all ok\n";
exit($fail ? 1 : 0);
