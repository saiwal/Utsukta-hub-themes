<?php
/**
 * Theme\Solidified\Api\Handlers\Chat
 *
 * Routes:
 *   GET  /api/chat/:nick              → room list
 *   GET  /api/chat/:nick/acl-options  → connections + groups for create form
 *   GET  /api/chat/:nick/:room_id     → room detail + presence
 *   POST /api/chat/:nick/:room_id/send   → post message
 *   POST /api/chat/:nick/:room_id/messages → fetch messages (paginated)
 *   POST /api/chat/:nick/:room_id/join    → enter room (set presence)
 *   POST /api/chat/:nick/:room_id/leave   → leave room (clear presence)
 *   POST /api/chat/:nick/new              → create chatroom (owner only)
 *   POST /api/chat/:nick/:room_id/drop    → delete chatroom (owner only)
 */

namespace Theme\Solidified\Api\Handlers;

use App;
use Zotlabs\Lib\Chatroom;
use Zotlabs\Access\AccessList;
use Theme\Solidified\Api\Auth;
use Theme\Solidified\Api\Response;

class Chat
{
    // argv layout: [0]=api [1]=chat [2]=nick [3]=room_id|"new" [4]=action
    private string $nick      = '';
    private int    $roomId    = 0;
    private string $action    = '';
    private int    $subjectUid = 0;

    private function parseArgs(): void
    {
        $this->nick   = \App::$argv[2] ?? '';
        $arg3         = \App::$argv[3] ?? '';
        $this->action = \App::$argv[4] ?? '';

        if (!$this->nick)
            Response::error(400, 'Nick required');

        $channel = channelx_by_nick($this->nick);
        if (!$channel)
            Response::error(404, 'Channel not found');

        $this->subjectUid = intval($channel['channel_id']);

        if ($arg3 && $arg3 !== 'new' && $arg3 !== 'acl-options')
            $this->roomId = intval($arg3);
    }

    // ── GET ───────────────────────────────────────────────────────────────────

    public function get(): void
    {
        $this->parseArgs();

        $observer = App::get_observer();
        $ob_hash  = $observer ? $observer['xchan_hash'] : '';

        if (!$ob_hash)
            Response::error(403, 'Observer required');

        if (!perm_is_allowed($this->subjectUid, $ob_hash, 'chat'))
            Response::error(403, 'Permission denied');

        $arg3 = \App::$argv[3] ?? '';
        if ($arg3 === 'acl-options') {
            $this->getAclOptions();
        } elseif (!$this->roomId) {
            $this->getRoomList();
        } else {
            $this->getRoomDetail($ob_hash);
        }
    }

    private function getRoomList(): void
    {
        if (!\Zotlabs\Lib\Apps::system_app_installed($this->subjectUid, 'Chatrooms'))
            Response::error(403, 'Chatrooms app not installed');

        $rooms = Chatroom::roomlist($this->subjectUid);
        if (!$rooms) $rooms = [];

        // Enrich with presence counts
        $result = [];
        foreach ($rooms as $room) {
            $room_id = intval($room['cr_id']);
            $presence = q(
                "SELECT count(*) AS total FROM chatpresence WHERE cp_room = %d",
                intval($room_id)
            );
            $last_chat = q(
                "SELECT created FROM chat WHERE chat_room = %d ORDER BY created DESC LIMIT 1",
                intval($room_id)
            );
            $result[] = [
                'id'        => $room_id,
                'name'      => $room['cr_name'],
                'expire'    => intval($room['cr_expire']),
                'in_room'   => $presence ? intval($presence[0]['total']) : 0,
                'last_msg'  => $last_chat ? $last_chat[0]['created'] : null,
                'is_owner'  => (local_channel() && local_channel() == $this->subjectUid),
            ];
        }

        Response::send([
            'nick'    => $this->nick,
            'rooms'   => $result,
            'is_owner' => (local_channel() && local_channel() == $this->subjectUid),
            'chatrooms_installed' => (bool)\Zotlabs\Lib\Apps::system_app_installed($this->subjectUid, 'Chatrooms'),
        ]);
    }

