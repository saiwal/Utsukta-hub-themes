<?php
/**
 * Round-trip check for Handlers/Bookmarks.
 *
 * Two things here can lose data silently, and both are why this exists:
 *
 *  1. menu_edit_item() hands its $arr to AccessList::set_from_array(), which
 *     defaults every missing contact_allow/group_allow/contact_deny/group_deny
 *     key to [] — so editing a *private* bookmark's title without re-passing its
 *     ACL quietly makes it public. Same family as the item_store_update() trap
 *     in ItemCollection.
 *  2. Bookmarks live in the same menu_item table as nav menus. A delete or edit
 *     scoped only to (mitem_id, channel) will happily destroy a nav menu entry,
 *     so every route has to join menu and require MENU_BOOKMARK.
 *
 * Creates real folders and bookmarks plus a throwaway nav menu, exercises the
 * routes, and removes all of it again.
 *
 * Run inside the Hubzilla install:
 *   ddev exec php core/extend/theme/utsukta-themes/solidified/spa-core/Api/Handlers/Bookmarks.test.php
 *
 * Response::send()/error() exit, so each handler call is its own process — the
 * driver re-executes this file per step, as ItemCollection.test.php does.
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

const F1   = 'bookmarks-probe-one';
const F2   = 'bookmarks-probe-two';
const NAV  = 'bookmarks-probe-navmenu';
const URL1 = 'https://probe.example/one';
const URL2 = 'https://probe.example/two';

// ---------------------------------------------------------------------------
// Step mode: authenticate as $nick and run one handler call.
//   php Bookmarks.test.php step <nick> <get|post|delete> <argv2> <argv3> <json>
// ---------------------------------------------------------------------------

if (($argv[1] ?? '') === 'step') {
    [$nick, $verb, $a2, $a3] = [$argv[2], $argv[3], $argv[4], $argv[5]];
    $payload = json_decode($argv[6] ?? '{}', true) ?: [];

    // local_channel() needs a non-empty session_id(); naming one is enough in CLI.
    session_id('bookmarks-test');

    $ch = q("SELECT * FROM channel WHERE channel_address = '%s' LIMIT 1", dbesc($nick));
    $_SESSION['uid']              = intval($ch[0]['channel_id']);
    $_SESSION['authenticated']    = 1;
    $_SESSION['solidified_csrf']  = 'test-token';
    $_SERVER['HTTP_X_CSRF_TOKEN'] = 'test-token';
    // Auth::parseJsonBody()'s form-data branch reads $_POST, which CLI can set;
    // php://input cannot be written to.
    $_SERVER['CONTENT_TYPE']      = 'multipart/form-data';
    $_POST                        = $payload;

    \App::$channel = $ch[0];
    $x = q("SELECT * FROM xchan WHERE xchan_hash = '%s' LIMIT 1", dbesc($ch[0]['channel_hash']));
    \App::$observer = $x[0];
    \App::$argv = ['spa', 'bookmarks', $a2 === '-' ? '' : $a2, $a3 === '-' ? '' : $a3];
    \App::$argc = 4;

    (new Utsukta\SpaCore\Api\Handlers\Bookmarks())->$verb();
    exit;
}

// ---------------------------------------------------------------------------
// Driver
// ---------------------------------------------------------------------------

$nick = $argv[1] ?? null;
if (!$nick) {
    $c = q("SELECT channel_address FROM channel WHERE channel_removed = 0
            AND channel_system = 0 ORDER BY channel_id LIMIT 1");
    if (!$c) { echo "SKIP  no local channel in this database\n"; exit(0); }
    $nick = $c[0]['channel_address'];
}
$ch  = q("SELECT * FROM channel WHERE channel_address = '%s' LIMIT 1", dbesc($nick));
$uid = intval($ch[0]['channel_id']);
echo "channel: $nick (uid $uid)\n";

// Most routes are gated on the Bookmarks app, exactly as core gates /bookmarks.
if (!Zotlabs\Lib\Apps::system_app_installed($uid, 'Bookmarks')) {
    echo "SKIP  the Bookmarks app is not installed for $nick\n";
    echo "      install it in the app list, then re-run\n";
    exit(0);
}

$fail = 0;
function check(string $label, $got, $want = true): void
{
    global $fail;
    if ($got === $want) { echo "ok    $label\n"; return; }
    $fail++;
    echo "FAIL  $label\n      got  " . json_encode($got) . "\n      want " . json_encode($want) . "\n";
}

function step(string $nick, string $verb, string $a2, array $payload = [], string $a3 = ''): array
{
    $cmd = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__FILE__) . ' step '
        . escapeshellarg($nick) . ' ' . escapeshellarg($verb) . ' '
        . escapeshellarg($a2 ?: '-') . ' ' . escapeshellarg($a3 ?: '-') . ' '
        . escapeshellarg(json_encode($payload));
    $out = shell_exec($cmd . ' 2>&1');
    $j   = json_decode((string) $out, true);
    if (!is_array($j)) {
        echo "      raw: " . substr((string) $out, 0, 400) . "\n";
        return ['error' => ['message' => 'unparseable response']];
    }
    return $j;
}

/** The stored menu_item row, or null. */
function row(int $uid, int $id): ?array
{
    $r = q("SELECT * FROM menu_item WHERE mitem_id = %d AND mitem_channel_id = %d LIMIT 1",
        intval($id), intval($uid));
    return $r ? $r[0] : null;
}

