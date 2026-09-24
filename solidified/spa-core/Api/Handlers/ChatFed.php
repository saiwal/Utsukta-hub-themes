<?php
/**
 * Utsukta\SpaCore\Api\Handlers\ChatFed
 *
 * Chatrooms don't federate: a room lives only on its owner's hub. This is the
 * hub-to-hub side channel that tells a channel on another SPA hub that a room
 * it bookmarked has new messages, so its unread dot works there too. Both hubs
 * must run spa-core; a plain Hubzilla hub never answers and nothing changes.
 *
 *   POST /spa/chatfed/subscribe  { room_url }                     your hub → owner's hub
 *   POST /spa/chatfed/notice     { room_url, created, recipient, sender_name? }  owner's hub → your hub
 *
 * Both are server-to-server: no session, no CSRF — an HTTP signature by a zot
 * channel (keyId = channel_url, as Libzot signs) is the only authentication.
 *
 * There is no unsubscribe. A notice for a room the recipient no longer has
 * bookmarked answers 404 and the owner's hub drops the subscription; one wasted
 * notice is the whole cost.
 *
 * Storage (pconfig, one key per pair so concurrent writes can't clobber):
 *   owner's hub  spa_chat_subs        "<cr_id>.<md5(xchan)>" → subscriber xchan
 *   your hub     spa_chat_last        md5(room url)          → newest notice time (UTC)
 *   your hub     spa_chat_subscribed  md5(room url)          → last subscribe attempt
 *   your hub     spa_chat_push        md5(room url)          → 1 = Web Push on notice (opt-in)
 *
 * Background work (subscribing, sending notices) runs in core's QueueWorker via
 * the `daemon_addon` hook — see the theme's hooks/chatfed.php.
 */

namespace Utsukta\SpaCore\Api\Handlers;

use App;
use Zotlabs\Lib\Cache;
use Zotlabs\Lib\QueueWorker;
use Zotlabs\Web\HTTPSig;
use Utsukta\SpaCore\Api\Response;

class ChatFed
{
    /** At most one notice per room per subscriber in this window. */
    // ponytail: a message inside the window after you've opened the room raises
    // no new dot; shrink the window, or have the receiver ask for a resend, if
    // that turns out to matter.
    const THROTTLE = '5 MINUTE';

    /** Re-subscribe remote bookmarks this often (covers core-made bookmarks). */
    const RESUBSCRIBE_DAYS = 7;

    public function post(): void
    {
        $raw    = file_get_contents('php://input') ?: '';
        $signer = self::verifiedSigner($raw);
        if (!$signer)
            Response::error(401, 'Valid HTTP signature required');

        $data = json_decode($raw, true);
        $room = is_array($data) ? self::parseRoomUrl((string)($data['room_url'] ?? '')) : null;
        if (!$room)
            Response::error(400, 'room_url required');

        switch (App::$argv[2] ?? '') {
            case 'subscribe':
                $this->subscribe($signer, $room);
            case 'notice':
                $this->notice($signer, $room, $data);
            default:
                Response::error(404, 'Unknown chatfed route');
        }
    }

    // ── Owner's hub ───────────────────────────────────────────────────────────

    private function subscribe(string $signer, array $room): never
    {
        // Same 404 for "no such room" and "you may not see it", so subscribing
        // can't be used to probe for private rooms.
        $cr = $room['host'] === self::localHost() ? self::visibleRoom($room, $signer) : null;
        if (!$cr)
            Response::error(404, 'Room not found');

        set_pconfig(intval($cr['cr_uid']), 'spa_chat_subs', $cr['cr_id'] . '.' . md5($signer), $signer);
        Response::send(['success' => true]);
    }

    /** The local chatroom `$room` names, if `$observer` may chat in it. */
    private static function visibleRoom(array $room, string $observer): ?array
    {
        $channel = channelx_by_nick($room['nick']);
        if (!$channel || !perm_is_allowed($channel['channel_id'], $observer, 'chat'))
            return null;

        require_once('include/security.php');
        $sql_extra = permissions_sql($channel['channel_id'], $observer);
        $r = q("SELECT * FROM chatroom WHERE cr_id = %d AND cr_uid = %d $sql_extra LIMIT 1",
            intval($room['id']),
            intval($channel['channel_id'])
        );
        return $r ? $r[0] : null;
    }

