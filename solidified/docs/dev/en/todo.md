# To Do

Planned-but-not-yet-built work, with enough design detail to pick back up later. Each entry captures the
decisions already made (so they don't need re-litigating) and the concrete implementation shape.

## Report-to-owner / report-to-admin moderation

**Status: planned, not started.**

A "Report" action any logged-in viewer can take on a post/comment, distinct from the existing
pending-moderation queue (`packages/spa-core/php/Api/Handlers/Moderate.php`, `/moderate` page): that mechanism hides
content Hubzilla itself auto-flags on arrival (unsolicited comments/reactions from non-contacts).
Reports need the opposite property — reported content is usually from an already-trusted contact and
must stay visible to everyone while under review, not get hidden by `item_blocked`.

The reporter picks the target audience per report: channel owner, site admin, or both (confirmed
design decision — not every report fans out everywhere, and it isn't owner-only).

### Design decisions already made

- **Storage**: a new table, `item_report` — one row per (reported item, target audience), so an
  owner dismissing their copy of a report never affects the admin's copy. `iconfig`
  (`Zotlabs\Lib\IConfig`) was ruled out: it only holds one scalar value per `(iid, cat, k)` — a second
  `Set()` call overwrites rather than appending, so it can't hold a growing list of reports. No
  existing `report`/`abuse`/`flag` table exists anywhere in core's schema (the only near-miss,
  `dreport`, is federation delivery receipts, unrelated).

  ```sql
  CREATE TABLE IF NOT EXISTS `item_report` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `iid` int(11) NOT NULL DEFAULT 0,
    `mid` char(191) NOT NULL DEFAULT '',
    `item_uid` int(11) NOT NULL DEFAULT 0,
    `reporter_xchan` char(191) NOT NULL DEFAULT '',
    `target` enum('owner','admin') NOT NULL DEFAULT 'owner',
    `reason` char(64) NOT NULL DEFAULT '',
    `note` mediumtext NOT NULL DEFAULT '',
    `status` enum('pending','dismissed','actioned') NOT NULL DEFAULT 'pending',
    `created` datetime NOT NULL,
    `reviewed_at` datetime DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `iid` (`iid`),
    KEY `item_uid` (`item_uid`),
    KEY `target` (`target`),
    KEY `status` (`status`)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
  ```

  Reason is a fixed short taxonomy (`spam`, `harassment`, `illegal`, `other`) plus an optional
  free-text `note`.

- **Schema delivery**: as a proper Hubzilla **addon**, not a manual migration. This theme has no
  `_install()` hook of its own, but the project already ships a small custom-addon repo,
  `utsukta-hub-addons` (github.com/saiwal/utsukta-hub-addons, checked out at
  `hz-ddev/core/extend/addon/utsukta-hub-addons`, currently holding `adminlte_tour/`). Add
  `item_report/item_report.php` there, mirroring the upstream `cart` addon's versioned-migration shape
  (`cart_install()` / `cart_dbUpgrade()` in `addon/cart/cart.php`) in miniature: a `$dbsql[1] = [...]`
  array applied once via `get_config('item_report','dbver')`, called from both `item_report_install()`
  (fires when the admin enables the addon via Admin ▸ Addons — already-built flow, `Admin.php`'s
  `postAddons()`/`install_plugin()`) and `item_report_load()` (cheap no-op re-check on every load, so a
  missed version self-heals, same as `cart_load()`). MySQL only — this project's actual DB backend, no
  need for cart's dual MySQL/Postgres branches. `item_report_uninstall()` intentionally leaves the
  table in place (reports are moderation history, not disposable addon state). The addon owns *only*
  the table lifecycle — no routes/hooks/UI; all read/write logic stays in this repo's
  `packages/spa-core/php/Api/Handlers/*.php`, same as every other core table.

- **Owner notification**: a direct `INSERT INTO notify`, not `Enotify::submit()` — its `NOTIFY_SYSTEM`
  branch is an empty stub in core (`Zotlabs/Lib/Enotify.php`), so nothing sets the notify text there.
  Mirror the exact column shape `Enotify::submit()` itself writes (`hash` = the item's `uuid`,
  `xname`/`url`/`photo` = reporter's xchan, `uid` = item owner's channel id, `link` = the item's
  permalink, `ntype = NOTIFY_SYSTEM`, `otype = 'item'`, `msg` = a `zrl`-linked sentence in the same
  style as core's "requested to like" wording). Lands in the existing "Alerts" bucket for free —
  `Sse_bs.php::bs_notify()` returns *every* unseen `notify` row for the uid, unfiltered by `ntype`. No
  notify row for the `admin` target — mirrors every existing admin section (accounts, channels,
  queue, …), none of which notify admins in-app; they check the panel.

### Implementation shape

Backend (this repo):

1. `packages/spa-core/php/Api/Handlers/Item.php` — new `POST /item/:mid/report` (add `'report'` to `$POST_VERBS` + the
   dispatch switch, same pattern as `star`/`pin`). Body: `{ reason, note?, targets: ("owner"|"admin")[] }`.
   Reuses `resolveItem($mid, $ob_hash)` for the visibility gate; blocks reporting your own item. One
   `item_report` row per selected target; the direct `notify` insert described above when `owner` is
   selected.
2. `packages/spa-core/php/Api/Handlers/Reports.php` (new) — owner-facing queue, mirrors `Moderate.php`'s shape exactly
   (`Auth::requireLocalGet()`/`requireLocalJson()`, hand-rolled DTO, `Response::send()`):
   `GET /spa/reports` (pending, `target='owner'`, scoped to `local_channel()`),
   `POST /spa/reports/:id/dismiss`, `POST /spa/reports/:id/delete` (→ `drop_item()`, then mark all
   `item_report` rows for that `iid` `'actioned'`).
3. `packages/spa-core/php/Api/Handlers/Admin.php` — add `case 'reports':` to both the `get()`/`post()` switches
   (`getChannels()`/`postChannels()` is the closest existing shape to mirror), gated by the existing
   `requireAdmin()`. Lists all `item_report` rows where `target='admin' AND status='pending'`,
   site-wide.
4. `packages/spa-core/php/Api/Router.php` — register `'reports' => Handlers\Reports::class`.

Frontend:

1. `src/shared/stream/components/PostCard.tsx` — "Report" entry in the existing "more" (⋮) dropdown
   (both compact-comment and full-post variants), gated on
   `canInteract() && !isTrueAuthor() && !!props.handlers.onReport`. Small inline form (mirror the
   `FolderPicker`/`PendingReactionsPanel` panel pattern already in this file): reason select, optional
   note, two checkboxes ("channel owner" checked by default, "site admin" unchecked). New
   `onReport?: (iid, reason, note, targets) => Promise<void>` on `StreamHandlers`
   (`src/shared/stream/types.ts`), wired in `PostView.tsx` and `PostDetailModal.tsx` the same way
   `onApprove`/`onReject` were.
2. `src/modules/moderate/api.ts` — add `reportItem()`, `fetchReports()`, `dismissReport()`,
   `deleteReportedItem()`.
3. Owner queue: a second tab in `src/modules/moderate/views/ModerateView.tsx` ("Pending" vs "Reports"),
   reusing its existing row-list/empty-state/loading-skeleton structure.
4. Admin panel: new `src/modules/admin/views/sections/ReportsSection.tsx` modeled on
   `ChannelsSection.tsx`; add to `ADMIN_ITEMS` (`admin/index.ts`) and `AdminView.tsx`'s `SECTIONS` map
   — gating (`is_site_admin() → is_admin → auth().isAdmin`) is already plumbed end to end.
5. i18n: new `report.*` keys across `en`/`de`/`hi`, following the exact multi-locale registration
   pattern the `moderate` namespace already uses.

### Verification (when this gets picked up)

- Enable the `item_report` addon via Admin ▸ Addons (fires `_install()` → creates the table).
- Report a post as a non-owner with target=owner only → one `item_report` row, one `notify` row,
  notification appears in the owner's Alerts bucket with correct text/link.
- Report with target=admin only → row appears in the site-admin Reports panel and *not* in the owner's
  `/moderate` Reports tab.
- Report with both targets → two rows; dismissing the owner's copy leaves the admin's copy `pending`.
- Delete-via-report (either queue) → item actually removed (`drop_item()`) and all report rows for
  that item flip to `actioned`.
- `npx tsc -b` and `npm run build` clean; deployed PHP handlers match source (`diff` check).
