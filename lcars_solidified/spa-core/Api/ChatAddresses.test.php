<?php
if (PHP_SAPI !== 'cli') exit;   // deployed into the web root by the build; never runnable over HTTP
/**
 * ChatAddresses: validation, own list, private vCard IMPP round-trip (other
 * IMPP survives), view_profile gate and the encode_person actor fields.
 * Restores the pconfig/abconfig it touches.
 *
 *   ddev exec php core/extend/theme/utsukta-themes/solidified/spa-core/Api/ChatAddresses.test.php [nick] [other nick]
 */

for ($dir = __DIR__; $dir !== '/'; $dir = dirname($dir)) {
    if (file_exists("$dir/include/cli_startup.php")) { chdir($dir); break; }
}
require_once 'include/cli_startup.php';
cli_startup();
require_once __DIR__ . '/../../vendor/autoload.php';

use Utsukta\SpaCore\Api\ChatAddresses as C;

$fail = 0;
function check(string $what, bool $ok): void {
    global $fail;
    echo ($ok ? 'ok   ' : 'FAIL ') . $what . "\n";
    if (!$ok) $fail++;
}

// ── normalize ───────────────────────────────────────────────────────────────
check('matrix id', C::normalize('matrix', ' @Al.ice:matrix.org ')['href'] === 'https://matrix.to/#/@Al.ice:matrix.org');
check('matrix.to link', C::normalize('matrix', 'https://matrix.to/#/@a:b.org')['value'] === '@a:b.org');
check('matrix: uri', C::normalize('matrix', 'matrix:u/a:b.org?action=chat')['value'] === '@a:b.org');
check('matrix with port', C::normalize('matrix', '@a:b.org:8448') !== null);
check('fediverse handle is not matrix', C::normalize('matrix', '@a@b.org') === null);
check('matrix rejects markup', C::normalize('matrix', '@a:b.org"><script>') === null);
check('deltachat email', C::normalize('deltachat', 'mailto:a@b.org')['href'] === 'mailto:a@b.org');
check('deltachat invite', C::normalize('deltachat', 'https://i.delta.chat/#ABC&a=x%40y.org')['href'] === 'https://i.delta.chat/#ABC&a=x%40y.org');
check('deltachat rejects javascript', C::normalize('deltachat', 'javascript:alert(1)') === null);
check('unknown type', C::normalize('xmpp', 'a@b.org') === null);
check('classify by label', C::classify('Delta Chat', 'a@b.org')['type'] === 'deltachat');
check('clean dedupes and drops junk', count(C::clean([
    ['type' => 'matrix', 'value' => '@a:b.org'], ['type' => 'matrix', 'value' => '@a:b.org'],
    ['type' => 'matrix', 'value' => 'nope'], 'junk',
])) === 1);

// ── fixtures ────────────────────────────────────────────────────────────────
$me = channelx_by_nick($argv[1] ?? 'aristotle');
$other = channelx_by_nick($argv[2] ?? 'plato');
if (!$me || !$other) { fwrite(STDERR, "usage: ChatAddresses.test.php <nick> <other nick>\n"); exit(2); }
$uid = intval($me['channel_id']);
$hash = $other['channel_hash'];
$oldOwn = get_pconfig($uid, 'spa', 'chat_ids');
$oldOtherOwn = get_pconfig($other['channel_id'], 'spa', 'chat_ids');
$oldVcard = get_abconfig($uid, $hash, 'system', 'vcard');

try {
    // ── own list ────────────────────────────────────────────────────────────
    C::setOwn($uid, [['type' => 'matrix', 'value' => 'https://matrix.to/#/@me:ex.org'], ['type' => 'deltachat', 'value' => 'bad']]);
    $own = C::own($uid);
    check('own list stored clean', count($own) === 1 && $own[0]['value'] === '@me:ex.org');

    // ── private vCard: replaces only matrix/deltachat IMPP ─────────────────
    $card = new \Sabre\VObject\Component\VCard(['FN' => 'X']);
    $card->add('IMPP', 'xmpp:x@y.org', ['type' => 'xmpp']);
    $card->add('IMPP', '@old:ex.org', ['type' => 'matrix']);
    set_abconfig($uid, $hash, 'system', 'vcard', $card->serialize());
    C::setPrivate($uid, $hash, [['type' => 'deltachat', 'value' => 'p@ex.org']]);
    $vc = \Sabre\VObject\Reader::read(get_abconfig($uid, $hash, 'system', 'vcard'));
    $impps = array_map('strval', $vc->select('IMPP'));
    check('private: xmpp kept, old matrix gone, new deltachat added',
        in_array('xmpp:x@y.org', $impps) && !in_array('@old:ex.org', $impps) && in_array('p@ex.org', $impps));

    // ── forXchan merges sources ─────────────────────────────────────────────
    set_pconfig($other['channel_id'], 'spa', 'chat_ids', [['type' => 'matrix', 'value' => '@other:ex.org']]);
    $all = C::forXchan($uid, $hash, $me['channel_hash']);
    $by = array_column($all, 'source', 'value');
    check('forXchan: private entry', ($by['p@ex.org'] ?? '') === 'private');
    $viewable = perm_is_allowed($other['channel_id'], '', 'view_profile');
    $anon = C::forXchan(0, $hash, '');
    check('visitor never sees private entries', !in_array('private', array_column($anon, 'source')));
    check('visitor sees published entry iff profile is public',
        in_array('@other:ex.org', array_column($anon, 'value')) === (bool) $viewable);

    // ── bulk Matrix list for the new-DM picker ──────────────────────────────
    C::setPrivate($uid, $hash, [['type' => 'matrix', 'value' => '@priv:ex.org']]);
    $bulk = array_column(C::matrixForConnections($uid, $me['channel_hash']), 'value');
    $connected = (bool) q("SELECT abook_id FROM abook WHERE abook_channel = %d AND abook_xchan = '%s'", $uid, dbesc($hash));
    check('bulk list has the private Matrix id', in_array('@priv:ex.org', $bulk));
    if ($connected) check('bulk list has a connection\'s published id', in_array('@other:ex.org', $bulk) === (bool) $viewable);

    // ── encode_person ───────────────────────────────────────────────────────
    $arr = ['xchan' => ['xchan_hash' => $hash], 'encoded' => []];
    C::encodePerson($arr);
    $names = array_column($arr['encoded']['attachment'] ?? [], 'name');
    check('encode_person adds Matrix field iff profile is public', in_array('Matrix', $names) === (bool) $viewable);
} finally {
    $oldOwn === null ? del_pconfig($uid, 'spa', 'chat_ids') : set_pconfig($uid, 'spa', 'chat_ids', $oldOwn);
    $oldOtherOwn === null ? del_pconfig($other['channel_id'], 'spa', 'chat_ids') : set_pconfig($other['channel_id'], 'spa', 'chat_ids', $oldOtherOwn);
    $oldVcard ? set_abconfig($uid, $hash, 'system', 'vcard', $oldVcard) : del_abconfig($uid, $hash, 'system', 'vcard');
}

echo $fail ? "$fail failed\n" : "all ok\n";
exit($fail ? 1 : 0);
