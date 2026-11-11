<?php
if (PHP_SAPI !== 'cli') exit;   // deployed into the web root by the build; never runnable over HTTP
/**
 * Checks for Handlers\Vault — sealed per-channel credentials.
 *
 * Seals entries with a real channel's keys, then asserts the stored pconfig is
 * ciphertext only, one row per entry, that it opens again, that writes and
 * deletes are staged for clone sync, that a legacy map row is split up, and
 * removes its rows again.
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
// Load first: resetting 'transient' below creates App::$config[$uid], and core
// then never loads the channel's rows — every write becomes a blind INSERT.
load_pconfig($uid);
$wipe = function () use ($uid, $name) {
    q("DELETE FROM pconfig WHERE uid = %d AND cat = 'spa_vault' AND (k = '%s' OR k LIKE '%s')", $uid, dbesc($name), dbesc($name) . ':%');
    unset(\App::$config[$uid]['spa_vault']);
};
if (q("SELECT k FROM pconfig WHERE uid = %d AND cat = 'spa_vault' AND (k = '%s' OR k LIKE '%s')", $uid, dbesc($name), dbesc($name) . ':%')) {
    echo "SKIP  '$name' rows already exist\n"; exit(0);
}

$secret = 'tok-' . random_string(24);
$entry = ['server' => 'https://media.example', 'userName' => 'me', 'anchor' => $secret];

\App::$config[$uid]['transient'] = [];
Vault::put($uid, $name, 'server-1', $entry);
$raw = (string) get_pconfig($uid, 'spa_vault', "$name:server-1", '');

check('stored value is sealed (no plaintext token)', $raw !== '' && !str_contains($raw, $secret));
check('stored value carries no server address either', !str_contains($raw, 'media.example'));
check('stored value is a core crypto envelope', !empty(json_decode($raw, true)['encrypted']));
check('opens back to the same map', Vault::open($uid, $name) === ['server-1' => $entry]);
check('write is staged for clone sync', !empty(\App::$config[$uid]['transient']['spa_vault']["$name:server-1"]));
check('staged sync data is ciphertext only', !str_contains(json_encode(\App::$config[$uid]['transient'] ?? []), $secret));

// One row per entry: a second entry is its own pconfig row, so clone sync can't lose either
Vault::put($uid, $name, 'server-2', ['anchor' => 'other']);
check('each entry is its own row', count(q("SELECT k FROM pconfig WHERE uid = %d AND cat = 'spa_vault' AND k LIKE '%s'", $uid, dbesc($name) . ':%')) === 2);
check('both entries open', array_keys(Vault::open($uid, $name)) === ['server-1', 'server-2']);

set_pconfig($uid, 'spa_vault', "$name:broken", '{"not":"sealed"}');
check('a non-envelope entry makes the vault unopenable, not empty', Vault::open($uid, $name) === null);

\App::$config[$uid]['transient'] = [];
Vault::clear($uid, $name);
check('clear empties even an unopenable vault', Vault::open($uid, $name) === []);
check('clear is staged for clone sync as deletes', count(\App::$config[$uid]['transient']['hz_delpconfig'] ?? []) >= 3);
check('unknown integration opens as empty', Vault::open($uid, 'no-such-integration-' . random_string(6)) === []);

// Legacy layout: the whole map in one row keyed by the integration name
$ch = channelx_by_n($uid);
set_pconfig($uid, 'spa_vault', $name, json_encode(crypto_encapsulate(json_encode(['old-1' => $entry]), $ch['channel_pubkey'])));
check('a legacy map row opens', Vault::open($uid, $name) === ['old-1' => $entry]);
check('…and was split into per-entry rows', get_pconfig($uid, 'spa_vault', $name, '') === '' && get_pconfig($uid, 'spa_vault', "$name:old-1", '') !== '');

set_pconfig($uid, 'spa_vault', $name, '{"not":"sealed"}');
check('an unopenable legacy row is unopenable, not empty', Vault::open($uid, $name) === null);

// restore: rows, and the delete markers core staged for clones
$wipe();
foreach (['', ':server-1', ':server-2', ':broken', ':old-1'] as $k) {
    q("DELETE FROM pconfig WHERE uid = %d AND cat = 'hz_delpconfig' AND k = '%s'", $uid, dbesc(gen_link_id("spa_vault:$name$k")));
}
\App::$config[$uid]['transient'] = [];

echo $fail ? "\n$fail check(s) FAILED\n" : "\nall passed\n";
exit($fail ? 1 : 0);
