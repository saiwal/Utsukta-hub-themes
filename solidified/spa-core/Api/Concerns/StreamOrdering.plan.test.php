<?php
/**
 * StreamOrdering::indexAnchor() — the stream queries must be planned off the
 * uid-prefixed index, without a filesort.
 *
 * Unanchored, MySQL satisfies `WHERE item.uid = X ... ORDER BY item.created
 * DESC LIMIT 10` by walking the *standalone* `created` index newest-first and
 * filtering for the uid as it goes (EXPLAIN: type index, key created). On a
 * hub whose newest rows belong to other channels that is a full-table scan:
 * seen live on a 11.4 hub where every unfiltered /spa/channel request 504'd
 * at 60s while `?tag=x` answered instantly.
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
// RSS query of Handlers/Feed.php, both with an anonymous observer.
$shapes = [
    'channel parents' => "SELECT item.id AS item_id FROM item
        WHERE true AND item.uid = $uid $normal
        AND item_thread_top = 1 AND item.mid = item.parent_mid
        AND item.item_wall = 1
        AND item.verb IN ('Create','Update','EmojiReact','Invite','" . ACTIVITY_SHARE . "')
        AND item.item_private IN (0,1) AND item.item_private = 0 %ANCHOR%
        ORDER BY item.created DESC",
    'feed'            => "SELECT item.uuid FROM item
        WHERE item.uid = $uid $normal
        AND item.item_thread_top = 1 AND item.item_private = 0 AND item.item_wall = 1 %ANCHOR%
        ORDER BY item.created DESC",
];

// EXPLAIN isn't one of the statements the db driver unwraps for us.
$plan = function (string $sql, string $anchor): array {
    $r = dbq('EXPLAIN ' . str_replace('%ANCHOR%', $anchor, $sql) . ' LIMIT 10');
    if ($r instanceof \PDOStatement) $r = $r->fetchAll(\PDO::FETCH_ASSOC);
    return [$r[0]['key'] ?? '', $r[0]['Extra'] ?? ''];
};

$anchor  = StreamOrdering::indexAnchor('item.created');
$saw_bad = false;

echo "uid $uid ({$c[0]['n']} items)\n";

foreach ($shapes as $label => $sql) {
    [$key_on, $extra_on] = $plan($sql, $anchor);
    [$key_off]           = $plan($sql, '');
    $saw_bad = $saw_bad || !str_starts_with($key_off, 'uid');

    echo "  $label\n    unanchored: $key_off\n    anchored:   $key_on ($extra_on)\n";

    assert(str_starts_with($key_on, 'uid'), "$label: expected a uid-prefixed index, got '$key_on'");
    assert(!str_contains($extra_on, 'filesort'), "$label: anchored query should not filesort ($extra_on)");
}

// The ranked orders sort by an expression over a join — no index can serve
// that order, so the anchor must stay out of their way.
assert(StreamOrdering::indexAnchor('COALESCE(rx.likes, 0)') === '', 'ranked order must not be anchored');

echo $saw_bad ? "PASS\n" : "PASS  (this database is too small to show the bad plan)\n";
