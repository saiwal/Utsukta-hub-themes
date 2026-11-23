<?php
namespace Utsukta\SpaCore\Api {
    if (!class_exists(Response::class)) {
        class Response { public static function error($s, $m) { throw new \RuntimeException("$s $m"); } }
    }
}

namespace {
if (PHP_SAPI !== 'cli') exit;   // deployed into the web root by the build; never runnable over HTTP
/**
 * Self-check for Concerns/StreamFilters' combination rules. Stubs Hubzilla's
 * globals, so no database:
 *
 *   php spa-core/Api/Concerns/StreamFilters.test.php
 *
 * Pins the two the network filter widget is laid out around: post types OR
 * (event + poll once silently dropped poll), and dm=1 always applies (with
 * star/search/… it used to vanish along with the default privacy fence, so
 * "DMs + Starred" listed every starred post, public included).
 */
if (!function_exists('dbesc')) {
    function dbesc($s) { return str_replace("'", "''", $s); }
    function protect_sprintf($s) { return str_replace('%', '%%', $s); }
    function notags($s) { return $s; }
    function is_a_date_arg($s) { return false; }
}
require_once __DIR__ . '/StreamFilters.php';
use Utsukta\SpaCore\Api\Concerns\StreamFilters;

$fail = 0;
function check(string $label, bool $ok, string $sql): void
{
    global $fail;
    if ($ok) { echo "ok    $label\n"; return; }
    $fail++;
    echo "FAIL  $label\n      sql  $sql\n";
}
$extra = fn(array $q) => StreamFilters::build($q, 1, ['alias' => 'i', 'channel_hash' => 'me'])['extra'];

$x = $extra(['verb' => '.Event,.Question']);
check('event + poll is OR', str_contains($x, "i.obj_type IN ('Event','Question')"), $x);

$x = $extra(['verb' => '.Event']);
check('one type still filters', str_contains($x, "i.obj_type IN ('Event')"), $x);

$x = $extra(['verb' => "Like,.Event"]);
check('verb + type OR together', str_contains($x, " OR i.verb IN ('Like')"), $x);

$x = $extra(['verb' => ".Ev'il"]);
check('type value escaped', str_contains($x, "'Ev''il'"), $x);

$x = $extra(['dm' => '1', 'star' => '1']);
check('dm + star keeps DMs only', str_contains($x, 'i.item_private = 2'), $x);

$x = $extra(['dm' => '1', 'verb' => '.Event']);
check('dm + type keeps DMs only', str_contains($x, 'i.item_private = 2'), $x);

$x = $extra([]);
check('default fence hides DMs', str_contains($x, 'i.item_private IN (0, 1)'), $x);

$x = $extra(['star' => '1']);
check('star still lifts the default fence', !str_contains($x, 'item_private'), $x);

echo $fail ? "\n$fail failed\n" : "\nall ok\n";
exit($fail ? 1 : 0);
}
