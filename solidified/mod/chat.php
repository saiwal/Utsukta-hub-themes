<?php
// Zotlabs/Module/Chatapi.php (addon, not modifying core)
namespace Zotlabs\Module;
use App;
use Zotlabs\Lib\Chatroom;
use Zotlabs\Web\Controller;
use Zotlabs\Access\AccessList;
class Chat_api extends Controller {
    function init() {
        // same profile_load as Chat::init()
        $which = argv(1);
        if ($which) profile_load($which, 0);
    }

    function get() {
        $observer = get_observer_hash();
        if (!$observer) {
            json_return_and_die(['error' => 'Permission denied']);
        }

        // /chatapi/:nick/rooms  → room list
        if (argc() > 2 && argv(2) === 'rooms') {
            $uid = App::$profile['profile_uid'];
            if (!perm_is_allowed($uid, $observer, 'chat')) {
                json_return_and_die(['error' => 'Permission denied']);
            }
            $rooms = Chatroom::roomlist($uid);
            json_return_and_die(['rooms' => $rooms]);
        }

        // /chatapi/:nick/:roomId/messages?since=...
        if (argc() > 2 && intval(argv(2))) {
            $room_id = intval(argv(2));
            $since = $_GET['since'] ?? null;

            require_once('include/security.php');
            $r = q("select * from chatroom where cr_id = %d limit 1", $room_id);
            if (!$r) json_return_and_die(['error' => 'Not found']);

            $sql_extra = permissions_sql($r[0]['cr_uid']);
            $x = q("select * from chatroom where cr_id = %d and cr_uid = %d $sql_extra limit 1",
                $room_id, intval($r[0]['cr_uid']));
            if (!$x) json_return_and_die(['error' => 'Permission denied']);

            // Presence: enter room
            if (argv(3) === 'enter') {
                Chatroom::enter($observer, $room_id, 'online', $_SERVER['REMOTE_ADDR']);
                json_return_and_die(['success' => true]);
            }
            if (argv(3) === 'leave') {
                Chatroom::leave($observer, $room_id, $_SERVER['REMOTE_ADDR']);
                json_return_and_die(['success' => true]);
            }

            // Messages
            $sql_since = $since
                ? sprintf(" and created > '%s'", dbesc($since))
                : '';
            $msgs = q("select chat.*, xchan.xchan_name, xchan.xchan_photo_s
                        from chat
                        left join xchan on xchan.xchan_hash = chat.chat_xchan
                        where chat.chat_room = %d $sql_since
                        order by created asc limit 50",
                $room_id);

            $presence = q("select cp_xchan, cp_status, xchan.xchan_name
                           from chatpresence
                           left join xchan on xchan.xchan_hash = chatpresence.cp_xchan
                           where cp_room = %d", $room_id);

            json_return_and_die([
                'room' => $x[0],
                'messages' => $msgs ?: [],
                'presence' => $presence ?: [],
            ]);
        }

        json_return_and_die(['error' => 'Bad request']);
    }

    function post() {
        $observer = get_observer_hash();
        if (!$observer || !local_channel()) {
            json_return_and_die(['error' => 'Permission denied']);
        }

        // POST /chatapi/:nick/:roomId/send  → send message
        if (argc() > 3 && intval(argv(2)) && argv(3) === 'send') {
            $room_id = intval(argv(2));
            $body = trim($_POST['body'] ?? '');
            if (!$body) json_return_and_die(['error' => 'Empty message']);

            $r = q("select * from chatroom where cr_id = %d and cr_uid = %d limit 1",
                $room_id, intval(local_channel()));
            // also allow non-owners who have perm
            if (!$r) {
                $r = q("select * from chatroom where cr_id = %d limit 1", $room_id);
                if (!$r || !perm_is_allowed($r[0]['cr_uid'], $observer, 'chat')) {
                    json_return_and_die(['error' => 'Permission denied']);
                }
            }

            $expire = intval($r[0]['cr_expire']);
            q("insert into chat (chat_room, chat_xchan, created, edited, chat_text)
               values(%d, '%s', '%s', '%s', '%s')",
                $room_id,
                dbesc($observer),
                dbesc(datetime_convert()),
                dbesc(datetime_convert()),
                dbesc($body)
            );

            if ($expire) {
                q("delete from chat where chat_room = %d and created < %s - interval %s",
                    $room_id,
                    db_utcnow(), db_quoteinterval($expire . ' minute')
                );
            }

            json_return_and_die(['success' => true]);
        }

        // POST /chatapi/:nick/rooms  → create room
        if (argc() > 2 && argv(2) === 'rooms') {
            $channel = App::get_channel();
            $room = strip_tags(trim($_POST['room_name'] ?? ''));
            if (!$room) json_return_and_die(['error' => 'No room name']);

            $acl = new AccessList($channel);
            $acl->set_from_array($_REQUEST);
            $arr = $acl->get();
            $arr['name'] = $room;
            $arr['expire'] = max(0, intval($_POST['chat_expire'] ?? 120));
            Chatroom::create($channel, $arr);

            $x = q("select * from chatroom where cr_name = '%s' and cr_uid = %d limit 1",
                dbesc($room), intval(local_channel()));
            json_return_and_die(['room' => $x ? $x[0] : null]);
        }

        json_return_and_die(['error' => 'Bad request']);
    }
}
