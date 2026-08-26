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

## Bayesian spam filter for incoming posts

**Status: planned, not started.**

A naive-Bayes classifier that learns each user's spam from their own "mark as spam" feedback,
in the style of an email client. Complementary to the report entry above — that one routes a
*human* judgement to a moderator; this one is an automatic, per-user, no-moderator filter over
the incoming stream.

Nothing like it exists today. Core dropped its `spam` table in `Zotlabs/Update/_1170.php`, there
is no `item_spam` column in this schema, and core's only content filter is
`Zotlabs/Lib/MessageFilter` (static word/regex lists — what the `nsfw` addon and this repo's
`packages/spa-core/src/lib/nsfw.ts` are built on).

**Fixes a latent bug on the way in:** `packages/spa-core/php/Api/Handlers/Network.php:230-233`
emits `AND item_spam = 1` when `?spam=1` is passed — a guaranteed SQL error against a column
that does not exist in this schema. It is dormant only because nothing sends it
(`parseNetworkParams()` in `src/modules/network/api.ts` never reads `params.spam`, despite
`spam?: 1` being declared in `NetworkParams`). This work gives the param a real meaning rather
than deleting it: it becomes the **Spam folder** query.

### Design decisions already made

- **Classifier runs server-side in PHP**, not client-side in TS. Per-account by construction
  (training on a laptop helps the phone), works for any client, and leaves an upgrade path to
  SQL-level filtering. The client-side alternative — token counts in IndexedDB next to
  `hz-inbox-*` — is smaller but per-device.

- **Flagged posts are hidden entirely**, not collapsed behind a reveal toggle like the NSFW
  path. A Spam folder view makes them inspectable, which is what makes hiding safe.

- **No schema migration.** Unlike the report feature above, nothing here needs a new table:
  verdicts fit `iconfig` (one scalar per `(iid, cat, k)` is exactly one verdict per item) and
  the token model fits a single `pconfig` row. So no addon, no `_install()` hook.

- **Classification is lazy at read time and cached in `iconfig`**, not hook-driven at write
  time. A `post_remote_end`-style hook would only ever see newly arriving items and leave the
  entire backlog unclassified; classifying on first read means a page classifies what it shows,
  once, and every later read is a cache hit.

- **The verdict rides out in the existing `flags[]` array** built by
  `Concerns/FormatsItems.php:303-315`, which the client already round-trips. No response-shape
  change, no new field.

- **Only `item_thread_top` items are classified.** Dropping a comment while keeping its root is
  incoherent.

- **Cold-start guard**: the classifier returns "unknown" (never flags) until the model holds at
  least 10 spam and 10 ham messages, so a fresh install and a single mis-click can't empty
  someone's stream.

### Implementation shape

**1. `packages/spa-core/php/Api/SpamClassifier.php` (new) — pure math, no Hubzilla
dependencies.** Kept free of globals/DB specifically so it is unit-testable without
bootstrapping core.

- `tokenize(string $body, array $meta): array` — strip BBCode/HTML, lowercase,
  `[a-z0-9'$!-]{2,20}`; plus `from:<author_addr>` and `tag:<hashtag>` tokens.
- `train(array $model, array $tokens, bool $isSpam, int $sign): array` — pure
  increment/decrement over the counts map; `$sign = -1` untrains.
- `score(array $model, array $tokens): ?float` — Graham's *A Plan for Spam*: per-token
  `p = (s/S) / (s/S + 2*(h/H))` (the ×2 ham weight biases against false positives), clamped to
  `[0.01, 0.99]`, tokens seen fewer than 5 times treated as unknown (`p = 0.4`), the 15 most
  interesting tokens by `|p - 0.5|` combined as `prod / (prod + prod(1-p))`. Returns `null`
  below the 10-spam/10-ham cold-start threshold.
- `prune(array $model, int $cap = 2000)` — drop lowest-total tokens.

Spam threshold: `0.9`.

