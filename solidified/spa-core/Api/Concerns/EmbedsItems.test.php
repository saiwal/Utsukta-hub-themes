<?php
/**
 * Contract check for Concerns/EmbedsItems — the [share] and [card] embeds.
 *
 * The two tags share one builder but not one privacy rule: [share] refuses a
 * private item outright, while [card] additionally allows the owner's own
 * private card. That asymmetry is the whole reason the builders were separate
 * copies, each carrying a comment asking the reader to keep them in agreement,
 * so it is what this pins down — along with the link attribute, which is the
 * only thing that makes a bbcode renderer label a block a card or an article
 * rather than a post.
 *
 * No rows are written: the item arrays are synthesised around a real channel,
 * so every combination of item_type × item_private can be exercised whatever
 * the database happens to hold.
 *
 * Run inside the Hubzilla install:
 *   ddev exec php core/extend/theme/utsukta-themes/solidified/spa-core/Api/Concerns/EmbedsItems.test.php
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

$fail = 0;
function check(string $label, $got, $want = true): void
{
    global $fail;
    if ($got === $want) { echo "ok    $label\n"; return; }
    $fail++;
    $t = fn($v) => substr(is_string($v) ? $v : json_encode($v), 0, 110);
    echo "FAIL  $label\n      got  " . $t($got) . "\n      want " . $t($want) . "\n";
}

// Two real channels: one owns the synthetic items, the other is a bystander.
$chans = q("SELECT * FROM channel WHERE channel_removed = 0 AND channel_system = 0
            ORDER BY channel_id LIMIT 2");
if (count($chans) < 2) {
    echo "SKIP  need two local channels in this database\n";
    exit(0);
}
[$owner, $other] = $chans;

/** Act as $ch, so local_channel() and the owner-private exception see them. */
function actAs(array $ch): void
{
    session_id('embedsitems-test');
    $_SESSION['uid'] = intval($ch['channel_id']);
    $_SESSION['authenticated'] = 1;
    \App::$channel = $ch;
    $x = q("SELECT * FROM xchan WHERE xchan_hash = '%s' LIMIT 1", dbesc($ch['channel_hash']));
    \App::$observer = $x ? $x[0] : [];
}

/** A plausible item row owned by $owner — never stored. */
function row(array $owner, int $itemType, int $private, string $mimetype = 'text/bbcode'): array
{
    $uuid = 'embedstest-' . $itemType . '-' . $private;
    return [
        // id 0 deliberately: appItemLink()'s slug lookup must miss and fall
        // back to the uuid, which keeps the expected link deterministic.
        'id'            => 0,
        'uuid'          => $uuid,
        'uid'           => intval($owner['channel_id']),
        'item_type'     => $itemType,
        'item_private'  => $private,
        'mimetype'      => $mimetype,
        'title'         => '',
        'body'          => 'the shared body',
        'created'       => '2026-01-01 00:00:00',
        'mid'           => z_root() . '/item/' . $uuid,
        'plink'         => z_root() . '/item/' . $uuid,
        'owner_xchan'   => $owner['channel_hash'],
        'author_xchan'  => $owner['channel_hash'],
    ];
}

$build = new ReflectionMethod(Utsukta\SpaCore\Api\Handlers\Item::class, 'buildEmbedBlock');
$build->setAccessible(true);
$h = new Utsukta\SpaCore\Api\Handlers\Item();
/** @param bool $forDisplay @param bool $ownPrivateOk */
$block = fn(array $item, bool $forDisplay = false, bool $ownPrivateOk = false)
    => $build->invoke($h, $item, $forDisplay, $ownPrivateOk);

$POST = 0;
$CARD = ITEM_TYPE_CARD;
$ART  = ITEM_TYPE_ARTICLE;

// ── The link attribute: what labels the block ───────────────────────────────
actAs($owner);
$nick = $owner['channel_address'];

check('a post links at its plink',
    str_contains($block(row($owner, $POST, 0)), "link='" . z_root() . "/item/embedstest-0-0'"));
check('an article links at its app page',
    str_contains($block(row($owner, $ART, 0)), "link='" . z_root() . "/articles/$nick/embedstest-$ART-0'"));
check('a card links at its app page',
    str_contains($block(row($owner, $CARD, 0)), "link='" . z_root() . "/cards/$nick/embedstest-$CARD-0'"));

// quote='true' federates the block as a quoteUrl, which only resolves for an
// ordinary post's plink — an app item must send its block inline instead.
check('a post may be quote-federated',
    str_contains($block(row($owner, $POST, 0)), "quote='true'"));
check('an article is never quote-federated',
    !str_contains($block(row($owner, $ART, 0)), "quote='true'"));
check('a card is never quote-federated',
    !str_contains($block(row($owner, $CARD, 0)), "quote='true'"));

// ── Only bbcode can be embedded ─────────────────────────────────────────────
check('a markdown body is not embeddable', $block(row($owner, $POST, 0, 'text/markdown')), '');
check('a plain body is not embeddable',    $block(row($owner, $POST, 0, 'text/plain')), '');

// ── The privacy asymmetry ───────────────────────────────────────────────────
// Save time, as the owner:
check('[share] refuses the owner\'s own private post',
    $block(row($owner, $POST, 1)), '');
check('[share] refuses the owner\'s own private card',
    $block(row($owner, $CARD, 1)), '');
check('[card] allows the owner\'s own private card',
    $block(row($owner, $CARD, 1), false, true) !== '');

// The relaxation is the card tag's, and must not reach anything else — even
// if a future caller passes the card path a post id.
check('[card] cannot relax a private post',
    $block(row($owner, $POST, 1), false, true), '');
check('[card] cannot relax a private article',
    $block(row($owner, $ART, 1), false, true), '');

// Save time, as a bystander: someone else's private card stays out.
actAs($other);
check('[card] refuses someone else\'s private card',
    $block(row($owner, $CARD, 1), false, true), '');
check('[share] still refuses someone else\'s private post',
    $block(row($owner, $POST, 1)), '');

// Preview mode may render anything the viewer already sees — nothing is
// stored, so nothing can leak onward.
check('preview renders a private post',
    $block(row($owner, $POST, 1), true) !== '');
check('preview renders someone else\'s private card',
    $block(row($owner, $CARD, 1), true) !== '');

// ── A card with no resolvable channel has no usable block ───────────────────
// The plink fallback would render it as an ordinary post, losing the only
// signal that says "card".
actAs($owner);
$orphan = row($owner, $CARD, 0);
$orphan['uid'] = 0;
check('a card with an unresolvable channel is refused', $block($orphan), '');

// ── permittedItemById re-reads behind the ACL ───────────────────────────────
$perm = new ReflectionMethod(Utsukta\SpaCore\Api\Handlers\Item::class, 'permittedItemById');
$perm->setAccessible(true);
check('a missing id resolves to null', $perm->invoke($h, 999999999), null);

$card = q("SELECT id FROM item WHERE item_type = %d AND item_deleted = 0 LIMIT 1",
    intval(ITEM_TYPE_CARD));
$post = q("SELECT id FROM item WHERE item_type = 0 AND item_deleted = 0 LIMIT 1");
if ($card && $post) {
    check('the type constraint rejects a post id',
        $perm->invoke($h, intval($post[0]['id']), ITEM_TYPE_CARD), null);
    check('the type constraint accepts a card id',
        $perm->invoke($h, intval($card[0]['id']), ITEM_TYPE_CARD) !== null);
} else {
    echo "SKIP  no card or no post in this database\n";
}

echo "\n" . ($fail ? "$fail check(s) failed\n" : "all checks passed\n");
exit($fail ? 1 : 0);
