<?php
/**
 * Self-check for the [card] token regexes in Api/Concerns/EmbedsCards.php.
 *
 * Pure string logic — no DB and no Hubzilla bootstrap, so unlike
 * event-terms-test.php this runs standalone in one process:
 *
 *   php packages/spa-core/php/tests/card-bbcode-test.php
 *
 * Exits non-zero on failure. Covers the compact/expanded token forms and the
 * malformed-input cases: non-numeric ids, the compact form leaking into the
 * collapse pass, a hostile title, and unterminated input (backtracking).
 *
 * The patterns below mirror the trait; if you change one, change both.
 */
declare(strict_types=1);

// Mirrors of the three patterns used by the trait.
const EXPAND   = '/(\[card=(\d+)\](.*?)\[\/card\])/ism';
const COLLAPSE = '/\[card\s[^\]]*\]\s*\[\/card\]/is';
const MIDATTR  = "/\smid='([^']*)'/is";

function expandIds(string $body): array {
    return preg_match_all(EXPAND, $body, $m) ? $m[2] : [];
}
function collapseBlocks(string $body): array {
    return preg_match_all(COLLAPSE, $body, $m) ? $m[0] : [];
}

$fail = 0;
function check(string $name, $got, $want) {
    global $fail;
    if ($got !== $want) { $fail++; printf("FAIL %s\n  got  %s\n  want %s\n", $name, json_encode($got), json_encode($want)); }
    else printf("ok   %s\n", $name);
}

// ── expand side ──────────────────────────────────────────────────────────────
check('plain token', expandIds('hi [card=42][/card] there'), ['42']);
check('two tokens', expandIds('[card=1][/card][card=2][/card]'), ['1','2']);
// Non-numeric id must not match at all — it is left as literal text, never
// passed to the DB lookup.
check('non-numeric id ignored', expandIds('[card=abc][/card]'), []);
check('negative id ignored', expandIds('[card=-1][/card]'), []);
check('empty id ignored', expandIds('[card=][/card]'), []);

// ── collapse side ────────────────────────────────────────────────────────────
$block = "[card\n\tmid='https%3A%2F%2Fh%2Fitem%2Fabc'\n\ttitle='Hi'\n][/card]";
check('block matched', collapseBlocks($block), [$block]);
preg_match(MIDATTR, $block, $mm);
check('mid extracted', urldecode($mm[1]), 'https://h/item/abc');

// The compact form must NOT be picked up by the collapse pattern (it requires
// whitespace after "[card"), so a body mid-edit is never double-collapsed.
check('compact not collapsed', collapseBlocks('[card=42][/card]'), []);

// A title containing bracket/quote characters cannot break out, because
// buildCardBlock urlencode()s every value. Simulate a hostile title.
$hostile = "[card\n\tmid='x'\n\ttitle='" . urlencode("]['card= \" ' evil") . "'\n][/card]";
check('hostile title: one block', collapseBlocks($hostile), [$hostile]);
preg_match(MIDATTR, $hostile, $hm);
check('hostile title: mid intact', $hm[1], 'x');

// Nested-looking input must terminate and not recurse (regex, so it cannot
// hang) — the inner literal is urlencoded in real output, but check the raw
// pathological case anyway.
$t0 = microtime(true);
$nested = "[card mid='a' title='[card mid=b][/card]'][/card]";
$got = collapseBlocks($nested);
check('nested-ish terminates', count($got) >= 1 && (microtime(true) - $t0) < 1.0, true);

// Long pathological input must not blow up (catastrophic backtracking guard).
$t1 = microtime(true);
collapseBlocks('[card ' . str_repeat('a', 200000));
check('unterminated is fast', (microtime(true) - $t1) < 1.0, true);

exit($fail ? 1 : 0);
