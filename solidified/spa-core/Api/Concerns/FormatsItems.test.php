<?php
/**
 * Contract check for Concerns/FormatsItems — the one item formatter.
 *
 * There used to be four copies of this function (the trait, Handlers/Item, and
 * one each in Articles/Cards), and they drifted: the Item copy never emitted
 * `mimetype`, returned markdown/plain bodies still htmlspecialchars-escaped,
 * and left `owner` null on a boost. So a comment or a single fetched item
 * rendered differently from the same row in a stream. This asserts the shape
 * is one shape: every handler that formats an item emits the same canonical
 * key set, and the fields that were missing carry real values.
 *
 * Rows are discovered, nothing is hardcoded — checks that find no suitable
 * row report SKIP rather than failing.
 *
 * Run inside the Hubzilla install:
 *   ddev exec php core/extend/theme/utsukta-themes/solidified/spa-core/Api/Concerns/FormatsItems.test.php
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

// Every key the SPA's item shape promises. A handler that formats an item and
// omits one of these is the drift this test exists to catch.
const CANONICAL = [
    'uuid', 'mid', 'parent_mid', 'thr_parent', 'message_top', 'created', 'edited',
    'commented', 'title', 'body', 'mimetype', 'verb', 'obj_type', 'like_count',
    'dislike_count', 'announce_count', 'comment_count', 'item_private',
    'item_thread_top', 'item_unseen', 'iid', 'profile_uid', 'flags', 'author',
    'owner', 'recipients', 'permalink', 'location', 'coord', 'expires',
    'viewer_liked', 'viewer_disliked', 'viewer_repeated', 'viewer_attending',
    'viewer_declining', 'viewer_maybe', 'viewer_following', 'can_comment',
    'attach', 'poll', 'categories', 'bookmark_links',
];

$fail = 0;
function check(string $label, $got, $want = true): void
{
    global $fail;
    if ($got === $want) { echo "ok    $label\n"; return; }
    $fail++;
    $t = fn($v) => substr(is_string($v) ? $v : json_encode($v), 0, 120);
    echo "FAIL  $label\n      got  " . $t($got) . "\n      want " . $t($want) . "\n";
}
function skip(string $label): void { echo "SKIP  $label\n"; }

/** Format one item id through $cls's formatter (all of them use the trait). */
function fmt(string $cls, int $id, string $ob): array
{
    $rows = q("SELECT item.* FROM item WHERE id = %d LIMIT 1", $id);
    if (!$rows) return [];
    xchan_query($rows, true);
    $rows = fetch_post_tags($rows, true);

    $fqcn = "Utsukta\\SpaCore\\Api\\Handlers\\$cls";
    $m = new ReflectionMethod($fqcn, 'formatItem');
    $m->setAccessible(true);
    return $m->invoke(new $fqcn(), $rows[0], $ob);
}

