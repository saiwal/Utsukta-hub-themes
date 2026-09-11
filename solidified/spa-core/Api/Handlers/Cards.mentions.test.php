<?php
/**
 * Backlink check for Cards::afterSingle — the card's "Mentioned in" list.
 *
 * Two things have to line up for it to work at all: the article save has to
 * expand the composer's compact [card=<id>] token (ItemCollection::
 * expandEmbedTokens — Cards did this from the start, Articles did not, and an
 * unexpanded token carries no message_id, so the mention was invisible), and
 * the backlink query has to match on that message_id rather than on the
 * block's link attribute, which carries a slug and changes when the card is
 * renamed. Both are asserted here, plus the item_normal_search() gate: a
 * delayed article must not show up in anybody's list.
 *
 * Creates a real card and a real article, deletes both again.
 *
 * Run inside the Hubzilla install:
 *   ddev exec php core/extend/theme/utsukta-themes/solidified/spa-core/Api/Handlers/Cards.mentions.test.php
 *
 * Response::send()/error() exit, so each handler call is its own process —
 * the driver below re-executes this file per step.
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

const SLUG_C  = 'mentions-test-card';
const SLUG_A  = 'mentions-test-article';
const TITLE_C = 'Mentions probe card';
const TITLE_A = 'Mentions probe article';

// ---------------------------------------------------------------------------
// Step mode — authenticate as $nick and run one handler call.
// ---------------------------------------------------------------------------

if (($argv[1] ?? '') === 'step') {
    [$cls, $nick, $verb, $a3] = [$argv[2], $argv[3], $argv[4], $argv[5]];
    $payload = json_decode($argv[6] ?? '{}', true) ?: [];

    session_id('mentions-test');

    $ch = q("SELECT * FROM channel WHERE channel_address = '%s' LIMIT 1", dbesc($nick));
    $_SESSION['uid']              = intval($ch[0]['channel_id']);
    $_SESSION['authenticated']    = 1;
    $_SESSION['solidified_csrf']  = 'test-token';
    $_SERVER['HTTP_X_CSRF_TOKEN'] = 'test-token';
    $_SERVER['CONTENT_TYPE']      = 'multipart/form-data';
    $_POST                        = $payload;

    \App::$channel  = $ch[0];
    $x = q("SELECT * FROM xchan WHERE xchan_hash = '%s' LIMIT 1", dbesc($ch[0]['channel_hash']));
    \App::$observer = $x[0];
    \App::$argv = ['spa', strtolower($cls), $nick, $a3 === '-' ? '' : $a3];
    \App::$argc = 4;

    $fqcn = "Utsukta\\SpaCore\\Api\\Handlers\\$cls";
    (new $fqcn)->$verb();
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
echo "channel: $nick\n";

$fail = 0;
function check(string $label, $got, $want = true): void
{
    global $fail;
    if ($got === $want) { echo "ok    $label\n"; return; }
    $fail++;
    echo "FAIL  $label\n      got  " . json_encode($got) . "\n      want " . json_encode($want) . "\n";
}

function step(string $cls, string $nick, string $verb, string $a3, array $payload = []): array
{
    $cmd = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__FILE__) . ' step '
        . escapeshellarg($cls) . ' ' . escapeshellarg($nick) . ' ' . escapeshellarg($verb) . ' '
        . escapeshellarg($a3 ?: '-') . ' ' . escapeshellarg(json_encode($payload));
    $out = shell_exec($cmd . ' 2>&1');
    $j   = json_decode((string) $out, true);
    if (!is_array($j)) {
        echo "      raw: " . substr((string) $out, 0, 400) . "\n";
        return ['error' => ['message' => 'unparseable response']];
    }
    return $j;
}

function cleanup(): void
{
    $r = q("SELECT id FROM item WHERE title IN ('%s', '%s')", dbesc(TITLE_A), dbesc(TITLE_C));
    foreach ($r ?: [] as $row) {
        $iid = intval($row['id']);
        q("DELETE FROM iconfig WHERE iid = %d", $iid);
        q("DELETE FROM item WHERE id = %d OR parent = %d", $iid, $iid);
    }
}

cleanup();

$card = step('Cards', $nick, 'post', '', [
    'body'     => 'Probe card body.',
    'title'    => TITLE_C,
    'slug'     => SLUG_C,
    'mimetype' => 'text/bbcode',
]);
$cid = intval($card['data']['iid'] ?? 0);
check('card created', $cid > 0);
if (!$cid) { echo json_encode($card) . "\n"; cleanup(); exit(1); }

$article = step('Articles', $nick, 'post', '', [
    'body'     => "Before.\n[card=$cid][/card]\nAfter.",
    'title'    => TITLE_A,
    'slug'     => SLUG_A,
    'lang'     => 'en',
    'mimetype' => 'text/bbcode',
]);
$aid = intval($article['data']['iid'] ?? 0);
check('article created', $aid > 0);
if (!$aid) { echo json_encode($article) . "\n"; cleanup(); exit(1); }

// The token must be gone from what was stored, replaced by a block naming the
// card's mid — that message_id is what the backlink query matches on.
$stored = q("SELECT body FROM item WHERE id = %d LIMIT 1", $aid)[0]['body'] ?? '';
$mid    = q("SELECT mid FROM item WHERE id = %d LIMIT 1", $cid)[0]['mid'] ?? '';
check('compact token expanded on save', !str_contains($stored, "[card=$cid]"));
check('stored block names the card mid', str_contains($stored, "message_id='" . $mid . "'"));

$got = step('Cards', $nick, 'get', SLUG_C)['data']['card'] ?? [];
check('mention listed', array_column($got['mentioned_in'] ?? [], 'title'), [TITLE_A]);
check('mention links to the article',
    str_ends_with(($got['mentioned_in'][0]['view_url'] ?? ''), '/articles/' . $nick . '/' . SLUG_A));

// A slug rename must not break the backlink — the match is on the mid, which
// does not move.
step('Cards', $nick, 'post', '', [
    'body'     => 'Probe card body.',
    'title'    => TITLE_C,
    'slug'     => SLUG_C . '-renamed',
    'mimetype' => 'text/bbcode',
    'post_id'  => $cid,
]);
$got = step('Cards', $nick, 'get', SLUG_C . '-renamed')['data']['card'] ?? [];
check('mention survives a slug rename', count($got['mentioned_in'] ?? []), 1);

// item_normal_search(): a delayed article is nobody's business, the owner's
// included.
q("UPDATE item SET item_delayed = 1 WHERE id = %d", $aid);
$got = step('Cards', $nick, 'get', SLUG_C . '-renamed')['data']['card'] ?? [];
check('delayed article is not listed', $got['mentioned_in'] ?? null, []);
q("UPDATE item SET item_delayed = 0 WHERE id = %d", $aid);

cleanup();
echo "\n" . ($fail ? "$fail check(s) failed\n" : "all checks passed\n");
exit($fail ? 1 : 0);
