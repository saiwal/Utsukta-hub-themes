# Chat notices across hubs

Chatrooms don't federate. A room, its messages and its presence live only in
the owner's hub's `chat*` tables, and core offers no machine-readable way to
ask another hub whether a room has news: `/chatsvc` GET marks the caller
present, `/chat/<nick>` is HTML without timestamps, and core's API signature
login (`include/api_auth.php`) only accepts channels local to the hub.

So a bookmarked room on another hub can only get an unread dot if **both hubs
run spa-core**. `Handlers/ChatFed.php` is that side channel. Against a plain
Hubzilla hub nothing is sent that could succeed, and the room simply stays
dot-less.

## Flow

```
your hub                                   owner's hub
────────                                   ───────────
bookmark a remote room
  └ queueSubscribe → daemon ──signed POST /spa/chatfed/subscribe──▶ ACL check,
                                                                    pconfig spa_chat_subs
                                           someone posts
                                             └ chat_post hook → daemon
                         ◀──signed POST /spa/chatfed/notice──────── per subscriber
signer owns room? bookmarked?
  └ pconfig spa_chat_last
GET /spa/bookmarks/chat → last_other → dot
```

## Endpoints

| Route | Direction | Body | Answers |
|---|---|---|---|
| `POST /spa/chatfed/subscribe` | your hub → owner | `{ room_url }` | 200; 404 for a missing room *or* one the signer may not see (same answer, so it can't probe private rooms) |
| `POST /spa/chatfed/notice` | owner → your hub | `{ room_url, created, recipient, sender_name? }` | 200; 403 signer isn't the room's owner; 404 unknown recipient or room not bookmarked |

Both are server-to-server: no session, no CSRF. The only authentication is an
HTTP signature by a zot channel, `keyId = channel_url()`, as `Libzot::zot()`
signs; `HTTPSig::verify($body, '', 'zot6')` resolves it through hubloc/zotfinger
and must report both `header_valid` and `content_valid` (the Digest is signed).
The signed Date bounds replay to ±1 day, and replaying either call is harmless:
a subscribe is idempotent, and a notice only ever moves a time forward.

There is **no unsubscribe**. A notice for a room the recipient no longer has
bookmarked answers 404, and the owner's hub deletes that subscription. So do a
410, a hub that doesn't run spa-core (its 404 for an unknown route), and a
subscriber whose access to the room was revoked (re-checked before every send).

## Storage

One pconfig key per pair, never a JSON blob, so two concurrent writes can't
clobber each other:

| Hub | cat | k | v |
|---|---|---|---|
| owner | `spa_chat_subs` | `<cr_id>.<md5(xchan)>` | subscriber xchan hash |
| subscriber | `spa_chat_last` | `md5(canonical room url)` | newest notice time, UTC `Y-m-d H:i:s` |
| subscriber | `spa_chat_subscribed` | `md5(canonical room url)` | last subscribe attempt |
| subscriber | `spa_chat_push` | `md5(canonical room url)` | `1` = Web Push on notice (opt-in) |

`parseRoomUrl()` canonicalises a room URL (lower-case host, port kept, query
and trailing slash dropped), so a bookmark saved as `…/chat/bob/7?zid=me@hub`
still matches the owner's `…/chat/bob/7`.

"Seen" stays in the browser (`hz-chat-seen`, keyed by bookmark URL for remote
rooms). Remote rooms open on their own hub, so the SPA never sees their
messages: clicking the bookmark marks the room seen *now*.

## Triggers

- **Subscribe**: `POST /spa/bookmarks` with `ischat`, and `POST /spa/bookmarks/item`
  for a chatroom link; plus `GET /spa/bookmarks/chat` re-queues any remote
  bookmark whose last attempt is older than 7 days. That last one is what
  covers bookmarks made through core's classic "Bookmark this room" (`/rbmark`),
  which spa-core never sees being created.
- **Notice**: the `chat_post` hook, fired by core's `/chatsvc` *and* by
  `Chat::sendMessage` (see `core-parity.md`). The hook only does one indexed
  pconfig lookup and, if the room has any subscriber, queues
  `['Addon', 'spa_chatfed', 'notice', <room>, <sender>]`.

All outbound HTTP runs in core's QueueWorker through the `daemon_addon` hook,
never in the sender's request. The daemon skips the sender and anyone present
in the room, and throttles to one notice per room per subscriber per
**5 minutes** (`Zotlabs\Lib\Cache`, key `spa_chatfed:<room>:<xchan>`).

Known ceiling: a message inside that window, after you have opened the room,
raises no new dot. Shrink `ChatFed::THROTTLE` if it matters.

## Where requests go

Never to a URL straight from a request body or a user's bookmark:

- subscribe → `hubloc_url` of a zot6 hubloc whose `hubloc_host` is the room's
  host — only hubs we already federate with;
- notice → the subscriber's primary `hubloc_url`, for an xchan that has already
  proven itself with a valid signature.

The receiving side checks the signer *owns* the room: a hubloc row for the
signer with `hubloc_addr = <nick>@<host>` from the room URL. Without that, any
zot channel could light up dots for rooms it doesn't own.

## Setup

The hooks (`chat_post`, `daemon_addon` → `hooks/chatfed.php`; `spa_webpush` →
`hooks/webpush.php`) are registered in
`solidified_theme_admin_enable()`, like the webpush hook. **An existing hub must
disable and re-enable the theme once** to pick them up.

## Check

`Handlers/ChatFed.test.php` runs the whole round trip over real HTTP on one hub,
with two local channels standing in for the two hubs: signature rejection,
subscribe + ACL, owner check, bookmark gate, monotonic time, future clamp, the
daemon's delivery, the throttle, the 404 that drops a subscription, and the push
opt-in plus the `spa_webpush` payload (captured with an in-process hook).

```
ddev exec php core/extend/theme/utsukta-themes/solidified/spa-core/Api/Handlers/ChatFed.test.php [owner] [subscriber]
```

A true two-hub test needs a second ddev hub.

## Web Push

Per room, off by default: the bell on a remote row of the Bookmarked Rooms
widget calls `POST /spa/bookmarks/chat-push { id, push }`, which sets pconfig
`spa_chat_push`. Turning it on first runs `subscribeToPush()` for this device —
idempotent — so the preference never exists without a push subscription behind it.

A notice that moves `spa_chat_last` forward, for a room with push on, calls
`ChatFed::push()`, which fires the **`spa_webpush`** hook with the same datarray
`enotify_store_end` carries (`uid`, `xname`, `msg`, `photo`, `link`, `hash`). The
theme's `webpush.php` answers both hooks with the same function. It's a hook
because spa-core can't name the theme's function; it's not `Enotify::submit()`
because that function's only generic type, `NOTIFY_SYSTEM`, always sends email too.

- **Title**: the recipient's own bookmark title, never a name the remote hub supplied.
- **Body**: "<sender> wrote in chat", with `sender_name` from the notice run
  through `escape_tags()`; "New chat messages" without it.
- **Link**: the room plus `?zid=<recipient address>`, built by hand. Core's
  `zid()` returns the URL unchanged when there's no session observer, and a
  notice request never has one (the test caught this).
- **Tag** `chat:<md5(url)>`: a new push replaces the room's previous one instead
  of stacking.

Pushes inherit the notice throttle, so at most one per room every 5 minutes.

## Not done yet

- **Seen state across devices**: still localStorage.
- Stale `spa_chat_last` / `spa_chat_push` keys after an unbookmark are left behind (tiny, harmless).