**2. `packages/spa-core/php/Api/Concerns/ClassifiesSpam.php` (new) — storage glue.** Modelled
directly on `Concerns/FiltersBlockedChannels.php` (same shape: read per-user config, emit a SQL
clause, expose a PHP-side check), so it drops into other handlers later.

- Model: `pconfig` cat `spa` key `spam_model` — one JSON row
  `{tokens: {w: [spam, ham]}, spam: N, ham: M}`, pruned to 2000 tokens (~40KB). One row, so
  `load_pconfig()` cost stays a single fetch.
- Verdict: `iconfig` cat `spa` key `spam` (`'1'`/`'0'`), via core's `set_iconfig(&$item, …)` /
  `get_iconfig()` (`include/config.php:181-189`).
- Training label: `iconfig` cat `spa` key `spam_trained`, so retraining the same item untrains
  the previous label first and stays idempotent.
- `classifyItems(array &$items, int $uid)` — for `item_thread_top` items lacking a verdict:
  tokenize, score, `set_iconfig`.
- `spamSqlClause(bool $wantSpam)` — `AND item.id [NOT] IN (SELECT iid FROM iconfig WHERE
  cat='spa' AND k='spam' AND v='1')`.

**3. `packages/spa-core/php/Api/Handlers/Network.php`**

- Replace the broken `item_spam = 1` branch (`:230-233`) with
  `$sql_extra .= $this->spamSqlClause(true)` — this is the Spam folder query.
- Call `classifyItems($items, $uid)` after `applyViewerFollowing()` (`:364`), before the
  `formatItem` map (`:368-371`); append `'spam'` to the item's `flags[]` in that map.
- Leave `$sql_extra` unfiltered in the *normal* view — filtering spam in SQL there would wrongly
  exclude never-yet-classified items and would break `$rootCount`. The client-side drop below
  handles it.

**4. `packages/spa-core/php/Api/Handlers/Spam.php` (new) + `Router.php`** —
`POST /spa/spam` `{mid, spam: bool}`: untrain the prior label, train the new one, update the
item's verdict. `Auth::requireLocalJson()`. Register in `Router.php`'s table.

**5. Client**

- `src/modules/network/api.ts` — add `flags.includes('spam')` to `shouldDisplay()` (`:6-14`),
  gated so it does *not* apply when `params.spam` is set. `fetchDisplayablePage()` in
  `src/shared/stream/store/createStreamStore.ts:128-146` already loops up to 10 pages to refill
  client-filtered pages, so pagination survives without touching `rootCount`. Same file: make
  `parseNetworkParams()` (`:91-111`) actually read `spam`, and pass it through in
  `fetchNetworkStream()`.
- `packages/spa-core/src/lib/spam-api.ts` (new) — `markSpam(mid, isSpam)`, mirroring
  `packages/spa-core/src/lib/blocklist-api.ts`.
- `src/shared/stream/components/PostCard.tsx` — "Mark as spam" / "Not spam" in the existing
  "more" (⋮) dropdown next to the delete entry. **Two copies of this menu exist** (~`:1535-1548`
  and ~`:2332`); both need it. Invalidate the stream query on success so the post disappears.
- Spam folder — a "Spam" chip setting `?spam=1` in `src/modules/network/views/StreamFilters.tsx`
  and `src/modules/network/widgets/StreamFiltersWidget.tsx`, alongside the existing
  star/dm/conv chips. No new module or route.
- i18n: `post.mark_spam`, `post.not_spam`, `network.spam` across `en`/`de`/`hi` plus
  `locales/namespaces/types.ts` — a key missing from one locale is a build error here, not a
  runtime warning.

### Known ceilings (deliberate — mark with `ponytail:` comments when built)

- Token model in one capped (2000-token) `pconfig` JSON row. Upgrade path: a real token table if
  vocabulary or write concurrency demands it.
- Up to ~30 `iconfig` INSERTs on a cold page — one-time per item, cache hit thereafter.
- No SQL-level exclusion in the normal stream. Upgrade path once volume makes client-side
  dropping insufficient; it costs the same `rootCount` accounting problem blocked channels
  already have.
