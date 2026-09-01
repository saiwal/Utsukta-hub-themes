<?php
/**
 * Validity checks for the SPA's RSS generator (Handlers\Feed).
 *
 * The two failures this guards against, both seen in the wild:
 *   - the handler used to echo and return, so Hubzilla appended its whole HTML
 *     page after </rss> and the feed wasn't even well-formed XML;
 *   - bodyless activity rows (verb Add/Remove) produced <item>s with neither a
 *     title nor a description, which RSS 2.0 forbids.
 *
 * Run inside the Hubzilla install (channel is discovered, nothing hardcoded):
 *   ddev exec php core/extend/theme/utsukta-themes/solidified/spa-core/Api/Handlers/Feed.test.php
 */

for ($dir = __DIR__; $dir !== '/'; $dir = dirname($dir)) {
    if (file_exists("$dir/include/cli_startup.php")) { chdir($dir); break; }
}
require_once('include/cli_startup.php');
cli_startup();
require_once('include/security.php');

$fail = 0;
function check(string $label, bool $cond): void {
    global $fail;
    if ($cond) { echo "ok    $label\n"; return; }
    $fail++; echo "FAIL  $label\n";
}

// A channel that actually has wall posts, so the item loop is exercised.
$c = q("SELECT channel_address, COUNT(item.id) AS n FROM channel
        JOIN item ON item.uid = channel.channel_id
        WHERE channel_removed = 0 AND item.item_wall = 1 AND item.item_thread_top = 1
        GROUP BY channel.channel_id, channel_address ORDER BY n DESC LIMIT 1");
if (!$c) { echo "SKIP  no channel with wall posts in this database\n"; exit(0); }
$nick = $c[0]['channel_address'];
echo "  channel = $nick ({$c[0]['n']} wall posts)\n";

$channel = channelx_by_nick($nick);
$rows = dbq("SELECT item.uuid, item.mid, item.title, item.body, item.created, item.plink, item.author_xchan
             FROM item
             WHERE item.uid = " . intval($channel['channel_id']) . "
               AND item.item_thread_top = 1 AND item.item_wall = 1
               AND item.verb NOT IN ('Add', 'Remove')
               " . item_permissions_sql($channel['channel_id'], '') . "
               " . item_normal($channel['channel_id'], 'item', ITEM_TYPE_POST) . "
             ORDER BY item.created DESC LIMIT 30");

$base = dirname(__DIR__) . '/';
require_once($base . 'Response.php');
require_once($base . 'Handlers/Feed.php');

$m = new ReflectionMethod(\Utsukta\SpaCore\Api\Handlers\Feed::class, 'render');
$m->setAccessible(true);
$xml = $m->invoke(new \Utsukta\SpaCore\Api\Handlers\Feed(), $channel, $rows ?: [], 'posts');

check('ends at </rss> (no HTML page appended)', str_ends_with(trim($xml), '</rss>'));

libxml_use_internal_errors(true);
$doc = simplexml_load_string($xml);
check('well-formed XML', $doc !== false);
if ($doc === false) {
    foreach (libxml_get_errors() as $e) { echo "      " . trim($e->message) . "\n"; }
    echo "\n$fail check(s) FAILED\n";
    exit(1);
}

$items = $doc->channel->item;
echo "  items   = " . count($items) . "\n";
$empty = 0;
foreach ($items as $item) {
    if (trim((string)$item->title) === '' && trim(strip_tags((string)$item->description)) === '') {
        $empty++;
    }
}
check('every item has a title or a description', $empty === 0);
check('channel has title, link, description',
    (string)$doc->channel->title !== '' && (string)$doc->channel->link !== '' && (string)$doc->channel->description !== '');
check('atom:self link present',
    count($doc->channel->children('http://www.w3.org/2005/Atom')->link) === 1);

echo $fail ? "\n$fail check(s) FAILED\n" : "\nall passed\n";
exit($fail ? 1 : 0);
