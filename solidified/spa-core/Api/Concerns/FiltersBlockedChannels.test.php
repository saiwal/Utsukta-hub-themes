<?php
namespace Zotlabs\Lib {
    if (!class_exists(Apps::class)) {
        class Apps { public static function addon_app_installed($uid, $app) { return $GLOBALS['APP_INSTALLED']; } }
    }
}

namespace {
if (PHP_SAPI !== 'cli') exit;   // deployed into the web root by the build; never runnable over HTTP
/**
 * Self-check for Concerns/FiltersBlockedChannels. Stubs Hubzilla's globals, so
 * no database:
 *
 *   php spa-core/Api/Concerns/FiltersBlockedChannels.test.php
 *
 * Pins both superblock storage formats — the array form is what 500'd every
 * stream endpoint when explode() met it — plus the addon parity rules: gated
 * on the app, timed blocks expire, reshares of a blocked channel are blocked.
 */

$PCONFIG = [];
$APP_INSTALLED = true;
$XCHAN_URLS = [];
$ADDON_ENABLED = true;
if (!function_exists('get_pconfig')) {
    function get_pconfig($uid, $cat, $key) { return $GLOBALS['PCONFIG']["$cat.$key"] ?? false; }
    function plugin_is_installed($name) { return $GLOBALS['ADDON_ENABLED']; }
    function dbesc($s) { return str_replace("'", "''", $s); }
    function q($sql, ...$args) {
        if (str_contains($sql, 'xchan_url FROM')) {
            return array_map(fn($u) => ['xchan_url' => $u], array_values($GLOBALS['XCHAN_URLS']));
        }
        $url = $args[0] ?? '';
        $hash = array_search($url, $GLOBALS['XCHAN_URLS'], true);
        return $hash !== false ? [['xchan_hash' => $hash]] : [];
    }
}

require_once __DIR__ . '/FiltersBlockedChannels.php';

class BlockProbe
{
    use Utsukta\SpaCore\Api\Concerns\FiltersBlockedChannels;

    public function hashes(int $uid): array            { return $this->blockedXchans($uid); }
    public function settings(int $uid): array          { return $this->superblockSettings($uid); }
    public function shares(string $b, array $bl): bool { return $this->bodySharesBlocked($b, $bl, 1); }
    public function shareSql(array $bl): string        { return $this->blockedShareSqlClause('item.body', $bl, 1); }
}

$fail = 0;

function check(string $label, $got, $want): void
{
    global $fail;
    if ($got === $want) { echo "ok    $label\n"; return; }
    $fail++;
    echo "FAIL  $label\n      got  " . json_encode($got) . "\n      want " . json_encode($want) . "\n";
}

// Fresh probe per case: superblockActive() memoises per uid.
$p = fn() => new BlockProbe();

// ── storage formats ──────────────────────────────────────────────────────────
check('unset',              $p()->hashes(1), []);
check('no uid',             $p()->hashes(0), []);

$PCONFIG['system.blocked'] = ' a, ,b ';
check('legacy string',      $p()->hashes(1), ['a', 'b']);

$future = gmdate('Y-m-d\TH:i:sO', time() + 86400);
$past   = gmdate('Y-m-d\TH:i:sO', time() - 86400);
$PCONFIG['system.blocked'] = [['hash' => 'a'], ['hash' => 'b', 'until' => $future], ['hash' => 'c', 'until' => $past], ['nohash' => 1], 'junk'];
check('array form, expired + malformed dropped', $p()->hashes(1), ['a', 'b']);

// ── gating, as the addon gates itself ────────────────────────────────────────
$APP_INSTALLED = false;
check('app not installed -> nothing applies', $p()->hashes(1), []);
$APP_INSTALLED = true;
$ADDON_ENABLED = false;
check('addon disabled -> nothing applies',    $p()->hashes(1), []);
$ADDON_ENABLED = true;

// ── settings ─────────────────────────────────────────────────────────────────
check('settings default on', $p()->settings(1), ['block_reshares' => true, 'block_incoming' => true]);
$PCONFIG['superblock.settings'] = ['block_reshares' => false];
check('settings stored',     $p()->settings(1), ['block_reshares' => false, 'block_incoming' => true]);

// ── reshares (Superblock::filterItem / filterByProfileUrl) ──────────────────
$XCHAN_URLS = ['a' => 'https://x.example/channel/bad'];
$share = "hi [share author='Bad' profile='https://x.example/channel/bad' link='l']quoted[/share]";
check('reshare off -> not blocked', $p()->shares($share, ['a']), false);
check('reshare off -> no sql',      $p()->shareSql(['a']), '');

unset($PCONFIG['superblock.settings']);
check('reshare by url',           $p()->shares($share, ['a']), true);
check('reshare by hash',          $p()->shares("[share profile='a']x[/share]", ['a']), true);
check('reshare of someone else',  $p()->shares("[share profile='https://ok/c/fine']x[/share]", ['a']), false);
check('no share',                 $p()->shares('plain [b]post[/b]', ['a']), false);
check('sql names hash and url',   $p()->shareSql(['a']),
    " AND (item.body NOT LIKE '%[share%' OR (item.body NOT LIKE '%profile=''a''%' AND item.body NOT LIKE '%profile=''https://x.example/channel/bad''%')) ");

exit($fail ? 1 : 0);
}