- Not wired into `Channel.php` / `Pubstream.php` — the trait is shaped so it's two lines each
  when wanted.

### Verification (when this gets picked up)

- `php packages/spa-core/php/Api/SpamClassifier.test.php` — assert-based self-check on the pure
  class, no Hubzilla bootstrap: tokenizer output; cold start returns `null`; after 10 obvious
  spam / 10 obvious ham a spam-like body scores `> 0.9` and a ham-like one `< 0.5`;
  train-then-untrain restores the model exactly; `prune()` respects the cap.
- `npx tsc -b` clean (catches any missing i18n key), `npm run build` clean.
- In the browser: mark ~10 posts spam and ~10 not-spam; confirm nothing is hidden before the
  10/10 threshold is crossed, then confirm a matching post drops out of `/network` and appears
  under the Spam chip.
- `GET /spa/network?spam=1` returns JSON rather than erroring — it currently would error.

## Post filter rules (Thunderbird-style)

**Status: planned, not started.**

An ordered list of named, user-authored rules — each a set of match conditions plus a set of actions —
evaluated automatically against incoming posts, in the shape of Thunderbird's Message Filters. Today
the SPA has four separate filtering mechanisms and none of them compose: per-connection regex
(`abook_incl`/`abook_excl`, stored by `Handlers/Connections.php:400-401`, surfaced in
`ConnectionEditorModal.tsx:478-502`, enforced only by core on delivery), a channel-wide
include/exclude expression (`Settings.php:274`, `ChannelSection.tsx:170-175`), an NSFW keyword list
(`packages/spa-core/src/lib/nsfw.ts`), and a per-viewer block list
(`Concerns/FiltersBlockedChannels.php`). None can express "posts from X mentioning Y → file into
folder Z, and stop".

**The engine already exists.** Core ships `Zotlabs\Lib\MessageFilter` — a production rule evaluator
with `&&` / `||` / newline composition, an include/exclude split, and rule forms for body substring,
`/regex/`, `#hashtag`, `@mention`, `$category`, `lang=`, `until=`, and a generic `?field OP value`
tester (`~=`, `==`, `!=`, `//`, `>=`, `<`, `&`, `{}`, …) over any item column. That is the entire
conditions half of a Thunderbird filter, already written, already exercised by core's delivery path
(`post_is_importable()`, `include/items.php:3633`), and already user-facing in this app. This feature
writes no matcher of its own — it writes a compiler from a friendly UI down to that DSL, and an
action executor on the other side.

### Design decisions already made

- **Matching engine: core's `MessageFilter`, not a new matcher.** A bespoke matcher was rejected —
  the DSL is shipped, tested by core, and these users already type it in Settings ▸ Channel. Its
  include/exclude split gives us **negation for free**: positive conditions compile into the
  `include` string, negated ones into `exclude`, and `evaluate()` returns `false` the moment any
  exclude rule matches.

- **`?field` is flat-key only.** `test_condition()` does `$item[trim($key)]` with no dot-path
  support, so `?author.xchan_name` silently evaluates the empty string — an easy and invisible
  mistake. Before evaluating, the trait injects flat helper keys onto a *copy* of the item row:
  `filter_author_name`, `filter_author_addr`, `filter_owner_addr`, `filter_source`
  (`author.site_project`, the same fallback `MessageFilter` itself uses to populate `app`), and
  `filter_host` (`parse_url($item['plink'], PHP_URL_HOST)`). Six lines, no core patch. The condition
  builder's field dropdown maps onto these plus the native `#`/`@`/`$`/`lang=` forms.

- **Rules evaluate at read time, in the SPA's own endpoints.** Consistent with
  `FiltersBlockedChannels.php`'s stated stance ("filtering here is native to the SPA's own read
  endpoints and never goes through the addon's hook pipeline") and with the spam-filter section
  above. An `item_stored` addon hook was rejected: it needs a new addon in `utsukta-hub-addons`,
  never applies to posts already in the DB, and needs a backfill job every time a rule is edited.
  Read-time evaluation is retroactive by construction.

