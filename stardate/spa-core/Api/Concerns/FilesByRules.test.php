<?php
/**
 * Self-check for Concerns/FilesByRules. Stubs core's handful of globals so the
 * cursor logic — the part that could silently refile a whole mailbox — is
 * exercised without a database:
 *
 *   php spa-core/Api/Concerns/FilesByRules.test.php
 */

namespace {

if (PHP_SAPI !== 'cli') exit;   // deployed into the web root by the build; never runnable over HTTP

define('TERM_FILE', 5);
define('TERM_OBJ_POST', 1);

$GLOBALS['pconfig'] = [];
$GLOBALS['tagged']  = [];   // [oid, folder] pairs store_item_tag() was asked for

function get_pconfig($uid, $cat, $k, $default = '') { return $GLOBALS['pconfig']["$cat/$k"] ?? $default; }
function set_pconfig($uid, $cat, $k, $v) { $GLOBALS['pconfig']["$cat/$k"] = $v; }
function notags($s) { return str_replace(['<', '>'], '', (string) $s); }
function random_string($n = 8) { return str_repeat('x', $n); }
function prepare_text($body, $mime = '') { return $body; }
function html2plain($s, $len = 0, $exact = false) { return $s; }
function store_item_tag($uid, $oid, $otype, $ttype, $term, $url) { $GLOBALS['tagged'][] = [$oid, $term]; }
function q($sql, ...$args) { return []; }
function dbesc($s) { return $s; }
function datetime_convert() { return '2026-01-01 00:00:00'; }

require_once __DIR__ . '/FilesByRules.php';

class RulesProbe
{
    use Utsukta\SpaCore\Api\Concerns\FilesByRules;
    public function apply(array &$items, int $uid): void { $this->applyInboxRules($items, $uid); }
}

$fail = 0;
function check(string $label, $got, $want): void
{
    global $fail;
    if ($got === $want) { echo "ok    $label\n"; return; }
    $fail++;
    echo "FAIL  $label\n      got  " . json_encode($got) . "\n      want " . json_encode($want) . "\n";
}

$p = new RulesProbe();

// ── validateRules ────────────────────────────────────────────────────────────
$v = RulesProbe::validateRules([
    ['id' => 'a', 'name' => 'Rust',  'folder' => 'Rust',  'expr' => '#rust'],
    ['id' => 'b', 'name' => 'NoDir', 'folder' => '',      'expr' => '#x'],      // no folder
    ['id' => 'c', 'name' => 'NoExp', 'folder' => 'X',     'expr' => '   '],     // no expression
    ['id' => 'd', 'folder' => str_repeat('f', 65), 'expr' => 'x'],              // folder too long
    ['id' => 'e', 'folder' => 'Y', 'expr' => str_repeat('e', 513)],             // expr too long
    'not an array',
]);
check('validate keeps only the usable rule', count($v), 1);
check('validate preserves the expression verbatim', $v[0]['expr'], '#rust');
check('validate defaults enabled to true', $v[0]['enabled'], true);
check('validate strips tags from the folder',
    RulesProbe::validateRules([['folder' => '<b>F', 'expr' => 'x']])[0]['folder'], 'bF');
check('validate honours an explicit disable',
    RulesProbe::validateRules([['folder' => 'F', 'expr' => 'x', 'enabled' => false]])[0]['enabled'], false);
check('validate caps the list', count(RulesProbe::validateRules(
    array_fill(0, 40, ['folder' => 'F', 'expr' => 'x']))), RulesProbe::RULES_MAX);

// ── applyInboxRules ──────────────────────────────────────────────────────────
$GLOBALS['pconfig']['spa/inbox_rules'] = json_encode([
    ['id' => 'r1', 'name' => 'Rust', 'folder' => 'Rust', 'expr' => 'rust'],
    ['id' => 'r2', 'name' => 'Off',  'folder' => 'Nope', 'expr' => 'rust', 'enabled' => false],
]);

$mk = fn($id, $created, $body, $terms = []) =>
    ['id' => $id, 'parent' => $id, 'created' => $created, 'body' => $body, 'term' => $terms];

// First run: no cursor. Adopt the high-water mark, file nothing — otherwise
// saving a rule would refile the entire mailbox on the next page load.
$items = [$mk(1, '2026-01-10 00:00:00', 'about rust')];
$p->apply($items, 7);
check('first run files nothing', $GLOBALS['tagged'], []);
check('first run adopts the newest created', $GLOBALS['pconfig']['spa/rules_cursor'], '2026-01-10 00:00:00');

// Newer post matching an enabled rule gets filed; the disabled one is ignored.
$GLOBALS['tagged'] = [];
$items = [$mk(2, '2026-01-11 00:00:00', 'more rust'), $mk(3, '2026-01-12 00:00:00', 'cats')];
$p->apply($items, 7);
check('matching post is filed once', $GLOBALS['tagged'], [[2, 'Rust']]);
check('cursor advances to the newest seen', $GLOBALS['pconfig']['spa/rules_cursor'], '2026-01-12 00:00:00');
check('filed folder is visible in the same response',
    array_column($items[0]['term'], 'term'), ['Rust']);

// Same items again: everything is at or below the cursor, so nothing re-files.
// This is what stops a post you unfiled by hand coming straight back.
$GLOBALS['tagged'] = [];
$p->apply($items, 7);
check('a second pass re-files nothing', $GLOBALS['tagged'], []);

// A post already carrying the folder is skipped even when newer than the cursor.
$GLOBALS['tagged'] = [];
$items = [$mk(4, '2026-01-13 00:00:00', 'rust again',
    [['ttype' => TERM_FILE, 'term' => 'Rust']])];
$p->apply($items, 7);
check('already-filed post is not re-tagged', $GLOBALS['tagged'], []);

// Sender rules read the flat keys the trait injects from $item['author'],
// which is an array and so unreachable by MessageFilter's ?field tester.
$GLOBALS['pconfig'] = ['spa/rules_cursor' => '2026-01-01 00:00:00'];
$GLOBALS['pconfig']['spa/inbox_rules'] = json_encode([
    ['id' => 'n', 'folder' => 'FromAlice', 'expr' => '?filter_author_name ~= Alice'],
    ['id' => 'a', 'folder' => 'AliceAddr', 'expr' => '?filter_author_addr == alice@hub.de'],
]);
$GLOBALS['tagged'] = [];
$items = [
    $mk(6, '2026-01-20 00:00:00', 'hello') + ['author' => ['xchan_name' => 'Alice B', 'xchan_addr' => 'alice@hub.de']],
    $mk(7, '2026-01-21 00:00:00', 'hello') + ['author' => ['xchan_name' => 'Bob', 'xchan_addr' => 'bob@hub.de']],
];
$p->apply($items, 7);
check('sender rules match on name and address', $GLOBALS['tagged'],
    [[6, 'FromAlice'], [6, 'AliceAddr']]);

// An item with no author array must not fatal or match an empty-string rule.
$GLOBALS['pconfig']['spa/rules_cursor'] = '2026-01-01 00:00:00';
$GLOBALS['tagged'] = [];
$items = [$mk(8, '2026-01-22 00:00:00', 'hello')];
$p->apply($items, 7);
check('missing author is harmless', $GLOBALS['tagged'], []);

// No rules at all must cost nothing and never touch the cursor.
$GLOBALS['pconfig'] = [];
$GLOBALS['tagged'] = [];
$items = [$mk(5, '2026-02-01 00:00:00', 'rust')];
$p->apply($items, 7);
check('no rules: nothing filed', $GLOBALS['tagged'], []);
check('no rules: no cursor written', isset($GLOBALS['pconfig']['spa/rules_cursor']), false);

echo $fail ? "\n$fail check(s) failed\n" : "\nFilesByRules: ok\n";
exit($fail ? 1 : 0);

}

namespace Zotlabs\Lib {
    /** Matches when the expression appears verbatim in the item body. Enough to
     *  drive the trait; the real matcher is core's and tested by core. */
    class MessageFilter
    {
        public function __construct(private $item, private $incl, private $excl, private $opts = []) {}
        public function evaluate(): bool
        {
            // "?filter_author_name ~= x" / "?filter_author_addr == x", enough to
            // prove the trait injected the flat keys; the real operator table is
            // core's and tested by core.
            if (preg_match('/^\?(\S+) (~=|==) (.*)$/', $this->incl, $m)) {
                $v = (string) ($this->item[$m[1]] ?? '');
                return $m[2] === '==' ? $v === $m[3] : str_contains($v, $m[3]);
            }
            return str_contains($this->item['body'] ?? '', $this->incl);
        }
    }
}
