<?php
if (PHP_SAPI !== 'cli') exit;   // deployed into the web root by the build; never runnable over HTTP
/**
 * Guard for Router.php's clone-sync shutdown hook.
 *
 * The hook fires build_sync_packet() only when a request actually wrote
 * pconfig, and it detects that through App::$config[$uid]['transient'] —
 * PConfig::Set()'s own record of what it changed this request. If core ever
 * stops staging writes there, every settings save silently stops reaching
 * clone hubs again, with nothing failing. This pins that premise.
 *
 *   ddev exec php core/extend/theme/utsukta-themes/solidified/spa-core/Api/CloneSync.test.php
 */

for ($dir = __DIR__; $dir !== '/'; $dir = dirname($dir)) {
    if (file_exists("$dir/include/cli_startup.php")) { chdir($dir); break; }
}
require_once 'include/cli_startup.php';
cli_startup();

$c = q("select channel_id from channel where channel_removed = 0 and channel_system = 0 order by channel_id limit 1");
if (!$c) { echo "usage: needs at least one local channel\n"; exit(2); }
$uid = intval($c[0]['channel_id']);

$was = get_pconfig($uid, 'spa', 'clonesync_probe');
load_pconfig($uid);                                  // a fresh request: nothing staged yet
unset(\App::$config[$uid]['transient']);

$fail = 0;
$check = function (string $what, bool $ok) use (&$fail) {
    echo ($ok ? 'ok   ' : 'FAIL ') . $what . "\n";
    if (!$ok) $fail++;
};

$check('quiet request stages nothing', empty(\App::$config[$uid]['transient']));

set_pconfig($uid, 'spa', 'clonesync_probe', (string) mt_rand());
$check('pconfig write is staged for sync',
    !empty(\App::$config[$uid]['transient']['spa']['clonesync_probe']));

if ($was === false) del_pconfig($uid, 'spa', 'clonesync_probe');
else set_pconfig($uid, 'spa', 'clonesync_probe', $was);

echo $fail ? "$fail failed\n" : "all ok\n";
exit($fail ? 1 : 0);
