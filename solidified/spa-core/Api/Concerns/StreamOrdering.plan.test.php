<?php
/**
 * StreamOrdering::TIEBREAK — the channel/network parent query must be planned
 * off a uid-prefixed index, not off the standalone `created`/`commented` one.
 *
 * Without the tiebreak MySQL satisfies `ORDER BY item.created DESC LIMIT 10`
 * by walking the global `created` index newest-first and filtering for this
 * uid — on a hub whose newest rows mostly belong to other channels that is a
 * full-table scan and the request times out (seen live on a 11.4 hub: every
 * unfiltered /spa/channel request 504'd while `?tag=x` answered instantly).
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
$uid = intval($c[0]['uid']);

// The threaded parent query of Handlers/Channel.php, anonymous observer.
$sql = "SELECT item.id AS item_id FROM item
    WHERE true AND item.uid = $uid " . item_normal($uid) . "
    AND item_thread_top = 1 AND item.mid = item.parent_mid
    AND item.item_wall = 1
    AND item.verb IN ('Create','Update','EmojiReact','Invite','" . ACTIVITY_SHARE . "')
    AND item.item_private IN (0,1) AND item.item_private = 0
    ORDER BY item.created DESC";

// EXPLAIN isn't one of the statements the db driver unwraps for us.
$key = function ($q) {
    $r = dbq("EXPLAIN $q LIMIT 10");
    if ($r instanceof \PDOStatement) $r = $r->fetchAll(\PDO::FETCH_ASSOC);
    return $r[0]['key'] ?? '';
};

$with    = $key($sql . StreamOrdering::TIEBREAK);
$without = $key($sql);

echo "uid $uid ({$c[0]['n']} items)\n  without tiebreak: $without\n  with tiebreak:    $with\n";

assert(str_starts_with($with, 'uid'), "expected a uid-prefixed index, got '$with'");
echo (str_starts_with($without, 'uid') ? "PASS  (this database is too small to show the bad plan)\n" : "PASS\n");
