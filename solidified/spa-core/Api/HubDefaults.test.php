<?php
if (PHP_SAPI !== 'cli') exit;   // deployed into the web root by the build; never runnable over HTTP
/**
 * HubDefaults: values are validated, and a pref resolves user > hub > built-in.
 * Also renders the theme's /admin/themes form. Restores config/pconfig after.
 *
 *   ddev exec php core/extend/theme/utsukta-themes/solidified/spa-core/Api/HubDefaults.test.php [nick]
 */

for ($dir = __DIR__; $dir !== '/'; $dir = dirname($dir)) {
    if (file_exists("$dir/include/cli_startup.php")) { chdir($dir); break; }
}
require_once 'include/cli_startup.php';
cli_startup();
require_once __DIR__ . '/../../vendor/autoload.php';

use Utsukta\SpaCore\Api\HubDefaults;
use Zotlabs\Lib\Config;

$fail = 0;
function check(string $what, bool $ok): void {
    global $fail;
    echo ($ok ? 'ok   ' : 'FAIL ') . $what . "\n";
    if (!$ok) $fail++;
}

$nick = $argv[1] ?? null;
$ch = $nick ? channelx_by_nick($nick) : (q("SELECT * FROM channel WHERE channel_removed = 0 LIMIT 1")[0] ?? null);
if (!$ch) { echo "no channel\n"; exit(1); }
$uid = (int) $ch['channel_id'];

check('scheme valid', HubDefaults::clean('color_scheme', 'nord') === 'nord');
check('custom scheme refused', HubDefaults::clean('color_scheme', 'custom') === null);
check('bad choice refused', HubDefaults::clean('font_size', 'huge') === null);
check('post_height clamped', HubDefaults::clean('post_height', '20') === '100');
check('post_height 0 kept', HubDefaults::clean('post_height', '0') === '0');
check('javascript: url refused', HubDefaults::clean('bg_url', 'javascript:alert(1)') === null);
check('protocol-relative url refused', HubDefaults::clean('bg_url', '//evil.example/x.png') === null);
check('relative url ok', HubDefaults::clean('bg_url', '/img/bg.jpg') === '/img/bg.jpg');

$savedHub  = Config::Get('spa', 'thread_mode', null);
$savedUser = get_pconfig($uid, 'spa', 'thread_mode', null);
try {
    Config::Set('spa', 'thread_mode', '');
    del_pconfig($uid, 'spa', 'thread_mode');
    check('built-in when nothing set', HubDefaults::pref($uid, 'thread_mode') === 'threaded');
    Config::Set('spa', 'thread_mode', 'flat');
    check('hub default when user unset', HubDefaults::pref($uid, 'thread_mode') === 'flat');
    check('hub default listed', (HubDefaults::get()['thread_mode'] ?? null) === 'flat');
    set_pconfig($uid, 'spa', 'thread_mode', 'threaded');
    check('user pref wins', HubDefaults::pref($uid, 'thread_mode') === 'threaded');

    require_once 'view/theme/solidified/php/config.php';
    $form = theme_admin($a);
    check('admin form renders every key', count(array_filter(array_keys(HubDefaults::CHOICES), fn($k) => str_contains($form, "name=\"$k\""))) === count(HubDefaults::CHOICES));
    check('admin form shows stored value', str_contains($form, 'value="flat" selected'));
} finally {
    $savedHub === null ? Config::Delete('spa', 'thread_mode') : Config::Set('spa', 'thread_mode', $savedHub);
    $savedUser === null ? del_pconfig($uid, 'spa', 'thread_mode') : set_pconfig($uid, 'spa', 'thread_mode', $savedUser);
}

echo $fail ? "$fail failed\n" : "all ok\n";
exit($fail ? 1 : 0);