- **Storage: `pconfig` cat `spa` key `filters`, one JSON array.** Same shape as
  `Handlers/WidgetLayout.php` — const-guarded private `validate()`, `set_pconfig(…, json_encode())`,
  `del_pconfig` on empty — and cat `spa` is already dumped wholesale to the client at boot by
  `Handlers/Pconfig.php`, so the rule list reaches the UI with no extra fetch. Caps:
  `MAX_RULES = 16`, `MAX_CONDITIONS = 8`, `MAX_ACTIONS = 4`, value strings 256 bytes.

  ```json
  {
    "id": "r1", "name": "Mute politics", "enabled": true, "stop": true,
    "match": "all",
    "conditions": [
      { "field": "hashtag", "op": "is",       "value": "politics" },
      { "field": "author",  "op": "contains", "value": "bot", "negate": true }
    ],
    "actions": [ { "type": "file", "value": "Politics" }, { "type": "hide" } ]
  }
  ```

  A sibling `rev` integer is bumped on every save — see the verdict cache.

- **Verdict cache: `iconfig` cat `spa`** — `filter_rev` (the `rev` the item was scored under),
  `filter_hidden` (`'1'`/`'0'`), `filter_by` (name of the rule that fired), via core's
  `set_iconfig(&$item, …)` / `get_iconfig()` (`include/config.php:181-189`), same as the spam
  section. The `rev` comparison is what makes an edited rule re-apply without a backfill job, and
  what stops the one-shot actions (star/file/delete) re-firing on every page load.

