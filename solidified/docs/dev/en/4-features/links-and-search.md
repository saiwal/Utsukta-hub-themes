# Backlinks, graph and unified search

## Links: `Handlers/Links.php`

One build pass over a channel's **own** writing produces the whole graph:

- **Nodes**: articles, cards, notes (`ITEM_TYPE_CUSTOM`) and wiki pages always;
  own top-level wall posts only when they link or are linked. Wiki pages store
  one row per revision, so only `MAX(id)` per `(resource_id, title)` counts.
- **Edges**: `[[Title]]` / `[[Title|label]]`, plus permalinks to this hub —
  `/display/<uuid>`, `/item/<uuid>`, `/articles/<nick>/<slug|uuid>`,
  `/cards/<nick>/<slug|uuid>`, `/wiki/<nick>/<wiki>/<page>` (absolute, or
  relative after `=`, `(`, a quote or a space). Links into another channel's
  content are ignored. Reshares count, since a share block carries a link.
- `[[Title]]` inside a wiki page tries the same wiki first, then any node with
  that title, case-insensitively (wiki pages before articles/cards). Nothing
  matches → a `missing` node.
- Every endpoint slices the same cached graph (`Zotlabs\Lib\Cache`, 15 min, key
  `spa:links:<uid>`). `Router::dispatch` drops the key on any non-GET by a local
  channel, so SPA edits show up right away; edits made in the classic UI can lag
  by up to 15 minutes.
- **Visitors**: notes are dropped; other types need `view_stream` /
  `view_pages` / `view_wiki` and must pass `item_permissions_sql`. An edge
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
  clear of the cursor. A post whose links all go to hidden types is dropped. The
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
searches posts (everything in your item table), articles, cards, notes, the
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