    /** chat_post hook: queue notices only if the room has any subscriber. */
    public static function onChatPost(array $arr): void
    {
        $roomId = intval($arr['chat_room'] ?? 0);
        $cr = q("SELECT cr_uid FROM chatroom WHERE cr_id = %d LIMIT 1", $roomId);
        if (!$cr)
            return;

        $any = q("SELECT k FROM pconfig WHERE uid = %d AND cat = 'spa_chat_subs' AND k LIKE '%s' LIMIT 1",
            intval($cr[0]['cr_uid']),
            dbesc($roomId . '.%')
        );
        if ($any)
            QueueWorker::Summon(['Addon', 'spa_chatfed', 'notice', $roomId, (string)($arr['chat_xchan'] ?? '')]);
    }

    private static function sendNotices(int $roomId, string $sender): void
    {
        $cr = q("SELECT * FROM chatroom WHERE cr_id = %d LIMIT 1", $roomId);
        $owner = $cr ? channelx_by_n($cr[0]['cr_uid']) : null;
        if (!$owner)
            return;
        $uid = intval($owner['channel_id']);

        $subs = q("SELECT k, v FROM pconfig WHERE uid = %d AND cat = 'spa_chat_subs' AND k LIKE '%s'",
            $uid, dbesc($roomId . '.%'));
        if (!$subs)
            return;

        // Whoever is in the room is reading it already.
        $present = array_column(q("SELECT cp_xchan FROM chatpresence WHERE cp_room = %d", $roomId) ?: [], 'cp_xchan');
        $last    = q("SELECT created FROM chat WHERE chat_room = %d ORDER BY created DESC LIMIT 1", $roomId);
        $roomUrl = z_root() . '/chat/' . $owner['channel_address'] . '/' . $roomId;
        $who     = q("SELECT xchan_name FROM xchan WHERE xchan_hash = '%s' LIMIT 1", dbesc($sender));

        foreach ($subs as $s) {
            $xchan = $s['v'];
            if ($xchan === $sender || in_array($xchan, $present, true))
                continue;

            // Access may have changed since they subscribed.
            if (!self::visibleRoom(['nick' => $owner['channel_address'], 'id' => $roomId], $xchan)) {
                del_pconfig($uid, 'spa_chat_subs', $s['k']);
                continue;
            }

            $throttle = "spa_chatfed:$roomId:$xchan";
            if (Cache::get($throttle, self::THROTTLE))
                continue;

            $hub = q("SELECT hubloc_url FROM hubloc WHERE hubloc_hash = '%s' AND hubloc_primary = 1 AND hubloc_deleted = 0 LIMIT 1",
                dbesc($xchan));
            if (!$hub)
                continue;

            $code = self::signedPost($owner, $hub[0]['hubloc_url'] . '/spa/chatfed/notice', [
                'room_url'  => $roomUrl,
                'created'   => $last ? $last[0]['created'] : datetime_convert(),
                'recipient' => $xchan,
                'sender_name' => $who ? $who[0]['xchan_name'] : '',
            ]);

            if ($code === 404 || $code === 410)
                del_pconfig($uid, 'spa_chat_subs', $s['k']); // unbookmarked, or not an SPA hub
            elseif ($code >= 200 && $code < 300)
                Cache::set($throttle, '1');
            else
                logger("chatfed: notice for room $roomId to $xchan failed ($code)");
        }
    }

    // ── Subscriber's hub ──────────────────────────────────────────────────────

    private function notice(string $signer, array $room, array $data): never
    {
        $recipient = channelx_by_hash((string)($data['recipient'] ?? ''));
        if (!$recipient)
            Response::error(404, 'No such recipient');

        // Only the room's owner may speak for it: the signer must be the
        // channel whose address the room URL carries.
        $own = q("SELECT hubloc_id FROM hubloc WHERE hubloc_hash = '%s' AND hubloc_addr = '%s' AND hubloc_deleted = 0 LIMIT 1",
            dbesc($signer),
            dbesc($room['nick'] . '@' . $room['host'])
        );
        if (!$own)
            Response::error(403, 'Signer does not own this room');

        $uid   = intval($recipient['channel_id']);
        $title = self::bookmarkTitle($uid, $room);
        if ($title === null)
            Response::error(404, 'Room not bookmarked');

        $created = (string)($data['created'] ?? '');
        if (!preg_match('/^\d{4}-\d\d-\d\d \d\d:\d\d:\d\d$/', $created))
            Response::error(400, 'created must be UTC Y-m-d H:i:s');
        $created = min($created, datetime_convert()); // a clock ahead can't pin the dot on

        $k = md5($room['url']);
        if ((string)get_pconfig($uid, 'spa_chat_last', $k, '') < $created) {
            set_pconfig($uid, 'spa_chat_last', $k, $created);
            if (get_pconfig($uid, 'spa_chat_push', $k))
                self::push($recipient, $room, $title, (string)($data['sender_name'] ?? ''));
        }

        Response::send(['success' => true]);
    }