- **Ordering and `stop`.** Rules run top to bottom; `stop: true` ends processing for that item
  (Thunderbird's "Stop filter execution"). Without it later rules still apply, so a post can be both
  filed and hidden.

- **Structured condition builder, not a raw expression box.** The rule is stored as the structured
  JSON above and compiled to the DSL **server-side**, so the client never needs to know the DSL and
  there is exactly one compiler. The cost is that the builder can only express what the dropdowns
  cover; the raw-expression escape hatch was rejected as two code paths for one feature.

- **Delete is guarded, not banned.** Three guards: it reuses `Handlers/Item.php`'s existing
  `case 'delete'` path (`:177`) so ownership and federation stay correct; the rule editor requires a
  second confirm before a delete action can be saved; and saving is gated on a mandatory **dry run**
  reporting how many of the last 200 stream items the rule would have destroyed.

- **The dry run rides on the existing settings POST**, not a new route:
  `POST /spa/settings/filters` with `{ test: <rule> }` evaluates the rule against the caller's most
  recent 200 network items and returns `{ count, samples: [{ mid, title, author, created }] }`
  without saving. One branch, no `Router.php` change.

### Implementation shape

**1. `packages/spa-core/php/Api/Concerns/AppliesPostFilters.php` (new) — engine glue.** Modelled on
`Concerns/FiltersBlockedChannels.php` (read per-user config → emit a SQL clause → expose a PHP-side
check), so it drops into other handlers later.

- `filterRules(int $uid): array` — decode and validate the pconfig blob. The no-rules path must cost
  one `get_pconfig` and nothing else.
- `compileRule(array $rule): array` — the whole builder → DSL compiler, returning
  `[$include, $exclude]`. Roughly one line per field: `body/contains → ?body ~= v`,
  `body/regex → ?body // v`, `title → ?title ~= v`, `author → ?filter_author_name ~= v`,
  `author_addr → ?filter_author_addr == v`, `hashtag → #v`, `mention → @v`, `category → $v`,
  `language → lang=v`, `source → ?filter_source ~= v`, `host → ?filter_host == v`,
  `type → ?obj_type == Event|Question|Note`, `is_dm → ?item_private == 2`,
  `is_private → ?item_private > 0`. Conditions joined with ` && ` (match=all) or ` || `
  (match=any); `negate: true` conditions go to `$exclude` instead.
- `applyFilters(array &$items, int $uid)` — for each `item_thread_top` item whose `filter_rev` is
  missing or stale: build the flat-key copy, run each enabled rule through
  `new MessageFilter($copy, $inc, $exc, ['plaintext' => …])->evaluate()`, execute the actions of
  matching rules, write the verdict, honour `stop`. Delete actions splice the item out of `$items`.
- Action execution reuses what exists — **star**: the `item_starred` update from `Item.php`'s
  `case 'star'`; **file** / **category**:
  `store_item_tag($uid, $iid, TERM_OBJ_POST, TERM_FILE|TERM_CATEGORY, $name, '')`, exactly as
  `Item.php:1912` (`saveto`) does, so filed posts are immediately reachable through the stream's
  existing `?file=` / `?cat=` filters; **delete**: `Item.php`'s delete path; **hide**: verdict only.
- `filteredSqlClause(): string` — `AND item.id IN (SELECT iid FROM iconfig WHERE cat='spa' AND
  k='filter_hidden' AND v='1')`, for the review view.

**2. `packages/spa-core/php/Api/Handlers/Network.php`**

- Call `applyFilters($items, $uid)` after `applyViewerFollowing()` (`:364`) and before the
  `formatItem` map (`:368-371`) — `fetch_post_tags()` has already run by then, so `$item['term']` is
  populated and `MessageFilter`'s `#`/`@`/`$` forms actually work.
- New `?filtered=1` param → `$sql_extra .= $this->filteredSqlClause()`; that is the review view.
- Leave `$sql_extra` unfiltered in the *normal* view — a SQL exclusion there would wrongly drop
  never-yet-scored items and would break `$rootCount`. Same trade-off, same reasoning as the spam
  section; the client-side drop below handles it.

**3. `packages/spa-core/php/Api/Concerns/FormatsItems.php`** — append `'filtered'` to the `flags[]`
array (`:303-315`) when `filter_hidden` is set, plus a `filtered_by` string field. `flags[]` is
already round-tripped verbatim to the client, so no response-shape change is needed.

**4. `packages/spa-core/php/Api/Handlers/Settings.php`** — one `case 'filters':` in each switch
(`get()` at `:26`, `post()` at `:895`) plus `getFilters()` / `postFilters(int $uid, array $data)`.
The POST covers both the save path (validate → `set_pconfig`, bump `rev`) and the `{ test: … }`
dry-run branch. No `Router.php` change — the section name comes from `\App::$argv[2]`.

**5. Client — settings**

- `src/modules/settings/index.ts` — one entry in `SETTINGS_ITEMS`
  (`{ path: "filters", label: () => useI18n().t("settings.title_filters") }`); routes derive from
  that array automatically.
- `src/modules/settings/views/SettingsView.tsx:9-22` — add
  `filters: lazy(() => import("./sections/FiltersSection"))` to `SECTIONS`.
- `src/modules/settings/views/sections/FiltersSection.tsx` (new) — follows
  `BlockedChannelsSection.tsx`'s hand-rolled `createQueryResource` + optimistic-mutate shape, not
  `useSectionForm` (this is a list editor, not a flat form). Rule cards with enable toggle, reorder,
  edit, delete; the editor is a `<For>` over condition rows (`[field ▾] [operator ▾] [value] (±)`)
  and action rows, with a match-all/any selector. Reuse `inputClass` from
  `src/modules/settings/store/FormHelpers.tsx` and `SubPageContent`.
- `src/modules/settings/api/api.ts` — `fetchFilters()`, `saveFilters()`, `testFilter()`.

**6. Client — stream**

- `src/modules/network/api.ts` — add `flags.includes('filtered')` to `shouldDisplay()` (`:6-14`),
  suppressed when `params.filtered` is set. `fetchDisplayablePage()`
  (`src/shared/stream/store/createStreamStore.ts:123-146`) already loops up to 10 backend pages to
  refill pages emptied by client-side filtering, so pagination survives without touching
  `rootCount`.
- Same file: make `parseNetworkParams()` (`:91-111`) actually read `filtered`. Note that this
  function already silently drops several params declared on `NetworkParams` (`verb`, `cat`,
  `xchan`, `net`, `unseen`, `liked`) — easy to add one more, equally easy to forget.
- `src/modules/network/widgets/StreamFiltersWidget.tsx` — one entry in the `CHIPS` array
  (`{ key: 'filtered', labelKey, Icon }`), alongside `star`/`pf`/`conv`/`dm`.
- `src/shared/stream/components/PostCard.tsx` — in the review view, a small "Filtered by *rule
  name*" badge fed by `filtered_by`. **Two copies of the post chrome exist in this file**; both need
  it.
- i18n: a new `filters` namespace across `en`/`de`/`hi` plus `locales/namespaces/types.ts` — a key
  missing from one locale is a build error here, not a runtime warning.

### Known ceilings (deliberate — mark with `ponytail:` comments when built)

- Negated conditions only compose correctly under **match=all**. `MessageFilter`'s exclude list
  short-circuits the whole evaluation, which is right for AND and wrong for OR. The editor greys out
  the negate toggle in "match any" mode. Upgrade path: evaluate negatives as a second `MessageFilter`
  pass and combine the results in PHP.
- Read-time only: a rule never fires for a post you never load, and its effects appear the first time
  you view it rather than on arrival. Upgrade path: the `item_stored` addon hook, with this trait as
  the shared implementation.
- Rules live in one capped (16-rule) `pconfig` JSON row. Upgrade path: a real table if anyone hits
  the cap.
- Up to ~30 `iconfig` writes on a cold page — one-time per item, cache hit thereafter. Same profile
  as the spam section, and the two share that cost when both ship.
- Only `Network.php` is wired. `Channel.php` / `Pubstream.php` are two lines each when wanted.
- Delete is irreversible and federates. The dry run is the only safety net — no undo, no quarantine.
- No "run filters now" button; bumping `rev` makes the next stream load do it.

### Verification (when this gets picked up)

- `php packages/spa-core/php/Api/AppliesPostFilters.test.php` — assert-based self-check on
  `compileRule()` alone, no Hubzilla bootstrap: each field/operator pair produces the expected DSL
  string; negated conditions land in `exclude`; match=all vs match=any pick the right joiner;
  over-cap rules are rejected by `validate()`.
- One `MessageFilter` round-trip check against a fixture item array (needs the core autoloader): a
  `#hashtag` rule matches, a `?filter_author_name ~=` rule matches, a negated rule blocks.
- In the browser: create "author contains X → file as Testing", load `/network`, confirm the post
  shows under the Testing folder chip (`?file=Testing`) and that a second page load performs no
  further writes. Edit the rule and confirm the `rev` bump forces re-evaluation.
- Create a hide rule → the post disappears from `/network` and reappears under the Filtered chip
  with the correct rule-name badge.
- Dry-run a delete rule → the reported count matches what actually disappears once it is enabled.
- `npx tsc -b` clean (catches any missing i18n key), `npm run build` clean, deployed PHP handlers
  match source (`diff` check).

## Collaborative editing (Yjs + y-websocket)

**Status: planned, not started.** This entry covers the transport and authorization layer only —
no editor is bound to it. Wiring an actual surface is a follow-up per surface.

Several editing surfaces are single-user today (notepad, wiki, articles, webpages, excalidraw
scenes) and two people cannot edit the same document at once. Hubzilla's backend is PHP with no
realtime transport — the app currently fakes liveness with 3s polling (`src/modules/chat/store.ts`)
and an SSE endpoint for notifications (`NotificationsAside.tsx:974`).

### Design decisions already made

- **y-websocket, not a polling provider.** Two cheaper rungs were considered and rejected: (a)
  mtime polling with a "someone else is editing" banner and a save-conflict merge, ~40 lines and no
  CRDT at all; (b) a custom Yjs provider riding HTTP polling against a PHP blob-store endpoint, no
  new process. Sub-100ms sync was the requirement, so rung (c) won. The cost is real and stays
  written down here: a Node process to run, supervise, and TLS-terminate alongside PHP, in dev and
  in production.
- **Node knows nothing about Hubzilla permissions.** PHP mints a short-lived HMAC token; Node
  verifies it. One `hash_hmac` line each side — no shared DB, no per-connection PHP round-trip, no
  cookie forwarding across ports.

  ```
  browser                     PHP (/spa)                  Node (:1234)
     │  GET /spa/collab/token?doc=wiki:abc
     │─────────────────────────►│ requireLoggedIn()
     │                          │ resolve doc → perm check
     │◄─── {url, room, token,   │ HMAC(room|xchan|exp|write)
     │      user:{name,color}}  │
     │  ws://…/collab?room=…&token=…                     │
     │──────────────────────────────────────────────────►│ verify HMAC
     │                          │◄── GET /spa/collab/content (seed, server token)
     │◄═══════ y-protocol sync + awareness ═════════════►│
     │                          │◄── POST /spa/collab/persist (5s debounce)
  ```

- **Rooms are seeded server-side, not client-side.** Node fetches the current content from PHP on
  room creation and applies it before accepting any client. If clients seeded, two simultaneous
  first-joiners would each insert the document body and Yjs would faithfully merge both copies.
- **Hubzilla stays the source of truth.** The Y.Doc is live editing state only. On a 5s debounce
  after the last edit Node POSTs the flattened content back to PHP, which writes it through the
  owning resource's normal save path — so the classic web UI, federation, and every non-SPA client
  see the result.
- **Read-only is enforced in Node**, not the client: a connection whose token carries `write:false`
  receives sync messages but its inbound update frames are dropped.
- **Awareness (live cursors, presence) is free** — it ships with Yjs, no extra work.
- **`y-indexeddb` gives offline-first for free** and composes with the existing offline layer:
  edits made while the daemon is down merge on reconnect rather than being lost.

### Implementation shape

**New workspace package `packages/collab-server/`** (`packages/*` is already globbed by the root
`workspaces` field):

- `package.json` — deps `yjs`, `y-websocket`, `ws`, `y-leveldb`; a `start` script.
- `src/server.mjs`, ~150 lines:
  - `ws` server; on `upgrade`, parse `room` + `token`, verify HMAC against
    `process.env.COLLAB_SECRET`, reject 401 on bad signature or expiry.
  - `setupWSConnection` from `y-websocket/bin/utils.js` for the protocol itself.
  - `y-leveldb` persistence so a crash doesn't lose in-flight CRDT state.
  - Room-create hook seeding from `GET {HUB_URL}/spa/collab/content?room=…` with a server-signed
    token; debounced `POST {HUB_URL}/spa/collab/persist`.
- `README.md` — env vars, a systemd unit, and the nginx `location /collab` upgrade block.

**New handler `packages/spa-core/php/Api/Handlers/Collab.php`** — follows the existing shape
(`Handlers/Notes.php` for style, `Api/Auth.php` + `Api/Response.php` for the helpers):

- `GET /spa/collab/token?doc=<type>:<id>` — `Auth::requireLoggedIn()`, resolve the doc ref through
  the owning resource's existing permission check, return
  `{ url, room, token, exp, write, user: { xchan, name, color } }`.
- `GET /spa/collab/content?room=…` and `POST /spa/collab/persist` — server-token only.
- Secret from `Config::Get('spa','collab_secret')`, auto-generated with `random_bytes(32)` on first
  read; `Config::Get('spa','collab_url')` for the ws endpoint. Both admin-settable.
- A `DOC_TYPES` map (`wiki`, `notes`, `webpages`, …) pairing each doc type with its resolver and
  permission check, so adding a surface later is one array entry rather than a new endpoint.

**Edits:**

- `packages/spa-core/php/Api/Router.php` — one line, `'collab' => Handlers\Collab::class`.
- `packages/spa-core/src/lib/collab.ts` (new) — `createCollabDoc(docRef)` → `{ ydoc, awareness,
  status }`, fetching the token via the existing `apiFetch` (`lib/fetch.ts`), wiring
  `WebsocketProvider` + `IndexeddbPersistence`, registering `onCleanup`. Reachable as
  `@utsukta/spa-core/lib/collab` — the existing `./lib/*` export glob already covers it, so
  `packages/spa-core/package.json` needs no change.
- Root `package.json` — add `yjs`, `y-websocket`, `y-indexeddb`; a `collab:dev` script.
- `vite.config.ts` — add `"/collab": { ...hubProxy, ws: true }` to `server.proxy`.
- `build-sw.mjs` — `/spa/collab/token` must be `NetworkOnly` (short-lived tokens must never be
  served from cache), alongside the existing `POLL_URL` NetworkOnly entry.
- `hz-ddev/.ddev/config.yaml` — bump `nodejs_version` from `"16"` to `"20"` (y-websocket needs it),
  add `web_extra_daemons` for the server and `web_extra_exposed_ports` for 1234.
- `hz-ddev/.ddev/nginx_full/nginx-site.conf` — `location /collab` with `proxy_pass`,
  `Upgrade`/`Connection` headers, and a long `proxy_read_timeout`.
- `src/docs/dev/en/collab.md` (new) + a link in `src/docs/dev/en/index.md`.

### Known ceilings (deliberate — mark with `ponytail:` comments when built)

- A Node process is now a hard runtime dependency of the feature. If it is down, collaborative
  surfaces must degrade to single-user editing rather than breaking — the client needs that
  fallback path, and it is the main reason not to bind an editor in the same pass.
- Persistence is a 5s debounce, so a crash can lose up to 5s of edits from Hubzilla's DB (not from
  the CRDT — `y-leveldb` still has them). Upgrade path: shorten the debounce or persist on last
  client disconnect too.