    private function getRoomDetail(string $ob_hash): void
    {
        require_once('include/security.php');
        $sql_extra = permissions_sql($this->subjectUid);

        $room = q(
            "SELECT * FROM chatroom WHERE cr_id = %d AND cr_uid = %d $sql_extra LIMIT 1",
            intval($this->roomId),
            intval($this->subjectUid)
        );
        if (!$room)
            Response::error(404, 'Room not found');

        $r = $room[0];
        $presence = q(
            "SELECT count(*) AS total FROM chatpresence WHERE cp_room = %d",
            intval($this->roomId)
        );

        Response::send([
            'id'       => intval($r['cr_id']),
            'name'     => $r['cr_name'],
            'expire'   => intval($r['cr_expire']),
            'in_room'  => $presence ? intval($presence[0]['total']) : 0,
            'is_owner' => (local_channel() && local_channel() == $this->subjectUid),
            'nick'     => $this->nick,
        ]);
    }

    // ── POST ──────────────────────────────────────────────────────────────────

    public function post(): void
    {
        $this->parseArgs();

        $arg3 = \App::$argv[3] ?? '';

        // Create room: POST /api/chat/:nick/new
        if ($arg3 === 'new') {
            $this->createRoom();
            return;
        }

        if (!$this->roomId)
            Response::error(400, 'Room ID required');

        switch ($this->action) {
            case 'send':
                $this->sendMessage();
                break;
            case 'messages':
                $this->fetchMessages();
                break;
            case 'join':
                $this->joinRoom();
                break;
            case 'leave':
                $this->leaveRoom();
                break;
            case 'drop':
                $this->dropRoom();
                break;
            default:
                Response::error(400, 'Unknown action');
        }
    }

    private function createRoom(): void
    {
        $uid = Auth::requireLocalJson();
        if ($uid !== $this->subjectUid)
            Response::error(403, 'Not your channel');

        $data = Auth::$parsedBody;
        $name = notags(trim($data['name'] ?? ''));
        if (!$name)
            Response::error(400, 'Room name required');

        $expire = max(0, intval($data['expire'] ?? 120));
        $visibility = $data['visibility'] ?? 'public'; // 'public' | 'connections' | 'private'

        $channel = App::get_channel();

        // Build ACL based on visibility choice
        $allow_cid = '';
        $allow_gid = '';
        $deny_cid  = '';
        $deny_gid  = '';

        if ($visibility === 'connections') {
            // Use the channel's default privacy group — same as Hubzilla's
            // populate_acl() default. Falls back to empty (open to connections
            // via perm_is_allowed) if no default group is set.
            $default_group = $channel['channel_default_group'] ?? '';
            if ($default_group) {
                $allow_gid = '<' . $default_group . '>';
            }
        } elseif ($visibility === 'custom' || $visibility === 'private') {
            // Granular allow/deny per contact and group
            foreach ((array)($data['allow_cid'] ?? []) as $h) {
                $h = notags(trim($h));
                if ($h) $allow_cid .= '<' . $h . '>';
            }
            foreach ((array)($data['allow_gid'] ?? []) as $h) {
                $h = notags(trim($h));
                if ($h) $allow_gid .= '<' . $h . '>';
            }
            foreach ((array)($data['deny_cid'] ?? []) as $h) {
                $h = notags(trim($h));
                if ($h) $deny_cid .= '<' . $h . '>';
            }
            foreach ((array)($data['deny_gid'] ?? []) as $h) {
                $h = notags(trim($h));
                if ($h) $deny_gid .= '<' . $h . '>';
            }
        }
        // 'public' → all four remain empty strings

        $arr = [
            'name'      => $name,
            'expire'    => $expire,
            'allow_cid' => $allow_cid,
            'allow_gid' => $allow_gid,
            'deny_cid'  => $deny_cid,
            'deny_gid'  => $deny_gid,
        ];

        Chatroom::create($channel, $arr);

        $x = q(
            "SELECT * FROM chatroom WHERE cr_name = '%s' AND cr_uid = %d LIMIT 1",
            dbesc($name),
            intval($uid)
        );

        if (!$x)
            Response::error(500, 'Failed to create room');

        Response::send([
            'id'         => intval($x[0]['cr_id']),
            'name'       => $x[0]['cr_name'],
            'visibility' => $visibility,
        ]);
    }

