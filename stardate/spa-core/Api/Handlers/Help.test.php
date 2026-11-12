<?php
if (PHP_SAPI !== 'cli') exit;   // deployed into the web root by the build; never runnable over HTTP
/**
 * Self-check for Help's grouped-folder resolution: bare page names ("hq", as
 * help mode and cross-links use them) must still find pages moved into
 * numbered group folders. Pure, runs against the user docs next to it:
 *
 *   php spa-core/Api/Handlers/Help.test.php [path/to/docs/user/en]
 */

require_once __DIR__ . '/Help.php';
use Utsukta\SpaCore\Api\Handlers\Help;

$fail = 0;
function check(string $label, $got, $want): void
{
    global $fail;
    if ($got === $want) { echo "ok    $label\n"; return; }
    $fail++;
    echo "FAIL  $label\n      got  " . json_encode($got) . "\n      want " . json_encode($want) . "\n";
}

// Deployed: <theme>/spa-core/Api/Handlers → <theme>/docs. Source tree: pass the path.
$base = $argv[1] ?? __DIR__ . '/../../../docs/user/en';
if (!is_dir($base)) { echo "no docs at $base\n"; exit(1); }

$h    = new Help();
$call = fn(string $m, ...$a) => (new ReflectionMethod(Help::class, $m))->invoke($h, ...$a);
$topic = fn(string $t) => ($f = $call('resolveFile', $base, $t)) ? $call('fileToTopic', $base, $f) : null;

check('bare name finds grouped page', $topic('hq'), '2-social/hq');
check('full path still works',        $topic('2-social/hq'), '2-social/hq');
check('wrong folder, right name',     $topic('3-your-content/settings'), '1-getting-around/settings');
check('unknown page',                 $topic('nope'), null);
check('index is the section root',      $topic('index'), '');
check('group label drops number',     $call('slugToLabel', '3-your-content'), 'Your Content');

$tree = $call('buildTree', $base, $base, 'user', 'en');
$slugs = array_column($tree, 'slug');
$sorted = $slugs; sort($sorted);
check('sidebar is only numbered groups, in order',
    [count(preg_grep('/^\d+-/', $slugs)) === count($slugs), $slugs], [true, $sorted]);

// Every help-mode target module must still resolve.
foreach (['calendar', 'cart', 'nav', 'network', 'notifications', 'widgets'] as $m) {
    check("help target '$m' resolves", $topic($m) !== null, true);
}

exit($fail ? 1 : 0);