    /**
     * Web Push for a remote room. Through a hook because spa-core can't name the
     * theme's sender function; the theme's webpush.php answers it with the same
     * datarray shape it takes from enotify_store_end. Not Enotify::submit():
     * its only generic type, NOTIFY_SYSTEM, always sends email too.
     */
    private static function push(array $recipient, array $room, string $title, string $sender): void
    {
        $sender = trim(escape_tags($sender));
        $arr = [
            'uid'   => intval($recipient['channel_id']),
            'xname' => $title,  // the recipient's own bookmark title, not a remote-supplied name
            'msg'   => $sender ? sprintf(t('%s wrote in chat'), $sender) : t('New chat messages'),
            'photo' => '',
            // Opens on the owner's hub, logged in as the recipient. Built by hand:
            // zid() bails out without a session observer, and a notice has none.
            // The canonical url never carries a query.
            'link'  => $room['url'] . '?zid=' . urlencode(channel_reddress($recipient)),
            'hash'  => 'chat:' . md5($room['url']), // one notification per room, replaced not stacked
        ];
        call_hooks('spa_webpush', $arr);
    }

    /** The recipient's bookmark title for `$room`, or null if not bookmarked. */
    private static function bookmarkTitle(int $uid, array $room): ?string
    {
        $r = q("SELECT mi.mitem_link, mi.mitem_desc FROM menu_item mi
                JOIN menu m ON m.menu_id = mi.mitem_menu_id
                WHERE mi.mitem_channel_id = %d AND (m.menu_flags & %d) AND (mi.mitem_flags & %d)",
            $uid, MENU_BOOKMARK, MENU_ITEM_CHATROOM);
        foreach ($r ?: [] as $row) {
            $b = self::parseRoomUrl(htmlspecialchars_decode($row['mitem_link'], ENT_QUOTES));
            if ($b && $b['url'] === $room['url'])
                return htmlspecialchars_decode($row['mitem_desc'], ENT_QUOTES);
        }
        return null;
    }

    /**
     * Queue a subscribe for a chat bookmark pointing at another hub. Called on
     * bookmark create and, every RESUBSCRIBE_DAYS, from the chat-bookmark list.
     */
    public static function queueSubscribe(int $uid, string $url): void
    {
        $room = self::parseRoomUrl($url);
        if (!$room || $room['host'] === self::localHost())
            return;
        set_pconfig($uid, 'spa_chat_subscribed', md5($room['url']), datetime_convert());
        QueueWorker::Summon(['Addon', 'spa_chatfed', 'subscribe', $uid, $room['url']]);
    }

    private static function sendSubscribe(int $uid, string $url): void
    {
        $channel = channelx_by_n($uid);
        $room    = self::parseRoomUrl($url);
        if (!$channel || !$room)
            return;

        // Only hubs we already federate with: the target comes from hubloc, never
        // straight from the user-supplied bookmark URL.
        $hub = q("SELECT hubloc_url FROM hubloc WHERE hubloc_host = '%s' AND hubloc_network = 'zot6' AND hubloc_deleted = 0 ORDER BY hubloc_id DESC LIMIT 1",
            dbesc($room['host']));
        if (!$hub) {
            logger('chatfed: no known zot hub at ' . $room['host']);
            return;
        }

        $code = self::signedPost($channel, $hub[0]['hubloc_url'] . '/spa/chatfed/subscribe', ['room_url' => $room['url']]);
        logger("chatfed: subscribe {$room['url']} → $code", LOGGER_DEBUG);
    }

