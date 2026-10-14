<?php
if (PHP_SAPI !== 'cli') exit;   // deployed into the web root by the build; never runnable over HTTP
/**
 * Checks for Handlers\JellyfinNowPlaying — who may see a channel's "now
 * playing", and what a pushed track is reduced to before it's stored.
 *
 * Uses a channel that has at least one approved connection, so the
 * "connections" audience is tested against a real abook row. Writes nothing
 * it doesn't restore.
 *
 *   ddev exec php core/extend/theme/utsukta-themes/solidified/spa-core/Api/Handlers/JellyfinNowPlaying.test.php
 */

for ($dir = __DIR__; $dir !== '/'; $dir = dirname($dir)) {
    if (file_exists("$dir/include/cli_startup.php")) { chdir($dir); break; }
}
require_once('include/cli_startup.php');
cli_startup();

$base = dirname(__DIR__) . '/';
require_once($base . 'Response.php');
require_once($base . 'Handlers/JellyfinNowPlaying.php');
use Utsukta\SpaCore\Api\Handlers\JellyfinNowPlaying as NP;

$fail = 0;
function check(string $label, bool $cond): void {
    global $fail;
    if ($cond) { echo "ok    $label\n"; return; }
    $fail++; echo "FAIL  $label\n";
}

// ── sanitizeTrack ────────────────────────────────────────────────────────────
$tiny = 'data:image/jpeg;base64,' . base64_encode(str_repeat('x', 100));
$t = NP::sanitizeTrack(['title' => '  <b>Song</b> ', 'artist' => 'A', 'album' => 'B', 'cover' => $tiny, 'extra' => 'dropped']);
check('title trimmed and tags stripped', $t['title'] === 'Song');
check('only known fields kept', array_keys($t) === ['title', 'artist', 'album', 'cover']);
check('small base64 image cover kept', $t['cover'] === $tiny);
check('missing title → rejected', NP::sanitizeTrack(['title' => '', 'artist' => 'A']) === null);
check('non-array → rejected', NP::sanitizeTrack('nope') === null);
check('remote URL cover dropped (no tracking pixels)',
    NP::sanitizeTrack(['title' => 'x', 'cover' => 'https://evil.example/p.gif'])['cover'] === '');
check('svg data URL dropped (scriptable)',
    NP::sanitizeTrack(['title' => 'x', 'cover' => 'data:image/svg+xml;base64,PHN2Zz4='])['cover'] === '');
check('oversized cover dropped',
    NP::sanitizeTrack(['title' => 'x', 'cover' => 'data:image/png;base64,' . str_repeat('A', 30000)])['cover'] === '');
check('long title capped at 200 chars', mb_strlen(NP::sanitizeTrack(['title' => str_repeat('é', 500)])['title']) === 200);

// ── visibleTo ────────────────────────────────────────────────────────────────
$r = q("SELECT channel_id, channel_hash, abook_xchan FROM channel
        JOIN abook ON abook_channel = channel_id
        WHERE channel_removed = 0 AND abook_self = 0 AND abook_blocked = 0 AND abook_pending = 0
        LIMIT 1");
if (!$r) {
    echo "SKIP  visibility: no channel with an approved connection in this database\n";
} else {
    $uid = intval($r[0]['channel_id']);
    $owner = $r[0]['channel_hash'];
    $friend = $r[0]['abook_xchan'];
    $stranger = 'not-a-connection-' . random_string(8);

    check('off: hidden even from the owner', !NP::visibleTo('off', $uid, $owner, $owner));
    check('self: owner sees it', NP::visibleTo('self', $uid, $owner, $owner));
    check('self: a connection does not', !NP::visibleTo('self', $uid, $owner, $friend));
    check('connections: a connection sees it', NP::visibleTo('connections', $uid, $owner, $friend));
    check('connections: a stranger does not', !NP::visibleTo('connections', $uid, $owner, $stranger));
    check('connections: anonymous does not', !NP::visibleTo('connections', $uid, $owner, ''));
    check('public: anonymous sees it', NP::visibleTo('public', $uid, $owner, ''));

    // audience() falls back to "off" for anything unrecognised, and round-trips
    $before = get_pconfig($uid, 'spa', 'jf_np_audience', null);
    set_pconfig($uid, 'spa', 'jf_np_audience', 'bogus');
    check('unknown stored audience reads as off', NP::audience($uid) === 'off');
    set_pconfig($uid, 'spa', 'jf_np_audience', 'connections');
    check('stored audience round-trips', NP::audience($uid) === 'connections');
    if ($before === null) del_pconfig($uid, 'spa', 'jf_np_audience');
    else set_pconfig($uid, 'spa', 'jf_np_audience', $before);
}

echo $fail ? "\n$fail check(s) FAILED\n" : "\nall passed\n";
exit($fail ? 1 : 0);
