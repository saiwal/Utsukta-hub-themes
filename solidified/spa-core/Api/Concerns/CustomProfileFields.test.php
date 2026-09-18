<?php
/**
 * Round-trip check for Concerns/CustomProfileFields — admin-defined profile
 * fields (profdef) and their per-profile values (profext).
 *
 * The rule under test is core's two-step one: a profdef row only counts once
 * its field_name is also in the admin's basic/advanced field list. A field
 * that isn't must be neither returned nor writable, or a removed field would
 * keep quietly accepting data.
 *
 * Runs against the first local channel, on throwaway field names, and puts
 * the profdef/profext rows and the config back the way it found them.
 *
 * Run inside the Hubzilla install:
 *   ddev exec php core/extend/theme/utsukta-themes/solidified/spa-core/Api/Concerns/CustomProfileFields.test.php
 */

for ($dir = __DIR__; $dir !== '/'; $dir = dirname($dir)) {
    if (file_exists("$dir/include/cli_startup.php")) { chdir($dir); break; }
}
require_once 'include/cli_startup.php';
cli_startup();
require_once __DIR__ . '/CustomProfileFields.php';

use Zotlabs\Lib\Config;

class ProfFieldsProbe
{
    use Utsukta\SpaCore\Api\Concerns\CustomProfileFields;

    public function read(int $uid, string $guid): array  { return $this->customProfileFields($uid, $guid, true); }
    public function write(int $uid, string $guid, array $d): void { $this->saveCustomProfileFields($uid, $guid, $d, true); }
}

const OK_FIELD  = 'cpftest_enabled';
const OFF_FIELD = 'cpftest_disabled';

$fail = 0;
function check(string $label, $got, $want): void {
    global $fail;
    if ($got === $want) { echo "ok    $label\n"; return; }
    $fail++;
    echo "FAIL  $label\n      got  " . json_encode($got) . "\n      want " . json_encode($want) . "\n";
}

$ch = q("SELECT channel_id FROM channel WHERE channel_removed = 0 ORDER BY channel_id LIMIT 1");
if (!$ch) { fwrite(STDERR, "no channel to test against\n"); exit(2); }
$uid = intval($ch[0]['channel_id']);

$pr = q("SELECT profile_guid FROM profile WHERE uid = %d AND is_default = 1 LIMIT 1", $uid);
if (!$pr) { fwrite(STDERR, "channel $uid has no default profile\n"); exit(2); }
$guid = $pr[0]['profile_guid'];

// ── setup: two definitions, only one of them enabled ────────────────────────
$saved_adv = Config::Get('system', 'profile_fields_advanced');
foreach ([OK_FIELD, OFF_FIELD] as $n) {
    q("DELETE FROM profdef WHERE field_name = '%s'", dbesc($n));
    q("DELETE FROM profext WHERE channel_id = %d AND hash = '%s' AND k = '%s'", $uid, dbesc($guid), dbesc($n));
}
q("INSERT INTO profdef (field_name, field_type, field_desc, field_help, field_inputs) VALUES ('%s','text','Occupation','What you do','')", dbesc(OK_FIELD));
q("INSERT INTO profdef (field_name, field_type, field_desc, field_help, field_inputs) VALUES ('%s','text','Not enabled','','')", dbesc(OFF_FIELD));

$adv = $saved_adv ?: ['address','locality','postal_code','partner','howlong','politic','religion','likes','dislikes','interest','channels','music','book','film','tv','romance','employment','education'];
Config::Set('system', 'profile_fields_advanced', array_merge($adv, [OK_FIELD]));

$p = new ProfFieldsProbe();

// ── the allowed list is the gate ────────────────────────────────────────────
$names = array_column($p->read($uid, $guid), 'name');
check('enabled field listed',      in_array(OK_FIELD, $names, true),  true);
check('disabled field not listed', in_array(OFF_FIELD, $names, true), false);

$field = array_values(array_filter($p->read($uid, $guid), fn($f) => $f['name'] === OK_FIELD))[0];
check('label from field_desc', $field['label'], 'Occupation');
check('help from field_help',  $field['help'],  'What you do');
check('empty before any save', $field['value'], '');

// ── write, read back, overwrite ─────────────────────────────────────────────
$p->write($uid, $guid, [OK_FIELD => '  Beekeeper  ', OFF_FIELD => 'should not land']);

$byName = fn(array $rows, string $n) => array_values(array_filter($rows, fn($f) => $f['name'] === $n));
check('value saved + trimmed', $byName($p->read($uid, $guid), OK_FIELD)[0]['value'], 'Beekeeper');

$off = q("SELECT v FROM profext WHERE channel_id = %d AND hash = '%s' AND k = '%s'", $uid, dbesc($guid), dbesc(OFF_FIELD));
check('disabled field not written', $off ?: [], []);

$p->write($uid, $guid, [OK_FIELD => 'Cooper']);
check('update, not duplicate insert', $byName($p->read($uid, $guid), OK_FIELD)[0]['value'], 'Cooper');
check('one profext row', count(q("SELECT id FROM profext WHERE channel_id = %d AND hash = '%s' AND k = '%s'", $uid, dbesc($guid), dbesc(OK_FIELD))), 1);

// absent key must leave the stored value alone (core semantics)
$p->write($uid, $guid, []);
check('absent key preserves value', $byName($p->read($uid, $guid), OK_FIELD)[0]['value'], 'Cooper');

// ── teardown ────────────────────────────────────────────────────────────────
foreach ([OK_FIELD, OFF_FIELD] as $n) {
    q("DELETE FROM profdef WHERE field_name = '%s'", dbesc($n));
    q("DELETE FROM profext WHERE channel_id = %d AND hash = '%s' AND k = '%s'", $uid, dbesc($guid), dbesc($n));
}
if ($saved_adv) Config::Set('system', 'profile_fields_advanced', $saved_adv);
else            Config::Delete('system', 'profile_fields_advanced');

echo $fail ? "\n$fail check(s) failed\n" : "\nall checks passed\n";
exit($fail ? 1 : 0);
