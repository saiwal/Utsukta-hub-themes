<?php
if (PHP_SAPI !== 'cli') exit;   // deployed into the web root by the build; never runnable over HTTP
/**
 * Check for Concerns/CartFulfillment — what a cart access product grants.
 *
 * The property that matters is the asymmetry the addon lacks: a cancel undoes
 * only what the purchase itself changed. So this adds a real buyer to a real
 * (temporary) privacy group through applyGrants(), revokes it, and then
 * repeats with a buyer who was a member beforehand and must still be one
 * afterwards. The group is removed again at the end. Connections are not
 * exercised: Connect::connect() needs a reachable remote hub.
 *
 * Run inside the Hubzilla install:
 *   ddev exec php core/extend/theme/utsukta-themes/solidified/spa-core/Api/Concerns/CartFulfillment.test.php
 */

for ($dir = __DIR__; $dir !== '/'; $dir = dirname($dir)) {
    if (file_exists("$dir/include/cli_startup.php")) { chdir($dir); break; }
}
require_once 'include/cli_startup.php';
cli_startup();

$autoload = __DIR__ . '/../../../vendor/autoload.php';
if (!file_exists($autoload)) {
    fwrite(STDERR, "run this from the deployed theme (expected $autoload)\n");
    exit(2);
}
require_once $autoload;

use Zotlabs\Lib\AccessList;

$probe = new class {
    use \Utsukta\SpaCore\Api\Concerns\CartFulfillment;
    public static function call(string $m, ...$a) { return self::$m(...$a); }
};

$fail = 0;
function check(string $what, bool $ok): void {
    global $fail;
    echo ($ok ? 'ok   ' : 'FAIL ') . $what . "\n";
    if (!$ok) $fail++;
}

$seller = channelx_by_n(intval(q("SELECT channel_id FROM channel WHERE channel_removed = 0 ORDER BY channel_id LIMIT 1")[0]['channel_id'] ?? 0));
$buyer  = q("SELECT xchan_hash FROM xchan WHERE xchan_hash != '%s' LIMIT 1", dbesc($seller['channel_hash'] ?? ''))[0]['xchan_hash'] ?? '';
if (!$seller || !$buyer) { echo "SKIP no channel/xchan to test with\n"; exit(0); }

$uid  = intval($seller['channel_id']);
$name = 'cartfulfillment-test';
AccessList::remove($uid, $name);
AccessList::add($uid, $name);
$g      = q("SELECT * FROM pgrp WHERE uid = %d AND gname = '%s' AND deleted = 0 LIMIT 1", $uid, dbesc($name))[0];
$member = fn() => (bool) q("SELECT id FROM pgrp_member WHERE uid = %d AND gid = %d AND xchan = '%s'", $uid, intval($g['id']), dbesc($buyer));

// ── command maps: the addon's shape, both ways ──
$svc = ['x' => ['cmdhash' => 'x', 'cmd' => 'setsvcclass', 'params' => ['class' => 'gold']]];
[$act, $deact] = $probe::call('grantsToCommands', ['groups' => [$g['hash']], 'connect' => true], $svc, []);
$h = md5('addtoprivacygroup' . $g['hash']);
check('activate uses the addon cmdhash', ($act[$h]['params']['group'] ?? '') === $g['hash'] && $act[$h]['cmd'] === 'addtoprivacygroup');
check('deactivate mirrors activate', isset($deact[md5('rmvfromprivacygroup' . $g['hash'])], $deact[md5('rmvconnection')]));
check('commands this UI cannot express survive a save', isset($act['x']));
check('round-trips to grants', $probe::call('commandsToGrants', $act) === ['groups' => [$g['hash']], 'connect' => true]);
[$act2] = $probe::call('grantsToCommands', ['groups' => [], 'connect' => false], $act, $deact);
check('clearing grants drops group + connection commands', array_keys($act2) === ['x']);

// ── grant, then revoke ──
$groupOnly = [$h => $act[$h]];
$r = $probe::call('applyGrants', $seller, $buyer, $groupOnly);
check('buyer added to the group', $member() && $r['granted']['groups'] === [$g['hash']] && !$r['errors']);
$again = $probe::call('applyGrants', $seller, $buyer, $groupOnly);
check('a second grant reports no change', $again['granted']['groups'] === [] && !$again['errors']);
$probe::call('revokeGrants', $seller, $buyer, $r['granted']);
check('revoke removes the buyer', !$member());

// ── a member from before the purchase stays a member ──
AccessList::member_add($uid, '', $buyer, intval($g['id']));
$r = $probe::call('applyGrants', $seller, $buyer, $groupOnly);
$probe::call('revokeGrants', $seller, $buyer, $r['granted']);
check('pre-existing membership survives a cancel', $member());

// ── failures are reported, not swallowed ──
$r = $probe::call('applyGrants', $seller, $buyer, [['cmd' => 'addtoprivacygroup', 'params' => ['group' => 'no-such-group']]]);
check('a deleted group is an error', count($r['errors']) === 1);
$r = $probe::call('applyGrants', $seller, $buyer, $svc);
check('setsvcclass is an error, never run', count($r['errors']) === 1);

AccessList::remove($uid, $name);
echo $fail ? "\n$fail FAILED\n" : "\nall ok\n";
exit($fail ? 1 : 0);
