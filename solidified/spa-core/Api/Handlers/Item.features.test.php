<?php
if (PHP_SAPI !== 'cli') exit;   // deployed into the web root by the build; never runnable over HTTP
/**
 * The SPA-only behaviours of /spa/item — the things core's Zotlabs\Module\Item
 * knows nothing about, and which therefore have to keep working *around* it.
 *
 * Posting now goes through core (src/docs/dev/en/core-parity.md), so
 * parity.test.php's post cases compare core against core and prove nothing.
 * These are what replaced them: Markdown source stashing, the embed index,
 * local-only posts, delayed publishing, poll fields, and the response shape the
 * composer reads.
 *
 * Probes run on a connection-free channel so nothing federates, except the
 * local-only case, which needs a channel with local recipients to prove that a
 * local-only post is *not* delivered.
 *
 * Run inside the Hubzilla install:
 *   ddev exec php core/extend/theme/utsukta-themes/solidified/spa-core/Api/Handlers/Item.features.test.php
 */

for ($dir = __DIR__; $dir !== '/'; $dir = dirname($dir)) {
    if (file_exists("$dir/include/cli_startup.php")) { chdir($dir); break; }
}
require_once 'include/cli_startup.php';
cli_startup();

$autoload = dirname(__DIR__, 3) . '/vendor/autoload.php';
if (!file_exists($autoload)) {
    fwrite(STDERR, "run this from the deployed theme (expected $autoload)\n");
    exit(2);
}
require_once $autoload;

// ── Step mode: one POST to /spa/item as $nick ──────────────────────────────
if (($argv[1] ?? '') === 'step') {
    $nick    = $argv[2];
    $payload = json_decode($argv[3], true) ?: [];

    $ch = q("SELECT * FROM channel WHERE channel_address = '%s' LIMIT 1", dbesc($nick));
    $x  = q("SELECT * FROM xchan WHERE xchan_hash = '%s' LIMIT 1", dbesc($ch[0]['channel_hash']));

    @session_start();
    $_SESSION['uid']              = intval($ch[0]['channel_id']);
    $_SESSION['account_id']       = intval($ch[0]['channel_account_id']);
    $_SESSION['authenticated']    = 1;
    $_SESSION['solidified_csrf']  = 'test-token';
    $_SERVER['HTTP_X_CSRF_TOKEN'] = 'test-token';
    $_SERVER['CONTENT_TYPE']      = 'multipart/form-data';
    $_POST                        = $payload;

    App::$channel  = $ch[0];
    App::$observer = $x[0];
    App::$argv     = ['spa', 'item'];
    App::$argc     = 2;

    (new \Utsukta\SpaCore\Api\Handlers\Item())->post();
    exit;
}

// ── Driver ─────────────────────────────────────────────────────────────────
$c = q("SELECT channel_address nick, channel_id uid FROM channel c WHERE channel_removed = 0
        AND channel_system = 0
        AND (SELECT COUNT(*) FROM abook a WHERE a.abook_channel = c.channel_id AND a.abook_self = 0) = 0
        ORDER BY channel_id LIMIT 1");
if (!$c) { echo "SKIP  no connection-free channel to probe with\n"; exit(0); }
$nick = $c[0]['nick'];
$uid  = intval($c[0]['uid']);
echo "channel: $nick (uid $uid)\n\n";

$fail = 0; $probes = [];
function ok(string $l): void { echo "ok    $l\n"; }
function bad(string $l, string $d = ''): void { global $fail; $fail++; echo "FAIL  $l\n$d"; }

function post(string $nick, array $payload): array
{
    $out = shell_exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__FILE__)
        . ' step ' . escapeshellarg($nick) . ' ' . escapeshellarg(json_encode($payload)) . ' 2>&1');
    $j = json_decode((string) $out, true);
    if (!is_array($j)) { echo "      raw: " . substr((string) $out, 0, 300) . "\n"; return []; }
    return $j;
}

function iidOf(array $res): int { return intval($res['data']['post']['iid'] ?? 0); }

// ── 1. response shape the composer reads (PostComposer.tsx) ────────────────
$res = post($nick, ['scope' => 'public', 'profile_uid' => $uid,
    'body' => 'feat shape ' . bin2hex(random_bytes(4)), 'mimetype' => 'text/bbcode']);
$iid = iidOf($res);
$probes[] = $iid;
$iid > 0
    ? ok('response carries a numeric data.post.iid')
    : bad('response shape', "      got " . substr(json_encode($res), 0, 200) . "\n");

// ── 2. Markdown is converted for storage, source kept for the editor ───────
$md  = "feat md " . bin2hex(random_bytes(4)) . "\n\n**bold** and _em_";
$res = post($nick, ['scope' => 'public', 'profile_uid' => $uid,
    'body' => $md, 'mimetype' => 'text/markdown']);
$iid = iidOf($res);
$probes[] = $iid;

if (!$iid) { bad('markdown post was not stored'); }
else {
    $row  = q('SELECT body, mimetype FROM item WHERE id = %d LIMIT 1', intval($iid))[0];
    $recall = \Utsukta\SpaCore\Api\ContentTypes::recallMarkdown($iid, $row['body']);

    $row['mimetype'] === 'text/bbcode'
        ? ok('markdown is stored as bbcode')
        : bad('markdown storage mimetype', "      got '" . $row['mimetype'] . "'\n");
    str_contains($row['body'], '[b]')
        ? ok('markdown emphasis became bbcode')
        : bad('markdown conversion', "      body: " . substr($row['body'], 0, 90) . "\n");
    $recall === $md
        ? ok('the Markdown source round-trips for the editor')
        : bad('md_source recall', "      got " . substr($recall, 0, 90) . "\n");
}

