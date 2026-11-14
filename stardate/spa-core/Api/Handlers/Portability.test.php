<?php
if (PHP_SAPI !== 'cli') exit;   // deployed into the web root by the build; never runnable over HTTP
/**
 * Round-trip check for Handlers/Portability's content and extras export.
 *
 * The claim it pins: a note (ITEM_TYPE_CUSTOM wall item) and a draft
 * (resource_type 'draft') leave in the yearly export and come back through
 * importPayload() as what they were, and a source/profext row survives
 * extras() → importExtras() without duplicating on a second import.
 *
 * Creates real rows on the first ordinary channel and removes them again.
 *
 *   ddev exec php core/extend/theme/utsukta-themes/solidified/spa-core/Api/Handlers/Portability.test.php
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
require_once 'include/items.php';

use Utsukta\SpaCore\Api\Handlers\Portability;

$fail = 0;
function check(bool $ok, string $what): void
{
    global $fail;
    echo ($ok ? 'ok   ' : 'FAIL ') . $what . "\n";
    if (!$ok) $fail++;
}

$r = q("SELECT * FROM channel WHERE channel_removed = 0 AND channel_system = 0 ORDER BY channel_id LIMIT 1");
if (!$r) { echo "SKIP no channel\n"; exit(0); }
$channel = $r[0];
$uid  = intval($channel['channel_id']);
$nick = $channel['channel_address'];
$year = (int) gmdate('Y');

$mk = function (array $over) use ($channel, $uid): string {
    $uuid = item_message_id();
    $mid  = z_root() . '/item/' . $uuid;
    $now  = datetime_convert();
    item_store($over + [
        'aid' => $channel['channel_account_id'], 'uid' => $uid, 'uuid' => $uuid,
        'mid' => $mid, 'parent_mid' => $mid, 'thr_parent' => $mid,
        'owner_xchan' => $channel['channel_hash'], 'author_xchan' => $channel['channel_hash'],
        'created' => $now, 'edited' => $now, 'verb' => 'Create', 'obj_type' => 'Note',
        'mimetype' => 'text/bbcode', 'body' => 'portability probe', 'plink' => $mid,
        'item_wall' => 1, 'item_origin' => 1, 'item_thread_top' => 1, 'item_private' => 1,
    ], false, false, false);
    return $mid;
};
$row = fn(string $mid) => q("SELECT * FROM item WHERE mid = '%s' AND uid = %d LIMIT 1", dbesc($mid), $uid)[0] ?? null;
$purge = fn(string $mid) => q("DELETE FROM item WHERE mid = '%s' AND uid = %d", dbesc($mid), $uid);

$note  = $mk(['item_type' => ITEM_TYPE_CUSTOM]);
$draft = $mk(['resource_type' => 'draft', 'item_unpublished' => 1, 'item_wall' => 0,
              'route' => '{"scope":"post:probe"}']);

try {
    check((bool) $row($note) && (bool) $row($draft), 'probe note and draft stored');

    $files = iterator_to_array(Portability::itemFiles($uid, $nick, $year));
    $mids = fn(array $names) => array_merge(...array_values(array_map(
        fn($n) => array_column($files[$n]['item'], 'message_id'), $names
    )) ?: [[]]);
    $months = array_values(array_filter(array_keys($files), fn($n) => !str_ends_with($n, '-drafts.json')));
    $uuidOf = fn(string $mid) => basename($mid);
    $has = fn(array $names, string $mid) => (bool) array_filter(
        $mids($names), fn($m) => $m === $mid || $m === $uuidOf($mid)
    );

    check($has($months, $note), 'note is in the monthly export');
    check(isset($files["$nick-$year-drafts.json"]) && $has(["$nick-$year-drafts.json"], $draft), 'draft is in the drafts file');
    check(!$has($months, $draft), 'draft is not duplicated into a month');

    // Encode exactly as the zip does, drop the rows, import them back.
    $payloads = array_map('json_encode', $files);
    $purge($note);
    $purge($draft);
    check(!$row($note) && !$row($draft), 'probes removed before import');

    foreach ($payloads as $raw) {
        Portability::importPayload($channel, $raw);
    }
    $n = $row($note);
    $d = $row($draft);
    check($n && intval($n['item_type']) === ITEM_TYPE_CUSTOM && intval($n['item_private']) === 1, 'note restored as a private ITEM_TYPE_CUSTOM item');
    check($d && $d['resource_type'] === 'draft' && intval($d['item_unpublished']) === 1, 'draft restored as a draft');
    check($d && str_contains(html_entity_decode((string) $d['route']), '"scope":"post:probe"'), 'draft keeps its route scope');

    check(Portability::importPayload($channel, 'not json') === null, 'garbage is rejected');
    check(Portability::importPayload($channel, '{"hello":1}') === null, 'unrelated JSON is rejected');

    // ── extras ──
    $h = 'portability-probe';
    q("INSERT INTO profext (channel_id, hash, k, v) VALUES (%d, '%s', 'probe', 'value')", $uid, $h);
    q("INSERT INTO source (src_channel_id, src_channel_xchan, src_xchan, src_patt, src_tag) VALUES (%d, '%s', '%s', 'p%%att', '')",
        $uid, dbesc($channel['channel_hash']), $h);
    $x = json_decode(json_encode(Portability::extras($channel)), true);
    check(in_array($h, array_column($x['profext'], 'hash'), true) && in_array($h, array_column($x['source'], 'src_xchan'), true), 'extras export source + profext');

    q("DELETE FROM profext WHERE channel_id = %d AND hash = '%s'", $uid, $h);
    q("DELETE FROM source WHERE src_channel_id = %d AND src_xchan = '%s'", $uid, $h);
    Portability::importExtras($channel, $x);
    Portability::importExtras($channel, $x);
    $p = q("SELECT v FROM profext WHERE channel_id = %d AND hash = '%s'", $uid, $h) ?: [];
    $s = q("SELECT src_patt FROM source WHERE src_channel_id = %d AND src_xchan = '%s'", $uid, $h) ?: [];
    check(count($p) === 1 && $p[0]['v'] === 'value', 'profext restored once');
    check(count($s) === 1 && $s[0]['src_patt'] === 'p%att', 'source restored once, % intact');
} finally {
    $purge($note);
    $purge($draft);
    q("DELETE FROM profext WHERE channel_id = %d AND hash = 'portability-probe'", $uid);
    q("DELETE FROM source WHERE src_channel_id = %d AND src_xchan = 'portability-probe'", $uid);
}

echo $fail ? "FAILED ($fail)\n" : "PASS\n";
exit($fail ? 1 : 0);