    /**
     * Return the owner's connections and privacy groups so the frontend
     * can render a meaningful ACL picker when creating a private room.
     * Only callable by the channel owner.
     */
    private function getAclOptions(): void
    {
        $uid = Auth::requireLocalGet();
        if ($uid !== $this->subjectUid)
            Response::error(403, 'Not your channel');

        // Privacy groups (formerly "privacy lists")
        $groups = q(
            "SELECT groups.id, groups.gname, groups.hash
             FROM groups
             WHERE groups.uid = %d AND groups.deleted = 0
             ORDER BY groups.gname ASC",
            intval($uid)
        );

        $group_list = [];
        foreach (($groups ?: []) as $g) {
            $group_list[] = [
                'id'   => intval($g['id']),
                'hash' => $g['hash'],
                'name' => $g['gname'],
            ];
        }

        // Approved connections (abook_pending = 0, abook_blocked = 0)
        $conns = q(
            "SELECT abook.abook_xchan, xchan.xchan_name, xchan.xchan_photo_m, xchan.xchan_url, xchan.xchan_addr
             FROM abook
             LEFT JOIN xchan ON xchan.xchan_hash = abook.abook_xchan
             WHERE abook.abook_channel = %d
               AND abook.abook_pending = 0
               AND abook.abook_blocked = 0
               AND abook.abook_self    = 0
             ORDER BY xchan.xchan_name ASC",
            intval($uid)
        );

        $conn_list = [];
        foreach (($conns ?: []) as $c) {
            $conn_list[] = [
                'hash'   => $c['abook_xchan'],
                'name'   => $c['xchan_name'] ?? '',
                'avatar' => $c['xchan_photo_m'] ?? '',
                'url'    => $c['xchan_url'] ?? '',
                'addr'   => $c['xchan_addr'] ?? '',
            ];
        }

        $channel = App::get_channel();
        Response::send([
            'default_group' => $channel['channel_default_group'] ?? '',
            'groups'        => $group_list,
            'connections'   => $conn_list,
        ]);
    }

    private function dropRoom(): void
    {
        $uid = Auth::requireLocalJson();
        if ($uid !== $this->subjectUid)
            Response::error(403, 'Not your channel');

        $channel = App::get_channel();
        $room = q(
            "SELECT * FROM chatroom WHERE cr_id = %d AND cr_uid = %d LIMIT 1",
            intval($this->roomId),
            intval($uid)
        );
        if (!$room)
            Response::error(404, 'Room not found');

        Chatroom::destroy($channel, ['cr_name' => $room[0]['cr_name']]);
        Response::send(['success' => true]);
    }

    private function joinRoom(): void
    {
        \Theme\Solidified\Api\Handlers\Csrf::validate();
        $observer = App::get_observer();
        $ob_hash  = $observer ? $observer['xchan_hash'] : '';
        if (!$ob_hash)
            Response::error(403, 'Authentication required');

        $x = Chatroom::enter($ob_hash, $this->roomId, 'online', $_SERVER['REMOTE_ADDR'] ?? '');
        if (!$x)
            Response::error(403, 'Cannot join room');

        Response::send(['success' => true]);
    }

    private function leaveRoom(): void
    {
        // Allow unauthenticated leave (cleanup on unload)
        $observer = App::get_observer();
        $ob_hash  = $observer ? $observer['xchan_hash'] : '';
        if ($ob_hash) {
            Chatroom::leave($ob_hash, $this->roomId, $_SERVER['REMOTE_ADDR'] ?? '');
        }
        Response::send(['success' => true]);
    }

