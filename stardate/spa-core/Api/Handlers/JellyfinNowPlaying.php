<?php
namespace Utsukta\SpaCore\Api\Handlers;

use Utsukta\SpaCore\Api\Auth;
use Utsukta\SpaCore\Api\Response;

/**
 * "Now playing" for the profile widget of the SPA's Jellyfin player.
 *
 * Jellyfin itself is unreachable for visitors (it's the owner's server, often
 * on a home LAN), so the owner's browser pushes what it's playing here and
 * visitors read it back. Opt-in: nothing is stored until the owner picks an
 * audience other than "off".
 *
 *   GET  /spa/jellyfin-nowplaying?channel=<nick>
 *        → { data: { track, paused, updated } | null }, or null when the
 *          observer isn't in the chosen audience, or the state is stale.
 *   GET  /spa/jellyfin-nowplaying            (local channel)
 *        → { data: { audience } } — the owner's own setting.
 *   POST /spa/jellyfin-nowplaying  { audience: off|self|connections|public }
 *   POST /spa/jellyfin-nowplaying  { track: {title, artist, album, cover}|null, paused }
 *
 * Stored in pconfig cat "spa": jf_np_audience, jf_np_state (JSON). `cover` is
 * a small data: URL thumbnail rendered in the browser — re-hosting covers as
 * photos would fill the channel's albums with one image per album played.
 */
class JellyfinNowPlaying
{
    public const AUDIENCES = ['off', 'self', 'connections', 'public'];

    /** A browser that stopped heartbeating (closed tab, lost network) stops counting as "now". */
    public const STALE_SECONDS = 1200;

    private const TEXT_MAX = 200;
    private const COVER_MAX = 24576;

    public function get(): void
    {
        $nick = trim((string) ($_GET['channel'] ?? ''));

        if ($nick === '') {
            $uid = Auth::requireLocalGet();
            Response::send(['audience' => self::audience($uid)]);
        }

        $ch = channelx_by_nick($nick);
        if (!$ch || !empty($ch['channel_removed'])) Response::error(404, 'Channel not found');
        $uid = intval($ch['channel_id']);

        if (!self::visibleTo(self::audience($uid), $uid, $ch['channel_hash'], (string) get_observer_hash())) {
            Response::send(null);
        }

        $state = json_decode((string) get_pconfig($uid, 'spa', 'jf_np_state', ''), true);
        if (!is_array($state) || empty($state['track'])
            || time() - intval($state['updated'] ?? 0) > self::STALE_SECONDS) {
            Response::send(null);
        }
        Response::send($state);
    }

    public function post(): void
    {
        $uid = Auth::requireLocalJson();
        $body = Auth::$parsedBody;

        if (array_key_exists('audience', $body)) {
            $a = (string) $body['audience'];
            if (!in_array($a, self::AUDIENCES, true)) Response::error(400, 'Invalid audience');
            set_pconfig($uid, 'spa', 'jf_np_audience', $a);
            // Switching off forgets what was last shared
            if ($a === 'off') del_pconfig($uid, 'spa', 'jf_np_state');
            Response::send(['audience' => $a]);
        }

        if (!array_key_exists('track', $body)) Response::error(400, 'Missing track');
        if (self::audience($uid) === 'off') Response::send(['stored' => false]);

        $track = $body['track'] === null ? null : self::sanitizeTrack($body['track']);
        if ($track === null) {
            del_pconfig($uid, 'spa', 'jf_np_state');
            Response::send(['stored' => true]);
        }
        set_pconfig($uid, 'spa', 'jf_np_state', json_encode([
            'track'   => $track,
            'paused'  => !empty($body['paused']),
            'updated' => time(),
        ]));
        Response::send(['stored' => true]);
    }

    public static function audience(int $uid): string
    {
        $a = (string) get_pconfig($uid, 'spa', 'jf_np_audience', 'off');
        return in_array($a, self::AUDIENCES, true) ? $a : 'off';
    }

    /**
     * Whether `$observer` (an xchan hash, '' for anonymous) may see channel
     * `$uid`'s now-playing under `$audience`. The owner always may, except
     * when it's off. "connections" = an approved, unblocked connection.
     */
    public static function visibleTo(string $audience, int $uid, string $ownerHash, string $observer): bool
    {
        if ($audience === 'off') return false;
        if ($observer !== '' && $observer === $ownerHash) return true;
        if ($audience === 'public') return true;
        if ($audience !== 'connections' || $observer === '') return false;
        $r = q("SELECT abook_id FROM abook WHERE abook_channel = %d AND abook_xchan = '%s'
                AND abook_blocked = 0 AND abook_pending = 0 AND abook_self = 0 LIMIT 1",
            $uid, dbesc($observer));
        return (bool) $r;
    }

    /** Text fields capped, cover kept only if it's a small base64 image data: URL. */
    public static function sanitizeTrack($t): ?array
    {
        if (!is_array($t)) return null;
        $text = fn($v) => mb_substr(trim(strip_tags((string) ($v ?? ''))), 0, self::TEXT_MAX);
        $title = $text($t['title'] ?? '');
        if ($title === '') return null;
        $cover = (string) ($t['cover'] ?? '');
        if (strlen($cover) > self::COVER_MAX
            || !preg_match('#^data:image/(jpeg|png|webp);base64,[A-Za-z0-9+/=]+$#', $cover)) {
            $cover = '';
        }
        return [
            'title'  => $title,
            'artist' => $text($t['artist'] ?? ''),
            'album'  => $text($t['album'] ?? ''),
            'cover'  => $cover,
        ];
    }
}
