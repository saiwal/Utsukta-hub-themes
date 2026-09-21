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

## Still unverified

- The event *create* datarray: `Channel_calendar::post()` could not be driven
  headlessly, so `parity.test.php` asserts SPA invariants only for events.
- `Cart`, `Admin`, `Register`, `NewChannel`, `SiteLogo`: site- and
  account-level writes, not clone-synced state. Phase 4 material.
