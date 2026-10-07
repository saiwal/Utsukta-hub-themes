<?php
if (PHP_SAPI !== 'cli') exit;   // deployed into the web root by the build; never runnable over HTTP
/**
 * SpaFeatures: the SPA editor toggles land in core's "editor" group, their
 * defaults hold when unset (core's feature_enabled() would say false for
 * both), and a stored value wins. Restores the channel's pconfig after.
 *
 *   ddev exec php core/extend/theme/utsukta-themes/solidified/spa-core/Api/SpaFeatures.test.php [nick]
 */

for ($dir = __DIR__; $dir !== '/'; $dir = dirname($dir)) {
    if (file_exists("$dir/include/cli_startup.php")) { chdir($dir); break; }
}
require_once 'include/cli_startup.php';
cli_startup();
require_once __DIR__ . '/../../vendor/autoload.php';
require_once 'include/features.php';

use Utsukta\SpaCore\Api\SpaFeatures;

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

$names = array_map(fn($i) => is_array($i) ? $i[0] : null, SpaFeatures::merge(get_features(false))['editor']);
check('spa_latex in editor group', in_array('spa_latex', $names, true));
check('spa_diagrams in editor group', in_array('spa_diagrams', $names, true));
check('core editor features kept', count(array_filter($names)) > 2);

$saved = [];
foreach (['spa_latex', 'spa_diagrams'] as $n) $saved[$n] = get_pconfig($uid, 'feature', $n, null);

foreach (['spa_latex', 'spa_diagrams'] as $n) del_pconfig($uid, 'feature', $n);
check('latex on by default', SpaFeatures::enabled($uid, 'spa_latex') === true);
check('diagrams off by default', SpaFeatures::enabled($uid, 'spa_diagrams') === false);

set_pconfig($uid, 'feature', 'spa_latex', 0);
set_pconfig($uid, 'feature', 'spa_diagrams', 1);
check('stored off wins over default on', SpaFeatures::enabled($uid, 'spa_latex') === false);
check('stored on wins over default off', SpaFeatures::enabled($uid, 'spa_diagrams') === true);

check('core features still via feature_enabled', SpaFeatures::enabled($uid, 'markdown') === (bool) feature_enabled($uid, 'markdown'));

foreach ($saved as $n => $v) {
    $v === null ? del_pconfig($uid, 'feature', $n) : set_pconfig($uid, 'feature', $n, $v);
}

exit($fail ? 1 : 0);
