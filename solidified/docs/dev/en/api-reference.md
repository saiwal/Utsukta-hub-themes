# Solidified SPA — PHP API Reference

Source: `src/Api/` in [saiwal/Hubzilla-Solidified-Source](https://github.com/saiwal/Hubzilla-Solidified-Source)
Namespace: `Theme\Solidified\Api`. All routes are dispatched by `Router.php` from `/api/<resource>[/...]`, where `<resource>` selects a handler class and the handler's `get()` / `post()` / `delete()` method runs based on HTTP verb. Unknown resource → `404`; unsupported verb on a known resource → `405`.

## Response Envelope (global, from `Response.php`)

| Kind | Shape |
|---|---|
| Success | `{ "data": <payload> }` — `data` is whatever the handler passes to `Response::send()` |
| Success + meta | `{ "data": <payload>, "meta": {...} }` |
| Paginated | `{ "data": [...], "meta": { offset, limit, count, root_count, has_more, nouveau } }` (via `Response::paginate()`) |
| Error | `{ "error": { "status": <code>, "message": "<text>" } }` (via `Response::error()`) |

All mutation endpoints (POST/DELETE) require CSRF protection (see `csrf` endpoint). Most endpoints require an authenticated local channel unless noted "public."

---

## Endpoints by Handler

| Resource (`/api/…`) | Method & Path | Description | Response `data` |
|---|---|---|---|
| **csrf** | `GET /csrf` | Issue a CSRF token for subsequent mutations | `{ token }` |
| **login** | `GET /login` | Issue login form token | `{ token }` |
| | `POST /login` | Authenticate with email/password (rate-limited, CSRF-checked) | `{ nick }` |
| **logout** | `POST /logout` | End session | `{ success }` |
| **rmagic** | `POST /rmagic` `{ address, dest? }` | Resolve remote channel's home hub, return OWA magic-auth URL | `{ url }` |
| **register** | `GET /register` | Registration policy / closed status | `{ policy, closed }` or full form data |
| | `POST /register` | Create a new account (rate-limited, CSRF-checked) | `{ next: 'check_email'|'pending_approval', regate_url? }` |
| **regate** | `GET /regate/:token` | Fetch email-verification record for a pending registration | verification details |
| | `POST /regate/:token` | Submit verification code, activate/queue account | `{ next: 'pending_approval' }` or account result |
| **password-reset** | `GET /password-reset[/:token]` | Get reset form token, or validate a reset token | `{ token }` / `{ valid }` |
| | `POST /password-reset[/:token]` | Request reset email, or submit new password (rate-limited) | `{ sent }` / `{ success }` |
| **new-channel** | `GET /new-channel[/autofill\|checkaddr]` | First-channel setup helpers: role autofill, nickname availability | role fields / `{ suggestion }` |
| | `POST /new-channel` | Create the account's first channel | new channel info |
| **manage** | `GET /manage` | List channels available to the current account | list of channels |
| | `POST /manage` `{ action }` | Switch active channel / set default channel | `{ ...channel }` / `{ default_channel_id }` |
| **nav** | `GET /nav` | Global nav: menu items, `installed_apps`, identity summary | nav/app config |
| **userconfig** | `GET /userconfig` | Viewer identity, pconfig, pinned/featured/system app lists (safe for anon) | config object |
| **pconfig** | `GET /pconfig` | Per-channel client-side config (with derived defaults) | config object |
| **siteinfo** | `GET /siteinfo` | Public site stats & metadata | site info object |
| **manifest** | `GET /manifest` | PWA web app manifest | manifest JSON |
| **sw** | `GET /sw` | Service worker script/config | SW payload |
| **help** | `GET /help[/:page]` | Rendered docs page from `src/docs/` | help content |
| **search** | `GET /search?q=<url>` | Resolve a public post URL to a local `uuid` (Zot/ActivityPub lookup) | `{ uuid }` |
| **pubsites** | `GET /pubsites` | Directory of public Hubzilla hubs | `{ sites }` |
| **weather** | `GET /weather?place=&unit=` | Geocode + current conditions (proxied via Open-Meteo) | weather data |
| **rss-feed** | `GET /rss-feed?url=&limit=` | Server-side fetch/parse of an RSS/Atom feed (SSRF-guarded) | feed items |
| **announcements** | `GET /announcements` | Latest site-wide announcements (public) | list of announcements |
| | `POST /announcements` `{ action:'create'\|'delete', ... }` | Admin: create/delete announcement | updated list/status |
| **channel** | `GET /channel[/:nick]` | Channel wall: posts, categories, tags for a nick | `{ ...posts/meta }` |
| **profile** | `GET /profile/:nick[/connections\|posts]` | Public profile fields, or profile's connections/post list | profile / `{ connections, total, hidden }` |
| **profiles** | `GET /profiles[/:id[/contacts]]` | List/get multi-profiles for own channel, or contacts assigned to one | profile(s) |
| | `POST /profiles[/:id[/set-default\|assign\|unassign]]` | Create/update/delete a profile; assign contacts to it | `{ status, id }` |
| **xchan** | `GET /xchan?hash=` | Look up a federated actor (xchan) by hash/address | xchan record |
| **connections** | `GET /connections[?address=][/permcats]` | List connections, exact lookup by address, or permission-role catalog | connections / permcats |
| | `POST /connections/:id/:action` | Approve/ignore/archive/etc. a connection | updated connection |
| | `DELETE /connections/:id` | Remove a connection | `{ status }` |
| **directory** | `GET /directory` | Federated people/hub directory search (delegates to core `/dirsearch`) | directory results |
| **blocklist** | `GET /blocklist` | Local channel's personal block list | list |
| | `POST /blocklist` `{ action:'block'\|'unblock'\|'siteblock', author }` | Block/unblock a channel (site-block is admin-only) | `{ status }` |
| **privacy-groups** | `GET /privacy-groups[/:id[/members]]` | List groups, one group + members, or non-members for the picker | group(s) |
| | `POST /privacy-groups[/:id]` | Create/rename a group, add/remove members | group object |
| | `DELETE /privacy-groups/:id` | Delete a group | `{ status }` |
| **network** | `GET /network` | Authenticated user's federated home feed (paginated) | items (paginated) |
| **pubstream** | `GET /pubstream` | Public stream of federated posts for this hub | items |
| **hq-messages** | `GET /hq-messages` | HQ dashboard feed: all/direct/starred/notifications/folder, with search | message cards |
| **display** | `GET /display/:item_hash` | Permalink: single post + full thread | `{ ...post, thread }` |
| **item** | `GET /item/:mid[/comments[/:count]\|likes\|dislikes\|repeats]` | Item + thread root; comments (all or last N); reaction lists | item/comments/actor lists |
| | `POST /item[/:mid[/like\|dislike\|repeat\|star\|pin\|comment\|delete\|edit\|follow\|unfollow]]` | Create post/comment, or toggle a reaction/action on an item | `{ post, comments }` / `{ success }` |
| **item-source** | `GET /item-source/:iid` | Raw ActivityPub source object for a post/comment | AP object |
| **drafts** | `GET /drafts[?type=]` | List saved drafts (post/article) | list of drafts |
| | `POST /drafts[/:mid[/delete]]` | Create, update, or delete a draft | draft object / `{ status }` |
| **scheduled** | `GET /scheduled` | List delayed-publish posts | list |
| | `POST /scheduled/:action` | Manage a scheduled post (edit/cancel/publish-now) | `{ status }` |
| **saved-searches** | `GET /saved-searches` | List saved search filters | list |
| | `POST /saved-searches` | Create a saved search | new record |
| | `DELETE /saved-searches/:tid` | Delete a saved search | `{ status }` |
| **bookmarks** | `GET /bookmarks[/chat]` | All bookmark menus + items, or chatroom bookmarks only | bookmarks |
| | `POST /bookmarks` `{ url, title, ischat? }` | Add a bookmark | new bookmark |
| | `DELETE /bookmarks/:id` | Remove a bookmark item | `{ status }` |
| **notes** | `GET /notes` | List personal notes | list |
| | `POST /notes` `{ body, mimetype? }` | Create a note directly (bypasses `/item`) | note object |
| **notify** | `GET /notify/:id` | Look up + mark seen one `notify` row; return redirect target | `{ url, ... }` |
| **notifications** | `GET /notifications` | Full "System Notifications" list (up to 50, unseen-first) | list |
| **stream-widgets** | `GET /stream-widgets[/tags\|categories\|popular]` | Sidebar widget data for the current stream view | widget data |
| **widget-layout** | `POST /widget-layout` `{ layout }` | Save (or `null` to clear) the owner's custom widget arrangement | `{ status }` |
| **widget-templates** | `GET /widget-templates` | Owner's saved widget-layout templates | list |
| | `POST /widget-templates` | Create/rename/delete a template or replace its widget list | template object |
| **push-subscription** | `GET /push-subscription` | VAPID public key (generated once, site-wide) | `{ publicKey }` |
| | `POST /push-subscription` `{ subscription }` | Store/update a browser push subscription | `{ status }` |
| | `DELETE /push-subscription` `{ endpoint }` | Remove a push subscription | `{ status }` |
| **articles** | `GET /articles/:nick[/:uuid]` | List articles, or one article + comment thread | `{ articles }` / `{ article, comments }` |
| | `POST /articles/:nick[/:uuid]` | Create or update an article | `{ uuid, iid }` |
| **photos** | `GET /photos/:nick[/summary\|album\|image][/:id][/acl]` | Album list, album contents, or single photo (+ ACL for owner) | photos/albums |
| | `POST /photos/:nick/image/:id[/rename\|title\|description]` | Upload a photo, or rename/edit metadata of an existing one | updated photo |
| | `DELETE /photos/:nick/...` | Delete a photo or album | `{ status }` |
| **avatar** | `POST /avatar` `{ type: 'avatar'\|'cover' }` (multipart) | Upload + resize a new avatar or cover photo | `{ url(s) }` |
| **site-logo** | `POST /site-logo` (multipart, admin) | Upload a new site logo (multi-scale) | `{ url(s) }` |
| **files** | `GET /files/:nick[/folder/:hash\|meta/:hash\|quota\|download/:hash]` | Cloud file browser: list root/folder, file metadata+ACL, storage quota, download (file or zip) | file/folder listing |
| | `POST /files/:nick/...` | Upload a file, create a folder, move/rename, set ACL | updated entry |
| **folders** | `GET /folders` | Flat folder tree for pickers (e.g. attachment/file dialogs) | folder tree |
| **webpages** | `GET /webpages/:nick[?pagelink=\|mid=]` | List an owner's webpages, or render one public page | page list / rendered page |
| | `POST /webpages/:nick` | Create/update/delete a webpage | page object |
| **menus** | `GET /menus[/:id[/:sub]]` | Own menus, one menu's raw items, or a rendered menu tree | menu(s) |
| | `POST /menus` | Create/update/delete a menu or its items | `{ status }` |
| **wiki** | `GET /wiki/:nick[/:wikiName[/:pageName]]` | List wikis, list a wiki's pages, or fetch a page (rendered + raw) | wiki/page data |
| | `POST /wiki/:nick[/:wikiName/:pageName]` | Create a wiki, or upsert a page | `{ status }` / page object |
| | `DELETE /wiki/:nick/:wikiName/:pageName` | Delete a page | `{ status }` |
| **cal** | `GET /cal/calendars` | List CalDAV calendars + channel calendar | calendar list |
| | `GET /cal/:nick?start=&end=` | Channel event feed for a date range (default: next 60 days) | event list |
| | `POST /cal/:nick/:action/:id` (`toggle`/`edit`/`delete`/`share`/`unshare`) | Manage calendar visibility/sharing, edit or delete an event | `{ status }` |
| **chat** | `GET /chat/:nick[/acl-options\|:room_id]` | Room list, ACL picker options, or one room's detail + presence | rooms / room detail |
| | `POST /chat/:nick/:room_id/(send\|messages\|join\|leave\|drop)`, `POST /chat/:nick/new` | Send/fetch messages, join/leave presence, create/delete a room (owner) | message(s) / `{ status }` |
| **cart** | `GET /cart/:nick/(catalog\|order\|payment-config\|payment-settings\|orders)` | Storefront catalog/order data; seller views for settings & orders | cart data |
| | `POST /cart/:nick/:action` | Place order / update seller payment config | `{ status }` |
| **portability** | `GET /portability/:datatype` | Export options / current export status | export info |
| | `POST /portability/:datatype` | Trigger export, import an identity backup, or migrate from another server (rate-limited) | `{ status, ... }` |
| **settings** | `GET /settings[/display\|profile\|features\|account\|privacy\|channel\|apps\|notifications\|integrations\|danger\|locations]` | Read one settings section (defaults to `display`) | section object |
| | `POST /settings[/<same sections>]` | Save one settings section | `{ status }` |
| **admin** | `GET /admin[/summary\|site\|accounts\|channels\|security\|features\|addons\|themes\|inspect-queue\|queueworker\|profile-fields\|db-updates\|logs]` | Admin dashboard data per section (admin only) | section data |
| | `POST /admin/:section` `{ action, ... }` | Admin actions: approve/deny registration, change service class, block account, etc. | `{ status }` / updated record |

---

### Notes

- **Auth model**: most `/api/*` GET/POST calls require an active local-channel session; a handful (`csrf`, `login`, `register`, `regate`, `password-reset`, `pubsites`, `pubstream`, `announcements` GET, `directory`, `siteinfo`, `manifest`, `sw`, `rss-feed`, `weather`, `search`) are public or work for anonymous/remote visitors.
- **Pagination**: list-heavy endpoints (`network`, `articles` comments, item comments, etc.) use `Response::paginate()`, returning `offset`, `limit`, `count`, `root_count`, and `has_more` in `meta`.
- **CSRF**: all state-changing (`POST`/`DELETE`) endpoints validate a CSRF token obtained from `GET /api/csrf` (or a form-specific token, e.g. `spa_login_tok`, `spa_pwreset_tok`).
- **Rate limiting**: `login`, `register`, `regate`, and `password-reset` enforce per-IP attempt limits before returning `429`.
- Full route-level comments live at the top of each file in `src/Api/Handlers/`.
