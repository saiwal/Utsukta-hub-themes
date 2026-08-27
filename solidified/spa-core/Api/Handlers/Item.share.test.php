<?php
/**
 * Check for the [share=<id>] expansion split in Item::expandShareTags.
 *
 * App items (articles, cards) must build their block via buildShareBlock, so
 * the block's link is the item's app page (/articles/<nick>/<slug>) — that
 * string is the only thing both bbcode renderers key off to label the embed an
 * article rather than a post. Ordinary posts must still go through core
 * Zotlabs\Lib\Share and link at their plink.
 *
 * Run inside the Hubzilla install (ids are discovered, nothing is hardcoded):
 *   ddev exec php core/extend/theme/utsukta-themes/solidified/spa-core/Api/Handlers/Item.share.test.php
 */

// Walk up to the Hubzilla root — this file runs from the deployed theme, whose
// depth below it depends on the theme slug.
for ($dir = __DIR__; $dir !== '/'; $dir = dirname($dir)) {
    if (file_exists("$dir/include/cli_startup.php")) { chdir($dir); break; }
}
require_once('include/cli_startup.php');
cli_startup();

$fail = 0;
function check(string $label, bool $cond): void {
    global $fail;
    if ($cond) { echo "ok    $label\n"; return; }
    $fail++; echo "FAIL  $label\n";
}

// A public bbcode article, and the channel that owns it.
$a = q("SELECT * FROM item WHERE item_type = %d AND item_private = 0 AND item_deleted = 0
        AND mimetype = 'text/bbcode' AND title != '' ORDER BY id DESC LIMIT 1",
    intval(ITEM_TYPE_ARTICLE));
if (!$a) { echo "SKIP  no public article in this database\n"; exit(0); }
$article = $a[0];

$c = q("SELECT * FROM channel WHERE channel_id = %d LIMIT 1", intval($article['uid']));
$x = q("SELECT * FROM xchan WHERE xchan_hash = '%s' LIMIT 1", dbesc($c[0]['channel_hash']));

// expandShareTags runs behind local_channel()/item_permissions_sql, neither of
// which returns anything useful in a bare CLI — emulate the session.
@session_start();
$_SESSION['authenticated'] = 1;
$_SESSION['uid'] = intval($article['uid']);
App::$channel  = $c[0];
App::$observer = $x[0];

$base = dirname(__DIR__) . '/';
foreach (glob($base . 'Concerns/*.php') as $f) require_once($f);
require_once($base . 'Response.php');
require_once($base . 'Auth.php');
require_once($base . 'Handlers/Item.php');

$expand = new ReflectionMethod(\Utsukta\SpaCore\Api\Handlers\Item::class, 'expandShareTags');
$expand->setAccessible(true);
$handler = new \Utsukta\SpaCore\Api\Handlers\Item();

// 1. The article embeds as a share block pointing at its article page.
$out = $expand->invoke($handler, '[share=' . intval($article['id']) . '][/share]');
preg_match("/link='([^']*)'/", $out, $m);
$link = trim($m[1] ?? '');
echo "  article link = $link\n";

check('article expands to a [share] block',  str_starts_with(trim($out), '[share author='));
check('article links at /articles/<nick>/',  str_starts_with($link, z_root() . '/articles/' . $c[0]['channel_address'] . '/'));
check('article title kept in [h3][b]',       str_contains($out, '[h3][b]'));
check('full article body inlined',           $article['body'] && str_contains($out, $article['body']));
// quote='true' would make Activity::encode_item replace the whole block with
// "RE: <link>" and federate that link as quoteUrl. An app page is not an
// AS-resolvable object, so a remote receiver would render bare text.
check('article block is NOT marked quote',   !str_contains($out, "quote='true'"));

// 2. No regression: an ordinary post still links at its plink.
$p = q("SELECT * FROM item WHERE item_type = %d AND item_private = 0 AND item_deleted = 0
        AND mimetype = 'text/bbcode' AND plink != '' AND body NOT LIKE '%%[/share]%%'
        AND uid = %d ORDER BY id DESC LIMIT 1",
    intval(ITEM_TYPE_POST), intval($article['uid']));

if (!$p) {
    echo "SKIP  no ordinary post to compare against\n";
} else {
    $out2 = $expand->invoke($handler, '[share=' . intval($p[0]['id']) . '][/share]');
    preg_match("/link='([^']*)'/", $out2, $m2);
    echo "  post link    = " . trim($m2[1] ?? '') . "\n";
    check('ordinary post still links at its plink', trim($m2[1] ?? '') === trim($p[0]['plink']));

    // The quote path is still wanted for ordinary posts — their plink is an
    // AS object a remote can fetch.
    $net = q("SELECT xchan_network FROM xchan WHERE xchan_hash = '%s' LIMIT 1", dbesc($p[0]['author_xchan']));
    if ($net && in_array($net[0]['xchan_network'], ['zot6', 'activitypub'])) {
        check('ordinary post still marked quote', str_contains($out2, "quote='true'"));
    }
}

echo $fail ? "\n$fail check(s) FAILED\n" : "\nall passed\n";
exit($fail ? 1 : 0);