// ── 3. delayed publish ─────────────────────────────────────────────────────
$res = post($nick, ['scope' => 'public', 'profile_uid' => $uid,
    'body' => 'feat delayed ' . bin2hex(random_bytes(4)), 'mimetype' => 'text/bbcode',
    'created' => datetime_convert('UTC', 'UTC', 'now + 3 days', 'Y-m-d H:i:s')]);
$iid = iidOf($res);
$probes[] = $iid;
if (!$iid) { bad('delayed post was not stored'); }
else {
    intval(q('SELECT item_delayed FROM item WHERE id = %d', intval($iid))[0]['item_delayed']) === 1
        ? ok('a future created date sets item_delayed')
        : bad('delayed publish did not set item_delayed');
}

// ── 4. poll: comments close with the poll, and it must NOT self-expire ─────
$res = post($nick, ['scope' => 'public', 'profile_uid' => $uid,
    'body' => 'feat poll ' . bin2hex(random_bytes(4)), 'mimetype' => 'text/bbcode',
    'poll_answers' => ['yes', 'no'], 'poll_expire_value' => 1, 'poll_expire_unit' => 'Days']);
$iid = iidOf($res);
$probes[] = $iid;
if (!$iid) { bad('poll was not stored'); }
else {
    $p = q('SELECT obj_type, comments_closed, expires FROM item WHERE id = %d', intval($iid))[0];
    $p['obj_type'] === 'Question'
        ? ok('poll stores as a Question')
        : bad('poll obj_type', "      got '" . $p['obj_type'] . "'\n");
    $p['comments_closed'] > '0002-01-01'
        ? ok('poll closes its comments at the poll end')
        : bad('poll comments_closed not set');
    // expires is what the reaper deletes on; core never sets it from a poll
    $p['expires'] < '0002-01-01'
        ? ok('poll does not set expires (it would delete itself at close)')
        : bad('poll set expires', "      got '" . $p['expires'] . "'\n");
}

// ── 5. local-only posts are stored but never delivered ─────────────────────
$d = q("SELECT channel_address nick, channel_id uid FROM channel c WHERE channel_removed = 0
        AND channel_system = 0
        AND (SELECT COUNT(*) FROM abook a
             JOIN channel lc ON lc.channel_hash = a.abook_xchan AND lc.channel_removed = 0
             WHERE a.abook_channel = c.channel_id AND a.abook_self = 0) > 0
        AND (SELECT COUNT(*) FROM abook a
             LEFT JOIN channel lc ON lc.channel_hash = a.abook_xchan AND lc.channel_removed = 0
             WHERE a.abook_channel = c.channel_id AND a.abook_self = 0 AND lc.channel_id IS NULL) = 0
        ORDER BY channel_id LIMIT 1");

if (!$d) {
    echo "SKIP  local-only — no channel with local-only connections to prove non-delivery\n";
} else {
    $dn = $d[0]['nick']; $du = intval($d[0]['uid']);
    echo "\n[local-only] poster $dn\n";
    $saved = get_pconfig($du, 'spa', 'local_only_posts');
    set_pconfig($du, 'spa', 'local_only_posts', 1);

    $res = post($dn, ['scope' => 'public', 'profile_uid' => $du, 'local_only' => true,
        'body' => 'feat localonly ' . bin2hex(random_bytes(4)), 'mimetype' => 'text/bbcode']);
    $lid = iidOf($res);
    $probes[] = $lid;

    $deadline = time() + 60;
    while (time() < $deadline) {
        $w = q('SELECT COUNT(*) AS n FROM workerq');
        if (!is_array($w) || intval($w[0]['n'] ?? 0) === 0) { sleep(3); break; }
        sleep(2);
    }

    if (!$lid) { bad('local-only post was not stored'); }
    else {
        $mid = q('SELECT mid FROM item WHERE id = %d', intval($lid))[0]['mid'];
        intval(get_iconfig($lid, 'spa', 'local_only')) === 1
            ? ok('[local-only] marked in iconfig')
            : bad('[local-only] iconfig marker missing');
        $n = intval(q("SELECT COUNT(DISTINCT uid) AS n FROM item WHERE mid = '%s'", dbesc($mid))[0]['n']);
        $n === 1
            ? ok('[local-only] reached only its own channel')
            : bad("[local-only] reached $n channels — it was delivered",
                  "      local_only must map to nopush so the Notifier never runs\n");
        q("DELETE FROM item WHERE mid = '%s'", dbesc($mid));
    }

    $saved === false
        ? del_pconfig($du, 'spa', 'local_only_posts')
        : set_pconfig($du, 'spa', 'local_only_posts', $saved);
}

foreach (array_filter($probes) as $id) {
    q('DELETE FROM item WHERE id = %d OR parent = %d', intval($id), intval($id));
}
q("DELETE FROM item WHERE uid = %d AND body LIKE 'feat %%'", intval($uid));
echo "\nprobes removed\n";

echo $fail ? "\n$fail check(s) FAILED\n" : "\nall feature checks passed\n";
exit($fail ? 1 : 0);