    /**
     * For GET /spa/bookmarks/chat: `last_other` and `push` per remote room,
     * re-queueing a subscribe where the last one is older than RESUBSCRIBE_DAYS.
     *
     * @param string[] $urls
     * @return array<string, array{last_other: ?string, push: bool}> keyed by url
     */
    public static function remoteRoomState(int $uid, array $urls): array
    {
        $cfg = [];
        foreach (q("SELECT cat, k, v FROM pconfig WHERE uid = %d AND cat IN ('spa_chat_last', 'spa_chat_subscribed', 'spa_chat_push')", $uid) ?: [] as $row)
            $cfg[$row['cat']][$row['k']] = $row['v'];

        $stale = datetime_convert('UTC', 'UTC', 'now - ' . self::RESUBSCRIBE_DAYS . ' days');
        $out = [];
        foreach ($urls as $url) {
            $room = self::parseRoomUrl($url);
            if (!$room || $room['host'] === self::localHost())
                continue;
            $k = md5($room['url']);
            $out[$url] = [
                'last_other' => $cfg['spa_chat_last'][$k] ?? null,
                'push'       => !empty($cfg['spa_chat_push'][$k]),
            ];
            if (($cfg['spa_chat_subscribed'][$k] ?? '') < $stale)
                self::queueSubscribe($uid, $url);
        }
        return $out;
    }

    /** Turn Web Push for a remote chat bookmark on or off. */
    public static function setPush(int $uid, string $url, bool $on): bool
    {
        $room = self::parseRoomUrl($url);
        if (!$room || $room['host'] === self::localHost())
            return false;
        $on ? set_pconfig($uid, 'spa_chat_push', md5($room['url']), 1)
            : del_pconfig($uid, 'spa_chat_push', md5($room['url']));
        return true;
    }

    // ── Shared ────────────────────────────────────────────────────────────────

    /** daemon_addon hook: ['Addon', 'spa_chatfed', <job>, ...args]. */
    public static function onDaemon(array $argv): void
    {
        if (($argv[1] ?? '') !== 'spa_chatfed')
            return;
        match ($argv[2] ?? '') {
            'notice'    => self::sendNotices(intval($argv[3] ?? 0), (string)($argv[4] ?? '')),
            'subscribe' => self::sendSubscribe(intval($argv[3] ?? 0), (string)($argv[4] ?? '')),
            default     => null,
        };
    }

    /**
     * `https://hub[:port]/chat/<nick>/<id>` → parts plus a canonical url (query
     * and trailing slash dropped), so a bookmark saved with `?zid=` still matches.
     */
    public static function parseRoomUrl(string $url): ?array
    {
        $p = parse_url(trim($url));
        if (!$p || !in_array($p['scheme'] ?? '', ['http', 'https'], true) || empty($p['host']))
            return null;
        if (!preg_match('#^/chat/([^/]+)/(\d+)/?$#', $p['path'] ?? '', $m))
            return null;
        $host = strtolower($p['host']) . (isset($p['port']) ? ':' . $p['port'] : '');
        return [
            'host' => $host,
            'nick' => $m[1],
            'id'   => intval($m[2]),
            'url'  => $p['scheme'] . '://' . $host . '/chat/' . $m[1] . '/' . $m[2],
        ];
    }

    private static function localHost(): string
    {
        $p = parse_url(z_root());
        return strtolower($p['host'] ?? '') . (isset($p['port']) ? ':' . $p['port'] : '');
    }

    /** The signer's xchan hash, or '' when the request isn't validly signed. */
    private static function verifiedSigner(string $body): string
    {
        $v = HTTPSig::verify($body, '', 'zot6');
        return ($v['header_valid'] && $v['content_valid']) ? (string)$v['portable_id'] : '';
    }

    /** POST a JSON body signed as `$channel` (as Libzot::zot signs); returns the HTTP code. */
    private static function signedPost(array $channel, string $url, array $body): int
    {
        $data = json_encode($body);
        $p    = parse_url($url);
        $headers = [
            'Content-Type'     => 'application/json',
            'Date'             => datetime_convert('UTC', 'UTC', 'now', 'D, d M Y H:i:s \\G\\M\\T'),
            'Digest'           => HTTPSig::generate_digest_header($data),
            'Host'             => $p['host'] . (isset($p['port']) ? ':' . $p['port'] : ''),
            '(request-target)' => 'post ' . get_request_string($url),
        ];
        $signed = HTTPSig::create_sig($headers, $channel['channel_prvkey'], channel_url($channel), false, 'sha256');
        $x = z_post_url($url, $data, 0, ['headers' => $signed, 'timeout' => 10]);
        return intval($x['return_code'] ?? 0);
    }
}
