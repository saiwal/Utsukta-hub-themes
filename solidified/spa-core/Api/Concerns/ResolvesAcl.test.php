<?php
/**
 * Self-check for Concerns/ResolvesAcl. Pure string/array work — no Hubzilla,
 * no database:
 *
 *   php spa-core/Api/Concerns/ResolvesAcl.test.php
 */

require_once __DIR__ . '/ResolvesAcl.php';

class AclProbe
{
    use Utsukta\SpaCore\Api\Concerns\ResolvesAcl;

    public function scope(string $s, array $b, string $o): array { return $this->aclFromScope($s, $b, $o); }
    public function composer(array $i, string $o): array         { return $this->aclFromComposerInput($i, $o); }
    public function parse(string $s): array                      { return self::parseHashList($s); }
    public function pack(array $h): string                       { return self::packHashList($h); }
}

$p    = new AclProbe();
$fail = 0;

function check(string $label, $got, $want): void
{
    global $fail;
    if ($got === $want) { echo "ok    $label\n"; return; }
    $fail++;
    echo "FAIL  $label\n      got  " . json_encode($got) . "\n      want " . json_encode($want) . "\n";
}

// ── parse/pack round-trip ────────────────────────────────────────────────────
check('parse empty',       $p->parse(''),            []);
check('parse two',         $p->parse('<a><b>'),      ['a', 'b']);
check('pack two',          $p->pack(['a', 'b']),     '<a><b>');
check('pack drops empties', $p->pack(['a', '', 'b']), '<a><b>');
check('round-trip',        $p->parse($p->pack(['x', 'y'])), ['x', 'y']);

// ── aclFromScope: named scopes (Blocks, Webpages) ───────────────────────────
// 'connections' must be item_private WITHOUT hashes: the 'contacts' policy is
// what item_permissions_sql() checks via scopes_sql(). Emitting hashes instead
// would pin the item to whoever was a contact at save time.
check('scope connections', $p->scope('connections', [], 'me'), ['', '', '', '', 1, 'contacts']);
check('scope private',     $p->scope('private', [], 'me'),     ['<me>', '', '', '', 1, '']);
check('scope public',      $p->scope('public', [], 'me'),      ['', '', '', '', 0, '']);
check('scope unknown falls back to public', $p->scope('', [], 'me'), ['', '', '', '', 0, '']);

check('scope custom allow', $p->scope('custom', ['allow_cid' => ['a'], 'deny_gid' => ['g']], 'me'),
    ['<a>', '', '', '<g>', 1, '']);
// Deny-only is NOT private: nothing was restricted *to* anyone.
check('scope custom deny only', $p->scope('custom', ['deny_cid' => ['a']], 'me'),
    ['', '', '<a>', '', 0, '']);
check('scope custom empty', $p->scope('custom', [], 'me'), ['', '', '', '', 0, '']);

// ── aclFromComposerInput: explicit lists (Articles, Cards) ──────────────────
check('composer private wins over everything',
    $p->composer(['scope' => 'private', 'contact_allow' => ['x'], 'public_policy' => 'contacts'], 'me'),
    ['<me>', '', '', '', 1, '']);
check('composer public', $p->composer([], 'me'), ['', '', '', '', 0, '']);
check('composer lists',
    $p->composer(['contact_allow' => ['a', 'b'], 'group_allow' => ['g'],
                  'contact_deny' => ['d'], 'group_deny' => ['h']], 'me'),
    ['<a><b>', '<g>', '<d>', '<h>', 1, '']);
// public_policy 'contacts' alone makes it private, with no hashes.
check('composer contacts policy', $p->composer(['public_policy' => 'contacts'], 'me'),
    ['', '', '', '', 1, 'contacts']);
// Any other policy (site, network, …) is a scope, not privacy.
check('composer site policy', $p->composer(['public_policy' => 'site'], 'me'),
    ['', '', '', '', 0, 'site']);
check('composer deny only is not private', $p->composer(['contact_deny' => ['d']], 'me'),
    ['', '', '<d>', '', 0, '']);
// Scalars where arrays are expected must not fatal.
check('composer tolerates scalars', $p->composer(['contact_allow' => 'a'], 'me'),
    ['<a>', '', '', '', 1, '']);

echo $fail ? "\n$fail check(s) failed\n" : "\nall checks passed\n";
exit($fail ? 1 : 0);