/** First item id matching $where, plus the channel that owns it. */
function pick(string $where): ?array
{
    // dbq(), not q(): a $where containing a LIKE pattern has '%' in it, which
    // q() would try to read as a printf placeholder.
    $r = dbq("SELECT i.id, c.channel_address, c.channel_hash, c.channel_id
            FROM item i JOIN channel c ON c.channel_id = i.uid
            WHERE i.item_deleted = 0 AND $where
            ORDER BY i.id DESC LIMIT 1");
    return $r ? $r[0] : null;
}

/** local_channel() needs a session id even in CLI; App::$channel drives dmRecipients(). */
function actAs(array $row): string
{
    session_id('formatsitems-test');
    $_SESSION['uid'] = intval($row['channel_id']);
    $_SESSION['authenticated'] = 1;
    $ch = q("SELECT * FROM channel WHERE channel_id = %d LIMIT 1", intval($row['channel_id']));
    \App::$channel = $ch[0];
    $x = q("SELECT * FROM xchan WHERE xchan_hash = '%s' LIMIT 1", dbesc($row['channel_hash']));
    \App::$observer = $x[0];
    return $row['channel_hash'];
}

// ── Every formatting handler emits the canonical key set ─────────────────────
// Item is the one that used to have its own copy; Channel/Display/Network/
// Pubstream/HqMessages share the trait.
$any = pick("i.item_type = 0 AND i.item_thread_top = 1 AND i.body != ''");
if (!$any) {
    echo "SKIP  no ordinary post in this database\n";
    exit(0);
}
$ob = actAs($any);

foreach (['Item', 'Channel', 'Display', 'Network', 'Pubstream', 'HqMessages'] as $cls) {
    $out     = fmt($cls, intval($any['id']), $ob);
    $missing = array_values(array_diff(CANONICAL, array_keys($out)));
    check("$cls emits the canonical key set", $missing, []);
}

// Item and the streams must agree field for field on the same row — that
// equality is what four hand-maintained copies kept breaking.
check('Item output === Channel output',
    fmt('Item', intval($any['id']), $ob) == fmt('Channel', intval($any['id']), $ob));

// ── The fields the Item copy used to drop ───────────────────────────────────

// A non-bbcode body is stored htmlspecialchars-escaped and must come back
// decoded, paired with the mimetype that says how to render it.
$md = pick("i.mimetype = 'text/markdown' AND i.body LIKE '%&lt;%'");
if ($md) {
    $ob  = actAs($md);
    $out = fmt('Item', intval($md['id']), $ob);
    check('markdown mimetype survives', $out['mimetype'] ?? null, 'text/markdown');
    check('markdown body is decoded', !str_contains($out['body'], '&lt;'));
} else {
    skip('no escaped markdown body in this database');
}

// A boost/Announce keeps the booster in source_xchan; the owner block has to
// report them, or the reader sees no attribution for who shared it.
$boost = pick("i.source_xchan != ''");
if ($boost) {
    $ob  = actAs($boost);
    $out = fmt('Item', intval($boost['id']), $ob);
    check('boost has an owner', !empty($out['owner']['name']));
} else {
    skip('no boosted item in this database');
}

$loc = pick("i.location != ''");
if ($loc) {
    $ob  = actAs($loc);
    $out = fmt('Item', intval($loc['id']), $ob);
    check('location is carried through', $out['location'] ?? null,
        htmlspecialchars_decode(q("SELECT location FROM item WHERE id = %d",
            intval($loc['id']))[0]['location'], ENT_QUOTES | ENT_HTML5));
} else {
    skip('no located item in this database');
}

// A direct message names its other participants; author/owner alone can't,
// which is what `recipients` is for.
$dm = pick("i.item_private = 2 AND i.allow_cid != ''");
if ($dm) {
    $ob  = actAs($dm);
    $out = fmt('Item', intval($dm['id']), $ob);
    check('DM carries a recipients string', is_string($out['recipients'] ?? null));
    check('DM is flagged direct_message', in_array('direct_message', $out['flags'], true));
} else {
    skip('no direct message in this database');
}

// ── isPinnedItem ────────────────────────────────────────────────────────────
// Only a thread top can be pinned; a comment must short-circuit without
// reading pconfig.
$comment = pick("i.item_thread_top = 0 AND i.verb = 'Create'");
if ($comment) {
    actAs($comment);
    $h = new Utsukta\SpaCore\Api\Handlers\Item();
    $m = new ReflectionMethod(Utsukta\SpaCore\Api\Handlers\Item::class, 'isPinnedItem');
    $m->setAccessible(true);
    $row = q("SELECT * FROM item WHERE id = %d LIMIT 1", intval($comment['id']))[0];
    check('a comment is never pinned', $m->invoke($h, $row), false);
} else {
    skip('no comment in this database');
}

echo "\n" . ($fail ? "$fail check(s) failed\n" : "all checks passed\n");
exit($fail ? 1 : 0);
