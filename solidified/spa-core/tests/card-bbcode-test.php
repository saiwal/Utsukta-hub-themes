<?php
/**
 * Self-check for the [card] token patterns.
 *
 * Pure string logic — no DB and no Hubzilla bootstrap, so unlike
 * event-terms-test.php this runs standalone in one process:
 *
 *   php packages/spa-core/php/tests/card-bbcode-test.php
 *
 * Exits non-zero on failure.
 *
 * Scope note: a *stored* card embed is a [share] block (see
 * Api/Concerns/EmbedsCards.php), so collapsing it back is Item.php's
 * collapseShareTags — covered by its own share-block scan, not here. What is
 * card-specific, and what this file covers, is the compact [card=<id>] token
 * expandCardTags matches: it must accept only well-formed numeric ids and
 * never hand anything else to the database lookup.
 *
 * The patterns below mirror the trait; if you change one, change both.
 */
declare(strict_types=1);

const EXPAND = '/(\[card=(\d+)\](.*?)\[\/card\])/ism';

function expandIds(string $body): array {
    return preg_match_all(EXPAND, $body, $m) ? $m[2] : [];
}

$fail = 0;
function check(string $name, $got, $want) {
    global $fail;
    if ($got !== $want) { $fail++; printf("FAIL %s\n  got  %s\n  want %s\n", $name, json_encode($got), json_encode($want)); }
    else printf("ok   %s\n", $name);
}

// ── well-formed tokens ───────────────────────────────────────────────────────
check('plain token', expandIds('hi [card=42][/card] there'), ['42']);
check('two tokens', expandIds('[card=1][/card][card=2][/card]'), ['1', '2']);
check('token beside a share', expandIds('[share=7][/share] [card=8][/card]'), ['8']);

// ── malformed ids never reach the DB lookup ──────────────────────────────────
check('non-numeric id ignored', expandIds('[card=abc][/card]'), []);
check('negative id ignored', expandIds('[card=-1][/card]'), []);
check('empty id ignored', expandIds('[card=][/card]'), []);
check('unterminated ignored', expandIds('[card=42] no closer'), []);

// A stored embed is a [share] block, so the expander must not touch one —
// otherwise a re-save would try to re-expand already-expanded content.
check('stored share block untouched', expandIds("[share author='x' link='/cards/a/b']body[/share]"), []);

// ── pathological input must terminate, not backtrack ─────────────────────────
$t = microtime(true);
expandIds('[card=' . str_repeat('1', 100000));
check('unterminated is fast', (microtime(true) - $t) < 1.0, true);

$t = microtime(true);
expandIds(str_repeat('[card=1][/card]', 5000));
check('many tokens are fast', (microtime(true) - $t) < 1.0, true);

exit($fail ? 1 : 0);
