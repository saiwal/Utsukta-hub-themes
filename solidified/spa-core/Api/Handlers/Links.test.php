<?php
if (PHP_SAPI !== 'cli') exit;   // deployed into the web root by the build; never runnable over HTTP
/**
 * Links::extractRefs()/refFromUrl() — the parser behind backlinks, the graph
 * and [[Title]] resolution. Pure, no database.
 *
 *   ddev exec php core/extend/theme/utsukta-themes/solidified/spa-core/Api/Handlers/Links.test.php
 */

for ($dir = __DIR__; $dir !== '/'; $dir = dirname($dir)) {
    if (file_exists("$dir/include/cli_startup.php")) { chdir($dir); break; }
}
require_once 'include/cli_startup.php';
cli_startup();
require_once __DIR__ . '/../../../vendor/autoload.php';

use Utsukta\SpaCore\Api\Handlers\Links;

$fail = 0;
function check(string $what, bool $ok): void {
    global $fail;
    echo ($ok ? 'ok   ' : 'FAIL ') . $what . "\n";
    if (!$ok) $fail++;
}

$z = z_root();
$body = "See [[Home Page|home]] and [[Other]].\n"
    . "[url=$z/articles/me/my-slug]a[/url] [url=/cards/me/abc]c[/url]\n"
    . "$z/display/1234-uuid and $z/item/5678-uuid\n"
    . "foreign https://elsewhere.example/display/nope and $z/articles/someone/x\n"
    . "($z/wiki/me/Notes/Page(20)Two) [url=$z/articles/me/series]idx[/url]";

$refs = Links::extractRefs($body, 'me', null);
$flat = array_map(fn($r) => is_array($r) ? $r[1] : $r, $refs);

check('wikilink keeps page, drops label', in_array('t:home page', $flat, true));
check('plain wikilink', in_array('t:other', $flat, true));
check('absolute article link', in_array('a:my-slug', $flat, true));
check('relative card link', in_array('c:abc', $flat, true));
check('display uuid', in_array('u:1234-uuid', $flat, true));
check('item uuid', in_array('u:5678-uuid', $flat, true));
check('wiki page link', in_array('w:Notes/Page(20)Two', $flat, true));
check('foreign host ignored', !in_array('u:nope', $flat, true));
check("other channel's article ignored", !in_array('a:x', $flat, true));
check('series index not an article', !in_array('a:series', $flat, true));
check('no wiki key outside a wiki', $refs[0][0] === null);

if (Links::wikiLoaded()) {
    $w = Links::extractRefs('[[Two Words]]', 'me', 'Notes');
    check('wiki-scoped key inside a wiki', $w[0][0] === 'w:Notes/' . \NativeWiki::name_encode('Two Words'));
}

check('bbcode list debris is not a wikilink', Links::extractRefs("[[i]] one\n[[/i]] two", 'me', null) === []);

check('backlinks path, display', Links::refFromUrl('/display/abc', null)['key'] === 'u:abc');
check('backlinks path, encoded slug', Links::refFromUrl('/articles/me/caf%C3%A9', null)['key'] === 'a:café');
check('backlinks path, list page', Links::refFromUrl('/articles/me', null) === null);

check('untitled snippet strips bbcode', Links::title(['title' => '', 'body' => '[b]Hello[/b] **world**']) === 'Hello world');

exit($fail ? 1 : 0);