- No per-document locking against non-SPA writers. Someone editing the same wiki page from the
  classic web UI while a room is live will have their write clobbered by the next debounce.
  Upgrade path: compare-and-swap on the resource's revision in `persist`.
- Only Yjs-bindable formats can ever be collaborative: text, code, markdown, and (with
  hand-written glue) excalidraw. Spreadsheets, docx, pdf, epub and 3D have no binding and stay
  read-only.

### Follow-ups (increasing cost)

- CodeMirror 6 + `y-codemirror.next` for notepad and wiki — the cheap one, a real binding exists.
- Hand-written glue for excalidraw scenes via `updateScene` + `onChange` — no official binding.
- ProseMirror/TipTap for the BBCode composer in `src/shared/editor` — biggest payoff, but means
  replacing the editor core.

### Verification (when this gets picked up)

- `ddev restart`, confirm the daemon is up: `ddev exec curl -sI localhost:1234`.
- Token endpoint rejects an anonymous caller (401) and returns a signed token for a logged-in one:
  `ddev exec curl -s '…/spa/collab/token?doc=notes:1'`.
- Tamper check — flip one byte of the token, confirm the ws upgrade is refused with 401.
- Convergence: a scratch page opening two `createCollabDoc("notes:1")` instances against the same
  room in two tabs; type in one, assert the other's `ytext.toString()` matches within 200ms and
  that both awareness states list two users.
- Persistence: edit, wait 6s, confirm the note body changed in the DB
  (`ddev mysql -e "select body from item where …"`), and that a hard reload with the room evicted
  from Node re-seeds the same content.
- Offline: kill the daemon mid-edit, keep typing, restart it, confirm the offline edits merge in
  rather than being lost.
- Read-only: mint a token with `write:false`, confirm remote edits arrive but local ones never
  propagate.
