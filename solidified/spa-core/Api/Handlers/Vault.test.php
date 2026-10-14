<?php
if (PHP_SAPI !== 'cli') exit;   // deployed into the web root by the build; never runnable over HTTP
/**
 * Checks for Handlers\Vault — sealed per-channel credentials.
 *
 * Seals a map with a real channel's keys, then asserts the stored pconfig is
 * ciphertext only, that it opens again, that the write is staged for clone
 * sync, and puts the original value back.
 *
 *   ddev exec php core/extend/theme/utsukta-themes/solidified/spa-core/Api/Handlers/Vault.test.php
 */

for ($dir = __DIR__; $dir !== '/'; $dir = dirname($dir)) {
    if (file_exists("$dir/include/cli_startup.php")) { chdir($dir); break; }
}
require_once('include/cli_startup.php');
cli_startup();
require_once('include/crypto.php');

$base = dirname(__DIR__) . '/';
require_once($base . 'Response.php');
require_once($base . 'Auth.php');
require_once($base . 'Handlers/Vault.php');
use Utsukta\SpaCore\Api\Handlers\Vault;

$fail = 0;
function check(string $label, bool $cond): void {
    global $fail;
    if ($cond) { echo "ok    $label\n"; return; }
    $fail++; echo "FAIL  $label\n";
}

$c = q("SELECT channel_id FROM channel WHERE channel_removed = 0 AND channel_pubkey != '' LIMIT 1");
if (!$c) { echo "SKIP  no channel with keys\n"; exit(0); }
$uid = intval($c[0]['channel_id']);
$name = 'vaulttest';
$before = get_pconfig($uid, 'spa_vault', $name, null);

$secret = 'tok-' . random_string(24);
$map = ['server-1' => ['server' => 'https://media.example', 'userName' => 'me', 'anchor' => $secret]];

\App::$config[$uid]['transient'] = [];
Vault::seal($uid, $name, $map);
$raw = (string) get_pconfig($uid, 'spa_vault', $name, '');

check('stored value is sealed (no plaintext token)', $raw !== '' && !str_contains($raw, $secret));
check('stored value carries no server address either', !str_contains($raw, 'media.example'));
check('stored value is a core crypto envelope', !empty(json_decode($raw, true)['encrypted']));
check('opens back to the same map', Vault::open($uid, $name) === $map);
check('write is staged for clone sync', !empty(\App::$config[$uid]['transient']));
check('staged sync data is ciphertext only', !str_contains(json_encode(\App::$config[$uid]['transient'] ?? []), $secret));

Vault::seal($uid, $name, []);
check('an emptied vault opens as empty', Vault::open($uid, $name) === []);
check('unknown integration opens as empty', Vault::open($uid, 'no-such-integration-' . random_string(6)) === []);

set_pconfig($uid, 'spa_vault', $name, '{"not":"sealed"}');
check('a non-envelope value opens as empty', Vault::open($uid, $name) === []);

// restore
if ($before === null) del_pconfig($uid, 'spa_vault', $name);
else set_pconfig($uid, 'spa_vault', $name, $before);
\App::$config[$uid]['transient'] = [];

echo $fail ? "\n$fail check(s) FAILED\n" : "\nall passed\n";
exit($fail ? 1 : 0);
