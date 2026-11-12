<?php
if (PHP_SAPI !== 'cli') exit;   // deployed into the web root by the build; never runnable over HTTP
/**
 * StreamOrdering::tiebreak() — the wall queries must not be planned off the
 * standalone `created` index.
 *
 * Plain, MySQL can satisfy `WHERE item.uid = X AND item.item_wall = 1 ...
 * ORDER BY item.created DESC LIMIT 10` by walking the *standalone* `created`
 * index newest-first and fetching every row to test the flags (EXPLAIN: type
 * index, key created). On a hub where those flags are sparse among recent rows
 * that is a full-table scan: seen live on a 11.4 hub where every unfiltered
 * /spa/channel request 504'd at 60s while `?tag=x` answered instantly.
 *
 * A trailing sort key no index covers takes that plan away — the same thing
 * core's channel module does with `ORDER BY $ordering DESC, item_id`.
 *
 * Run inside the Hubzilla install:
 *   ddev exec php core/extend/theme/utsukta-themes/solidified/spa-core/Api/Concerns/StreamOrdering.plan.test.php
 */

for ($dir = __DIR__; $dir !== '/'; $dir = dirname($dir)) {
    if (file_exists("$dir/include/cli_startup.php")) { chdir($dir); break; }
}
require_once 'include/cli_startup.php';
cli_startup();
require_once dirname(__DIR__, 3) . '/vendor/autoload.php';

use Utsukta\SpaCore\Api\Concerns\StreamOrdering;

if (defined('ACTIVE_DBTYPE') && defined('DBTYPE_POSTGRES') && ACTIVE_DBTYPE == DBTYPE_POSTGRES) {
    echo "SKIP  EXPLAIN shape is MySQL-specific\n"; exit(0);
}

$c = q("SELECT uid, count(*) n FROM item GROUP BY uid ORDER BY n DESC LIMIT 1");
if (!$c) { echo "SKIP  no items in this database\n"; exit(0); }
$uid    = intval($c[0]['uid']);
$normal = item_normal($uid);

// Real query shapes: the threaded parent query of Handlers/Channel.php and the
// RSS query of Handlers/Feed.php, both with an anonymous observer. %TB% is
// where the tiebreak lands.
$shapes = [
    'channel parents' => "SELECT item.id AS item_id FROM item
        WHERE true AND item.uid = $uid $normal
        AND item_thread_top = 1 AND item.mid = item.parent_mid
        AND item.item_wall = 1
        AND item.verb IN ('Create','Update','EmojiReact','Invite','" . ACTIVITY_SHARE . "')
        AND item.item_private IN (0,1) AND item.item_private = 0
        ORDER BY item.created DESC %TB%",
    'feed'            => "SELECT item.uuid FROM item
        WHERE item.uid = $uid $normal
        AND item.item_thread_top = 1 AND item.item_private = 0 AND item.item_wall = 1
        ORDER BY item.created DESC %TB%",
];

// EXPLAIN isn't one of the statements the db driver unwraps for us.
$plan = function (string $sql, string $tb): array {
    $r = dbq('EXPLAIN ' . str_replace('%TB%', $tb, $sql) . ' LIMIT 10');
    if ($r instanceof \PDOStatement) $r = $r->fetchAll(\PDO::FETCH_ASSOC);
    return [$r[0]['key'] ?? '', $r[0]['Extra'] ?? ''];
};

$tb      = StreamOrdering::tiebreak();
$saw_bad = false;

echo "uid $uid ({$c[0]['n']} items)\n";

foreach ($shapes as $label => $sql) {
    [$key_on, $extra_on] = $plan($sql, $tb);
    [$key_off]           = $plan($sql, '');
    $saw_bad = $saw_bad || $key_off === 'created';

    echo "  $label\n    plain:       $key_off\n    w/ tiebreak: $key_on ($extra_on)\n";

    // The filesort the tiebreak buys runs over the channel's own rows, which is
    // the trade: bounded work instead of an unbounded walk of the whole table.
    assert($key_on !== 'created', "$label: still planned off the global created index");
}

assert(str_contains(StreamOrdering::tiebreak('i'), 'i.parent'), 'tiebreak must honour the alias');

// A ranged ranked view bounds its aggregate join by the same dbegin, so "Top
// (month)" reads a month of reactions instead of the channel's whole history.
$ranged    = StreamOrdering::clause('top', $uid, '2026-08-18')['join'];
$unbounded = StreamOrdering::clause('top', $uid)['join'];
$discussed = StreamOrdering::clause('discussed', $uid, '2026-08-18')['join'];

assert(str_contains($ranged, "r.created >= '2026-08-17 00:00:00'"), "ranged join must bound reactions: $ranged");
assert(str_contains($discussed, "r.created >= '2026-08-17 00:00:00'"), 'comment join must bound the same way');
assert(!str_contains($unbounded, 'r.created >='), 'an unranged view must aggregate everything');

echo $saw_bad ? "PASS\n" : "PASS  (this database does not show the bad plan)\n";
