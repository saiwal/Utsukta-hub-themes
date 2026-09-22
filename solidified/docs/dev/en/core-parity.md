# Core parity: delivery and clone-sync side effects

The SPA reimplements work core does in its own modules. A column diff
(`packages/spa-core/php/Api/parity.test.php`) catches a datarray field we fail
to set. It cannot see what core does *around* the write: summoning the Notifier,
building a clone-sync packet, firing a hook, sending a notification.

Those are the ones that hurt quietly. A missing clone-sync is invisible on the
hub you made the change on and wrong on every other hub the channel owns — the
same shape as the missing conversation target that started this audit.

**Rule: a handler that writes a row core also writes must either call core's
helper, or carry a comment naming the core function it mirrors and why it
can't.**

## Clone sync (`Libsync::build_sync_packet`)

What core syncs, and where the SPA stands. "free" means the sync lives inside a
core function the SPA already calls, so there is nothing to add.

| key | core sync site | SPA | status |
|---|---|---|---|
| `file` | `include/attach.php` (inside `attach_store`/`attach_delete`) | `Files`, `Photos` | free |
| `item` | the Notifier carries item sync | `Item` and friends | free |
| `abook` | `Connedit`, `Defperms`, `Follow`, `Tokens`, `Permcat::assign` | `Connections` | **fixed 2026-09-21** — only `createConnection` synced; `approve()` and `update()` did not |
| `abook` (bulk) | `Permcat::assign` syncs each row itself | `Connections` bulk role paths | free (their extra `UPDATE abook SET abook_role` duplicates what `assign()` already did) |
| `app` / `sysapp` | `Appman` on install and delete | `Settings` app actions | **fixed 2026-09-21** — installs and uninstalls reached no clone |
| `chatroom` | `Chatroom::destroy` (inside), `Module\Chat::post` (create, in the module) | `Chat` | **fixed 2026-09-21** — `Chatroom::create()` does not sync, so creates reached no clone |
| `menu` | `menu_sync_packet()`, called by `Module\Menu` / `Module\Mitem` | `Menus`, `Bookmarks` | covered (all 7 write paths call it) |
| `event` / `event_item` | `Channel_calendar` on delete; `event_addtocal` | `Cal` | covered on create via item delivery; delete **fixed 2026-09-21** — see below |
| `profile` | `Profiles`, `Profile_photo` | `Profiles`, `Avatar` | covered |
| group/pgrp | `include/group.php` (inside `AccessList::add`/`remove`/…) | `PrivacyGroups` | free |
| `atoken` | `Module\Tokens` | `Tokens` | covered |
| `config` | `Module\Pin` | `Item::togglePin` | covered |
| `likes`, `obj` | thing/profile likes, Things | no SPA equivalent | n/a |

## Notifier commands

`Master::Summon(['Notifier', <cmd>, <id>])`. Getting the command wrong is as bad
as omitting it — the Notifier branches on it to decide the audience.

| action | core command | SPA |
|---|---|---|
| new top-level post | `wall-new` | same |
| comment | `comment-new` | same |
| reaction / RSVP / vote | `like` | same |
| edit | `edit_post` | same |
| delete | `drop` | same |
| connection permission change | `permission_create` / `permission_update` / `permission_accept` | same |

## Notifications (`Enotify::submit`)

Delivery fires most notifications on its own. The exception is anything stored
under the recipient's *own* uid, where no delivery happens:

- wall-to-wall post → `NOTIFY_WALL` (covered)
- wall-to-wall comment → `NOTIFY_COMMENT` (**added 2026-09-21**; a visitor
  commenting on your wall post previously notified nobody)

## Deleting an event

`Cal::deleteEvent()` used to call `drop_item($id, DROPITEM_PHASE1)` and then
`DELETE FROM event`, with no sync and no Notifier. `drop_item()` on its own only
marks the row — it federates nothing — so an event deleted in the SPA stayed on
every other hub and on the channel's own clones forever.

It now mirrors core's phased shape, which is also what `Item::deleteItem()` in
this codebase already did:

1. `drop_item($id, DROPITEM_PHASE1)`
2. re-read the row, `xchan_query` + `fetch_post_tags`, then
   `Libsync::build_sync_packet($uid, ['item' => [encode_item($sync[0], true)]])`
3. `tag_deliver($uid, $id)`
4. `Master::Summon(['Notifier', 'drop', $id])` when `item_wall`
5. the `event` row is clone-synced separately, flagged `event_deleted = 1`,
   before the `DELETE`

## Hooks

`call_hooks('post_local')` / `post_local_end')` run on the SPA's post and
comment paths. Known gap, deliberate: `Zotlabs\Module\Item` is the only thing
that fires `post_content`, so the `mdpost` addon — and any other addon on that
hook — is skipped when posting from the SPA. See `ContentTypes.php`.

## Driving core's post handler from PHP

`Zotlabs\Module\Item::post()` is callable directly — this is what
`parity.test.php` does, and what a future refactor would use to stop
reimplementing it. The contract, verified against all 30 exit points in
lines 79–1225:

- Set `$_POST['api_source'] = 1`. It then **returns** instead of `killme()`.
- **Never set** `dropitems`, `preview` or `return`. Those are the only guards
  that still reach an exit with `api_source` on (lines 142, 548, 990, 1020,
  1164). One more, line 353, fires when `post_id` names a missing item, so
  pre-validate on the edit path.
- Three return shapes: `$post` (the `item_store()` result) for a create at
  1194; `$x` (the `item_store_update()` result — **different shape**) for an
  edit at 1065; and `['success' => false, 'message' => …]` for a handled
  failure, with eight possible messages (`no channel`, `no owner`,
  `no content`, `invalid post id`, `permission denied`,
  `service class exception`, `operation cancelled`, `system error`).
- ACL arrives via `$acl->set_from_array($_POST)`: `contact_allow`,
  `group_allow`, `contact_deny`, `group_deny` as **arrays**. Empty arrays mean
  public; omitting them entirely means the channel's default ACL.
- `nopush = 1` suppresses delivery. `origin`, `namespace`, `remote_id` and
  `message_id` are honoured only under `api_source`.

Unrelated but adjacent, and it costs an hour every time it bites:
`dba_pdo::q()` decides whether a query is a SELECT with
`stripos($sql, 'select') === 0`. A query string starting with a newline is not
recognised and returns a raw `PDOStatement` instead of rows. Keep `SELECT` on
the first line.

## Why the app item types still build their own datarray

Cards, articles, webpages and blocks cannot go through `Item::post()`, even
though core routes its own through it with `$_POST['webpage']`. Core derives
`public_policy` only when `item_type === ITEM_TYPE_POST`
(`Zotlabs\Module\Item::post:432-436`), so for these four it is always ''.

`ResolvesAcl::aclFromScope()`'s `connections` scope puts the entire restriction
in `public_policy` with an empty ACL, and `aclFromComposerInput()` passes one
explicitly. Drop the field and `item_private` computes to 0 — a connections-only
webpage or block becomes public. So these keep their own datarray on purpose.

## Still unverified

- The event *create* datarray: `Channel_calendar::post()` could not be driven
  headlessly, so `parity.test.php` asserts SPA invariants only for events.
- `Cart`, `Admin`, `Register`, `NewChannel`, `SiteLogo`: site- and
  account-level writes, not clone-synced state. Phase 4 material.
