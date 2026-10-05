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

if (!empty($argv[1])) {
    $out = run(['body' => "RE: {$argv[1]}\r\n\r\nhi", 'mimetype' => 'text/bbcode']);
    check('live: quote pasted as a share block', str_contains($out, '[/share]') && !str_contains($out, 'RE: '));
    echo substr($out, 0, 400), "\n";
}

echo "\n" . ($fail ? "$fail check(s) failed\n" : "all checks passed\n");
exit($fail ? 1 : 0);
