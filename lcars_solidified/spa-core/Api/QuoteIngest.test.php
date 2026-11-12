<?php
if (PHP_SAPI !== 'cli') exit;   // deployed into the web root by the build; never runnable over HTTP
/**
 * Guard paths of QuoteIngest::onPostRemote — all offline. A live fetch is a
 * manual check: pass a real quote url as argv[1].
 *
 *   ddev exec php core/extend/theme/utsukta-themes/solidified/spa-core/Api/QuoteIngest.test.php [url]
 */
for ($dir = __DIR__; $dir !== '/'; $dir = dirname($dir)) {
    if (file_exists("$dir/include/cli_startup.php")) { chdir($dir); break; }
}
require_once 'include/cli_startup.php';
cli_startup();
require_once __DIR__ . '/../../vendor/autoload.php';

use Utsukta\SpaCore\Api\QuoteIngest;

$fail = 0;
function check(string $label, bool $ok): void
{
    global $fail;
    echo ($ok ? 'ok  ' : 'FAIL') . "  $label\n";
    if (!$ok) $fail++;
}
function run(array $arr): string { QuoteIngest::onPostRemote($arr); return $arr['body']; }

$b = "RE: https://127.0.0.1/item/x\r\nhi";
check('private host is never fetched', run(['body' => $b, 'mimetype' => 'text/bbcode']) === $b);
$b = "RE: https://example.com/item/x";
check('local-origin item is left alone', run(['body' => $b, 'mimetype' => 'text/bbcode', 'item_origin' => 1]) === $b);
check('non-bbcode body is left alone', run(['body' => $b, 'mimetype' => 'text/markdown']) === $b);
$b = "RE: https://example.com/item/x [share]q[/share]";
check('body with a share already is left alone', run(['body' => $b, 'mimetype' => 'text/bbcode']) === $b);

// Fallback: the sender's rendered content (trimmed from a real hub.hubzilla.hu
// quote-post whose quoted item the origin 404s).
$u   = 'https://hub.hubzilla.hu/item/af51fabb-c8f5-4922-be6d-e0e4a404787a';
$obj = json_encode(['type' => 'Note', 'content' => 'RE:<div id="shared_container_1" class="shared_container"><div id="shared_header_1" class="shared_header"><a href="https://hub.hubzilla.hu/channel/hz-workshop"><img src="https://hub.hubzilla.hu/photo/profile/s/35" alt="Hubzilla Workshop" height="32" width="32" /></a><span><a href="https://hub.hubzilla.hu/channel/hz-workshop">Hubzilla Workshop</a> wrote the following <a href="' . $u . '">post </a><span class="autotime" title="2026-10-02T12:23:12+02:00">Fri</span></span></div><div id="reshared-content-1" class="reshared-content"><strong>Hubzilla Workshop #12</strong><br /><ul class="listbullet"><li>Kanalkalender</li></ul></div></div>']);
$q = QuoteIngest::quoteFromObj($obj, $u);
check('obj fallback builds a share block', str_contains($q['bbcode'] ?? '', "[share author='Hubzilla+Workshop'"));
check('obj fallback keeps the quoted content', str_contains($q['bbcode'] ?? '', 'Hubzilla Workshop #12') && str_contains($q['bbcode'], 'Kanalkalender'));
check('obj fallback converts posted to UTC', str_contains($q['bbcode'] ?? '', "posted='2026-10-02 10:23:12'"));
$out = \Zotlabs\Lib\Activity::pasteQuote("RE: $u\n\n#tag", $q);
check('obj fallback replaces the RE: line', !str_contains($out, 'RE: ') && str_ends_with($out, '#tag'));
check('obj without a shared_container gives nothing', QuoteIngest::quoteFromObj(['content' => '<p>hi</p>'], $u) === []);
$evil = str_replace('hz-workshop"><img', "hz-workshop' avatar='x\"><img", $obj);
check("a ' in a remote attribute can't open a new one", !str_contains(QuoteIngest::quoteFromObj(json_decode($evil, true), $u)['bbcode'] ?? '', "avatar='x"));

if (!empty($argv[1])) {
    $out = run(['body' => "RE: {$argv[1]}\r\n\r\nhi", 'mimetype' => 'text/bbcode']);
    check('live: quote pasted as a share block', str_contains($out, '[/share]') && !str_contains($out, 'RE: '));
    echo substr($out, 0, 400), "\n";
}

echo "\n" . ($fail ? "$fail check(s) failed\n" : "all checks passed\n");
exit($fail ? 1 : 0);