    private function fetchMessages(): void
    {
        // No CSRF needed — read-only poll, but require observer
        $observer = App::get_observer();
        $ob_hash  = $observer ? $observer['xchan_hash'] : '';
        if (!$ob_hash)
            Response::error(403, 'Observer required');

        if (!perm_is_allowed($this->subjectUid, $ob_hash, 'chat'))
            Response::error(403, 'Permission denied');

        require_once('include/security.php');
        $sql_extra = permissions_sql($this->subjectUid);

        $room = q(
            "SELECT * FROM chatroom WHERE cr_id = %d AND cr_uid = %d $sql_extra LIMIT 1",
            intval($this->roomId),
            intval($this->subjectUid)
        );
        if (!$room)
            Response::error(404, 'Room not found');

        // Parse body manually — messages fetch is POST but no CSRF (poll)
        $raw  = file_get_contents('php://input');
        $body = $raw ? (json_decode($raw, true) ?? []) : [];

        $since  = notags(trim($body['since'] ?? ''));   // ISO datetime
        $limit  = max(1, min(100, intval($body['limit'] ?? 50)));

        if ($since) {
            $msgs = q(
                "SELECT chat.*, xchan.xchan_name, xchan.xchan_photo_m, xchan.xchan_url
                 FROM chat
                 LEFT JOIN xchan ON xchan.xchan_hash = chat.chat_xchan
                 WHERE chat.chat_room = %d AND chat.created > '%s'
                 ORDER BY chat.created ASC
                 LIMIT %d",
                intval($this->roomId),
                dbesc($since),
                intval($limit)
            );
        } else {
            $msgs = q(
                "SELECT chat.*, xchan.xchan_name, xchan.xchan_photo_m, xchan.xchan_url
                 FROM chat
                 LEFT JOIN xchan ON xchan.xchan_hash = chat.chat_xchan
                 WHERE chat.chat_room = %d
                 ORDER BY chat.created DESC
                 LIMIT %d",
                intval($this->roomId),
                intval($limit)
            );
            if ($msgs) $msgs = array_reverse($msgs);
        }

        $messages = [];
        foreach (($msgs ?: []) as $m) {
            $raw     = $m['chat_text'];
            $decoded = base64url_decode(str_rot47($raw));
            $body    = ($decoded !== false && mb_check_encoding($decoded, 'UTF-8')) ? $decoded : $raw;
            $messages[] = [
                'id'          => intval($m['chat_id']),
                'body'        => $body,
                'created'     => $m['created'],
                'author_name' => $m['xchan_name'] ?? '',
                'author_avatar' => $m['xchan_photo_m'] ?? '',
                'author_url'  => $m['xchan_url'] ?? '',
                'author_hash' => $m['chat_xchan'],
            ];
        }

        // Also return presence list
        $presence = q(
            "SELECT cp_xchan, xchan.xchan_name, xchan.xchan_photo_m, xchan.xchan_url, cp_status
             FROM chatpresence
             LEFT JOIN xchan ON xchan.xchan_hash = cp_xchan
             WHERE cp_room = %d",
            intval($this->roomId)
        );

        $present = [];
        foreach (($presence ?: []) as $p) {
            $present[] = [
                'hash'   => $p['cp_xchan'],
                'name'   => $p['xchan_name'] ?? '',
                'avatar' => $p['xchan_photo_m'] ?? '',
                'url'    => $p['xchan_url'] ?? '',
                'status' => $p['cp_status'],
            ];
        }

        $observer = App::get_observer();
        $viewer_hash = $observer ? $observer['xchan_hash'] : '';

        Response::send([
            'messages'    => $messages,
            'presence'    => $present,
            'viewer_hash' => $viewer_hash,
            'room_name'   => $room[0]['cr_name'],
            'room_acl'    => [
                'allow_cid' => $this->expandAclString($room[0]['allow_cid'] ?? ''),
                'allow_gid' => $this->expandAclString($room[0]['allow_gid'] ?? ''),
                'deny_cid'  => $this->expandAclString($room[0]['deny_cid']  ?? ''),
                'deny_gid'  => $this->expandAclString($room[0]['deny_gid']  ?? ''),
            ],
        ]);
    }

    private function expandAclString(string $s): array
    {
        if (!$s) return [];
        $parts = explode('>', str_replace('<', '', $s));
        return array_values(array_filter(array_map('trim', $parts)));
    }

    private function sendMessage(): void
    {
        \Theme\Solidified\Api\Handlers\Csrf::validate();
        $observer = App::get_observer();
        $ob_hash  = $observer ? $observer['xchan_hash'] : '';
        if (!$ob_hash)
            Response::error(403, 'Authentication required');

        if (!perm_is_allowed($this->subjectUid, $ob_hash, 'chat'))
            Response::error(403, 'Permission denied');

        $raw  = file_get_contents('php://input');
        $data = $raw ? (json_decode($raw, true) ?? []) : [];
        $text = trim($data['body'] ?? '');
        if (!$text)
            Response::error(400, 'Message body required');

        // Hubzilla stores chat_text as str_rot47(base64url_encode($text))
        $r = q(
            "INSERT INTO chat (chat_room, chat_xchan, created, chat_text)
             VALUES (%d, '%s', '%s', '%s')",
            intval($this->roomId),
            dbesc($ob_hash),
            dbesc(datetime_convert()),
            dbesc(str_rot47(base64url_encode($text)))
        );

        if (!$r)
            Response::error(500, 'Failed to send message');

        // Keep presence alive
        Chatroom::enter($ob_hash, $this->roomId, 'online', $_SERVER['REMOTE_ADDR'] ?? '');

        // Expire old messages if room has expiry set
        $room = q(
            "SELECT cr_expire FROM chatroom WHERE cr_id = %d LIMIT 1",
            intval($this->roomId)
        );
        if ($room && intval($room[0]['cr_expire']) > 0) {
            q(
                "DELETE FROM chat WHERE chat_room = %d AND created < '%s'",
                intval($this->roomId),
                dbesc(datetime_convert('UTC', 'UTC', 'now - ' . intval($room[0]['cr_expire']) . ' minutes'))
            );
        }

        Response::send(['success' => true, 'created' => datetime_convert()]);
    }
}
