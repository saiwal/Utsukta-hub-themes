# Backlinks, graph and unified search

## Links: `Handlers/Links.php`

One build pass over a channel's **own** writing produces the whole graph:

- **Nodes**: articles, cards, webpages (`ITEM_TYPE_WEBPAGE`, not blocks or
  layouts; url from the `system`/`WEBPAGE` page link), notes (`ITEM_TYPE_CUSTOM`), wiki pages and the
  `RECENT_POSTS` (500) newest own top-level wall posts always; older posts only
  when they link or are linked. Drafts and anything else unpublished
  (`item_unpublished = 1`, except wiki pages, which are always stored that
  way) are left out entirely. Every query excludes
  `verb = 'Add'`: each article/card create or edit stores an empty
  collection-sync `Add` row flagged thread-top, which otherwise shows up as a
  blank item. Wiki pages store
  one row per revision, so only `MAX(id)` per `(resource_id, title)` counts.
- **Edges**: `[[Title]]` / `[[Title|label]]`, plus permalinks to this hub —
  `/display/<uuid>`, `/item/<uuid>`, `/articles/<nick>/<slug|uuid>`,
  `/cards/<nick>/<slug|uuid>`, `/page/<nick>/<page link>`, `/wiki/<nick>/<wiki>/<page>` (absolute, or
  relative after `=`, `(`, a quote or a space). Links into another channel's
  content are ignored. Reshares count, since a share block carries a link.
- `[[target]]` forms, parsed once by `Links::parseTarget()` into index keys
  tried in order (case-insensitive except slugs):
  `[[article:X]]` / `[[card:X]]` / `[[page:X]]` → `a:`/`c:`/`p:` slug, then
  `at:`/`ct:`/`pt:` title;
  `[[Wiki/Page]]` → `wn:wiki/page`, then as a plain title; `[[Title]]` →
  `wn:<current wiki>/title` (wiki sources only), then `t:` (wiki pages first,
  then articles/cards/webpages). Nothing matches → a `missing` node. Debris (one
  character, or starting with `/`) is never a target.
- `Links::rewriteWikilinks()` turns resolvable targets into real links in the
  body's format (bbcode/markdown/html), resolved for the *observer* so nobody
  is linked to what they can't see. `Wiki.php` runs it (relative) before
  core's `convert_links`, leaving same-wiki and unresolved targets to core;
  `Item.php` runs it (absolute, `z_root()`) at save on posts, comments and
  edits, beside the `[share]`/`[card]` expansion, since posts federate. Notes
  are never linked from a post. `resolve` and the `/link/*title` route (a splat,
  for `Wiki/Page`) use the same parser.
- Every endpoint slices the same cached graph (`Zotlabs\Lib\Cache`, 15 min, key
  `spa:links:<uid>`). `Router::dispatch` drops the key on any non-GET by a local
  channel, so SPA edits show up right away; edits made in the classic UI can lag
  by up to 15 minutes.
- **Visitors**: notes are dropped; other types need `view_stream` /
  `view_pages` (articles, cards, webpages) / `view_wiki` and must pass `item_permissions_sql`. An edge
  survives only when both ends survive.
- The wiki addon's classes load only when one of its hooks runs —
  `Links::wikiLoaded()` requires them on demand.

```
GET /spa/links/graph?nick=          → { nodes, edges }
GET /spa/links/backlinks?path=      → { node, from[] }
GET /spa/links/resolve?nick=&title= → { url | null }
```

## Frontend: `src/modules/graph`

- `/graph/:nick`: d3-force on a canvas, with pan/zoom/drag, Fit all and Reset
  view, type chips (hidden types saved in localStorage `hz-graph-hidden`) and a
  find-in-graph field. Clicking a dot selects it and opens a details panel
  (links to / linked from, Open); it never navigates on its own. Labels are
  placed greedily by priority with an overlap test, all of them below
  `SMALL_GRAPH` (40) visible nodes; the focused node's label goes above its dot,
  clear of the cursor. Pseudo-filter chip: **Unlinked posts** (off = drop posts with no *visible* link). The
  canvas is `aria-hidden`; the same nodes are also an `sr-only` link list.
- **Toggle**: a `frontendFeature` (off by default, Settings → Integrations)
  with `channelScoped: true`. On someone else's channel the *owner's* switch
  decides: `Nav.php` returns their pconfig overrides as
  `subject_frontend_overrides` (null on your own pages), `useLayoutChrome` feeds
  them to `setChannelFrontendOverrides`, and `isModuleActive` uses them for
  channelScoped modules (undefined while loading lets the route through). The
  same switch adds the `graph` channel tab (`Links::enabledFor`) and gates
  `/spa/links/graph` (404) and `backlinks` (empty). `resolve` is never gated,
  and `/link/:title` lives in the always-on `wikilink` module.
- `graph.backlinks` widget ("Linked from"): shown by default on wiki, articles,
  cards and post pages; renders nothing off a single-item page.
- `[[Title]]` in rendered bodies: `lib/wikilinks.ts` (called from
  `renderBody`/`bbcodeDisplay`) turns it into `<a class="wikilink"
  href="/link/Title">`. A capture-phase click listener in `wikilink/index.ts` adds
  `?nick=<page subject>` (renderBody can't know it). `/link/:title` resolves the
  title and replaces itself with the target. `[[i]]`-style bbcode debris
  (one character, or starting with `/`) is never treated as a link, on either
  side.

## Unified search: `Handlers/Find.php`

`GET /spa/find?term=&types=&tag=&dbegin=&dend=&limit=` (local channel only)
searches posts (everything in your item table), articles, cards, webpages, notes, the
latest wiki revisions, files (`attach.filename`) and bookmarks (`menu_item`).
A tag matches hashtags *and* categories, and skips files/bookmarks; a date range
skips bookmarks. Bookmarks pointing into this hub come back as SPA paths.

UI (`src/shared/views/search/`): `search.ts` (`useContentSearch`, debounced,
plus the palette open signal), `ResultList`, `SearchFilters`, and
`CommandPalette` (Ctrl/Cmd+K, mounted in `Layout`; nav-item jumps first, then
content). On mobile, the app drawer's search runs the same `useContentSearch`
under its app matches. Visitors only get the app jump.

Notes link as `/notepad?note=<uuid>` (`Notes.php` `note` filter).

Checks: `Handlers/Links.test.php` (`npm run test:php -- Links`),
`node --experimental-strip-types packages/spa-core/src/lib/wikilinks.test.ts`.
