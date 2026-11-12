<?php
if (PHP_SAPI !== 'cli') exit;   // deployed into the web root by the build; never runnable over HTTP
/**
 * Wiki::extractMermaid() tokens must survive core's bbcode() + smilies()
 * untouched, and come back as a language-mermaid block (core alone drops the
 * language). No database writes.
 *
 *   ddev exec php core/extend/theme/utsukta-themes/solidified/spa-core/Api/Handlers/Wiki.mermaid.test.php
 */

for ($dir = __DIR__; $dir !== '/'; $dir = dirname($dir)) {
    if (file_exists("$dir/include/cli_startup.php")) { chdir($dir); break; }
}
require_once 'include/cli_startup.php';
cli_startup();
require_once __DIR__ . '/../../../vendor/autoload.php';

use Utsukta\SpaCore\Api\Handlers\Wiki;

$fail = 0;
function check(string $what, bool $ok): void {
    global $fail;
    echo ($ok ? 'ok   ' : 'FAIL ') . $what . "\n";
    if (!$ok) $fail++;
}

// Stored bbcode is already escaped, hence --&gt;.
$src = "intro :)\n[code=mermaid]\ngraph TD\n    A--&gt;B\n[/code]\n[code=php]echo 1;[/code]";
[$tokenised, $diagrams] = Wiki::extractMermaid($src);
check('one diagram extracted', count($diagrams) === 1);
check('php block left for core', str_contains($tokenised, '[code=php]'));

$html = strtr(smilies(bbcode($tokenised, ['tryoembed' => false])), $diagrams);
check('token survived bbcode', !str_contains($html, 'spamermaid'));
check('language-mermaid emitted', str_contains($html, '<pre><code class="language-mermaid">graph TD'));
check('not double-escaped', str_contains($html, 'A--&gt;B') && !str_contains($html, '&amp;gt;'));
check('indentation kept', str_contains($html, "\n    A--&gt;B"));

exit($fail ? 1 : 0);