function cleanup(int $uid): void
{
    require_once 'include/menu.php';
    foreach ([F1, F2, NAV] as $name) {
        $m = q("SELECT menu_id FROM menu WHERE menu_name = '%s' AND menu_channel_id = %d",
            dbesc($name), intval($uid));
        foreach (($m ?: []) as $one) menu_delete_id(intval($one['menu_id']), $uid);
    }
    // bookmark_add() may also have filed a probe URL into the channel's own
    // derived folder if a menu target was ever missed.
    q("DELETE FROM menu_item WHERE mitem_channel_id = %d AND mitem_link IN ('%s', '%s')",
        intval($uid), dbesc(URL1), dbesc(URL2));
}

cleanup($uid);   // leftovers from an interrupted run

// ── Folders ─────────────────────────────────────────────────────────────────

$r  = step($nick, 'post', 'folder', ['name' => F1, 'desc' => 'Probe one']);
$f1 = intval($r['data']['menu_id'] ?? 0);
check('folder one created', $f1 > 0);

$r  = step($nick, 'post', 'folder', ['name' => F2, 'desc' => 'Probe two']);
$f2 = intval($r['data']['menu_id'] ?? 0);
check('folder two created', $f2 > 0);

if (!$f1 || !$f2) { cleanup($uid); echo "\ncould not create folders\n"; exit(1); }

// A bookmark folder must be flagged MENU_BOOKMARK, or /bookmarks won't list it.
$m = q("SELECT menu_flags FROM menu WHERE menu_id = %d", intval($f1));
check('folder carries MENU_BOOKMARK', (bool)(intval($m[0]['menu_flags']) & MENU_BOOKMARK));

// Name collisions are channel-wide — a second folder of the same name is refused
// rather than silently merged.
$r = step($nick, 'post', 'folder', ['name' => F1]);
check('duplicate folder name refused', $r['error']['status'] ?? null, 409);

// ── Create, private ─────────────────────────────────────────────────────────

$r   = step($nick, 'post', '', ['url' => URL1, 'title' => 'Probe one',
    'menu_id' => $f1, 'private' => true]);
$id1 = intval($r['data']['mitem_id'] ?? 0);
check('private bookmark created', $id1 > 0);

if (!$id1) { cleanup($uid); echo "\ncould not create bookmark\n"; exit(1); }

$before = row($uid, $id1);
check('landed in the requested folder', intval($before['mitem_menu_id']), $f1);
check('private bookmark is ACLd to its owner',
    $before['allow_cid'], '<' . $ch[0]['channel_hash'] . '>');

// ── The edit trap ───────────────────────────────────────────────────────────
// A title-only edit must not touch the link, the flags, or — the actual trap —
// the ACL that makes this bookmark private.

$r = step($nick, 'post', (string)$id1, ['title' => 'Probe one renamed']);
check('title edit succeeded', $r['data']['success'] ?? null, true);

$after = row($uid, $id1);
check('title changed',            $after['mitem_desc'], 'Probe one renamed');
check('link survived the edit',   $after['mitem_link'], $before['mitem_link']);
check('flags survived the edit',  intval($after['mitem_flags']), intval($before['mitem_flags']));
check('ACL survived the edit',    $after['allow_cid'], $before['allow_cid']);

// ── Move ────────────────────────────────────────────────────────────────────

$r = step($nick, 'post', (string)$id1, ['menu_id' => $f2]);
check('move succeeded', $r['data']['success'] ?? null, true);

