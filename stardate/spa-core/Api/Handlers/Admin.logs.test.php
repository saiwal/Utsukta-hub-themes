<?php
if (PHP_SAPI !== 'cli') exit;   // deployed into the web root by the build; never runnable over HTTP
/**
 * Self-check for Admin::parseLogChunk (continuation-line folding). Pure:
 *
 *   php spa-core/Api/Handlers/Admin.logs.test.php
 */

require_once __DIR__ . '/Admin.php';
use Utsukta\SpaCore\Api\Handlers\Admin;

$fail = 0;
function check(string $label, $got, $want): void
{
    global $fail;
    if ($got === $want) { echo "ok    $label\n"; return; }
    $fail++;
    echo "FAIL  $label\n      got  " . json_encode($got) . "\n      want " . json_encode($want) . "\n";
}

$raw = "Array\n"
     . "2026-10-18T12:00:00+00:00:LOG_INFO:abc123:Queue.php:88:deliver: hello: world\n"
     . "Array\n(\n    [a] => 1\n)\n"
     . "2026-10-18T12:00:01Z:LOG_ERR:def456:Libzot.php:12:process: boom\n";
$e = Admin::parseLogChunk($raw);

check('orphan + 2 entries',       count($e), 3);
check('leading orphan stands',    [$e[0]['level'], $e[0]['message']], ['LOG_UNDEFINED', 'Array']);
check('message keeps colons',     strtok($e[1]['message'], "\n"), 'hello: world');
check('dump folded into parent',  $e[1]['message'], "hello: world\nArray\n(\n    [a] => 1\n)");
check('fields parsed',            [$e[2]['level'], $e[2]['logid'], $e[2]['file'], $e[2]['line'], $e[2]['fn']],
                                  ['LOG_ERR', 'def456', 'Libzot.php', 12, 'process']);
check('empty input',              Admin::parseLogChunk(''), []);

exit($fail ? 1 : 0);
