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
session_id('hubdefaults-test'); // before any output, or local_channel() stays false
$_SESSION = [];
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
check('bad custom colours refused', HubDefaults::cleanCustomColors('{"base":"red","txt":"#000000","accent":"#000000","isDark":true}') === null);
$savedColors = Config::Get('spa', 'custom_theme_colors', null);
try {
    Config::Delete('spa', 'custom_theme_colors');
    check('custom scheme refused without colours', HubDefaults::clean('color_scheme', 'custom') === null);
    Config::Set('spa', 'custom_theme_colors', '{"base":"#101010","txt":"#eeeeee","accent":"#ff8800","isDark":true}');
    check('custom scheme ok with colours', HubDefaults::clean('color_scheme', 'custom') === 'custom');
} finally {
    $savedColors === null ? Config::Delete('spa', 'custom_theme_colors') : Config::Set('spa', 'custom_theme_colors', $savedColors);
}
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

    // Admin post: "use my scheme" copies every override; untouched pickers keep
    // them; a changed picker replaces them with a derived set.
    $savedHubColors = Config::Get('spa', 'custom_theme_colors', null);
    $savedScheme    = Config::Get('spa', 'color_scheme', null);
    $savedMine      = get_pconfig($uid, 'spa', 'custom_theme_colors', null);
    try {
        $_SESSION['authenticated'] = 1; $_SESSION['uid'] = $uid;
        \App::set_channel($ch);
        $mine = '{"base":"#1E1E2E","txt":"#cdd6f4","accent":"#cba6f7","isDark":true,"surface":"#222233"}';
        set_pconfig($uid, 'spa', 'custom_theme_colors', $mine);
        check('admin form offers copy', str_contains(theme_admin($a), 'name="custom_copy_mine" value="1" class="btn btn-sm btn-outline-secondary">'));
        $post = fn(array $p) => ($_POST = $_REQUEST = $p + ['form_security_token' => get_form_security_token('admin_themes')]) && theme_admin_post();
        $post(['custom_copy_mine' => '1', 'color_scheme' => '', 'custom_base' => '#000000']);
        check('copy stores full set', Config::Get('spa', 'custom_theme_colors') === $mine);
        check('copy selects custom', Config::Get('spa', 'color_scheme') === 'custom');
        check('hub default carries colours', (HubDefaults::get()['custom_theme_colors'] ?? null) === $mine);
        $post(['color_scheme' => 'custom', 'custom_base' => '#1e1e2e', 'custom_txt' => '#cdd6f4', 'custom_accent' => '#cba6f7', 'custom_isDark' => '1']);
        check('untouched pickers keep overrides', Config::Get('spa', 'custom_theme_colors') === $mine);
        $post(['color_scheme' => 'custom', 'custom_base' => '#ffffff', 'custom_txt' => '#000000', 'custom_accent' => '#cba6f7']);
        check('changed picker drops overrides', !str_contains(Config::Get('spa', 'custom_theme_colors'), 'surface'));
    } finally {
        $savedHubColors === null ? Config::Delete('spa', 'custom_theme_colors') : Config::Set('spa', 'custom_theme_colors', $savedHubColors);
        $savedScheme === null ? Config::Delete('spa', 'color_scheme') : Config::Set('spa', 'color_scheme', $savedScheme);
        $savedMine === null ? del_pconfig($uid, 'spa', 'custom_theme_colors') : set_pconfig($uid, 'spa', 'custom_theme_colors', $savedMine);
    }
} finally {
    $savedHub === null ? Config::Delete('spa', 'thread_mode') : Config::Set('spa', 'thread_mode', $savedHub);
    $savedUser === null ? del_pconfig($uid, 'spa', 'thread_mode') : set_pconfig($uid, 'spa', 'thread_mode', $savedUser);
}

echo $fail ? "$fail failed\n" : "all ok\n";
exit($fail ? 1 : 0);