$moved = row($uid, $id1);
check('moved to the other folder', intval($moved['mitem_menu_id']), $f2);
check('ACL survived the move',     $moved['allow_cid'], $before['allow_cid']);

// ── Reorder ─────────────────────────────────────────────────────────────────
// bookmark_add() never sets mitem_order, so everything starts at 0 and the
// listing falls back to alphabetical until something writes an order.

$r   = step($nick, 'post', '', ['url' => URL2, 'title' => 'Probe two', 'menu_id' => $f2]);
$id2 = intval($r['data']['mitem_id'] ?? 0);
check('second bookmark created', $id2 > 0);

if ($id2) {
    check('order starts unset', intval(row($uid, $id2)['mitem_order']), 0);

    $r = step($nick, 'post', 'reorder', ['menu_id' => $f2, 'ids' => [$id2, $id1]]);
    check('reorder succeeded', $r['data']['success'] ?? null, true);
    check('first id got order 0',  intval(row($uid, $id2)['mitem_order']), 0);
    check('second id got order 1', intval(row($uid, $id1)['mitem_order']), 1);
    check('reorder kept the ACL',  row($uid, $id1)['allow_cid'], $before['allow_cid']);
}

// ── Listing shape ───────────────────────────────────────────────────────────

$r     = step($nick, 'get', '');
$menus = $r['data']['menus'] ?? [];
$probe = null;
foreach ($menus as $m) if (intval($m['id']) === $f2) $probe = $m;

check('probe folder is listed', $probe !== null);
if ($probe) {
    // menu_desc, not menu_name: a post-derived folder's name is "<hash16> Name".
    check('folder reports its label', $probe['label'], 'Probe two');
    check('own folder is not flagged system', $probe['system'], false);
    $one = null;
    foreach ($probe['items'] as $i) if (intval($i['id']) === $id1) $one = $i;
    check('item is listed', $one !== null);
    if ($one) {
        check('item reports private', $one['private'], true);
        check('item carries a visit url', !empty($one['visit_url']));
    }
}

// ── Nav menus are out of reach ──────────────────────────────────────────────
// The whole point of joining menu and testing MENU_BOOKMARK on every route.

require_once 'include/menu.php';
$navId = menu_create(['menu_channel_id' => $uid, 'menu_name' => NAV,
    'menu_desc' => 'Probe nav menu', 'menu_flags' => 0]);
check('nav menu created', $navId > 0);

if ($navId) {
    // Inserted directly rather than through menu_add_item(): that builds an
    // AccessList from App::get_channel(), which needs a local_channel() session
    // this driver process does not have.
    q("INSERT INTO menu_item ( mitem_link, mitem_desc, mitem_flags, allow_cid, allow_gid,
           deny_cid, deny_gid, mitem_channel_id, mitem_menu_id, mitem_order )
       VALUES ( '%s', '%s', 0, '', '', '', '', %d, %d, 0 )",
        dbesc('https://probe.example/nav'), dbesc('Nav entry'),
        intval($uid), intval($navId));
    $navItem = q("SELECT mitem_id FROM menu_item WHERE mitem_menu_id = %d AND mitem_channel_id = %d LIMIT 1",
        intval($navId), intval($uid));
    $navMitem = intval($navItem[0]['mitem_id'] ?? 0);

    $r = step($nick, 'delete', (string)$navMitem);
    check('deleting a nav menu item is refused', $r['error']['status'] ?? null, 404);
    check('nav menu item still exists', row($uid, $navMitem) !== null);

    $r = step($nick, 'post', (string)$navMitem, ['title' => 'hijacked']);
    check('editing a nav menu item is refused', $r['error']['status'] ?? null, 404);

    $r = step($nick, 'delete', 'folder', [], (string)$navId);
    check('deleting a nav menu as a folder is refused', $r['error']['status'] ?? null, 404);
    check('nav menu still exists',
        q("SELECT menu_id FROM menu WHERE menu_id = %d", intval($navId)) !== []);
}

// ── Delete ──────────────────────────────────────────────────────────────────

$r = step($nick, 'delete', (string)$id1);
check('bookmark deleted', $r['data']['success'] ?? null, true);
check('bookmark row is gone', row($uid, $id1), null);

$r = step($nick, 'delete', 'folder', [], (string)$f2);
check('folder deleted', $r['data']['success'] ?? null, true);
check('folder contents went with it', $id2 ? row($uid, $id2) : null, null);

cleanup($uid);

echo "\n" . ($fail ? "$fail check(s) failed\n" : "all checks passed\n");
exit($fail ? 1 : 0);
