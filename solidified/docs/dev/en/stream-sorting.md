# Stream sorting

How the network and channel streams decide what order posts come back in, and
how the shared `SortSelect` control drives it.

## The pieces

| Layer | File |
|---|---|
| ORDER BY clauses (expression + join) | `packages/spa-core/php/Api/Concerns/StreamOrdering.php` |
| Ranking cache | `packages/spa-core/php/Api/Concerns/CachesRanking.php` |
| Consumers | `Handlers/Network.php`, `Handlers/Channel.php` |
| Sort data (orders, ranges, `rangeToDbegin`) | `src/shared/stream/filters/ranked.ts` |
| UI control | `src/shared/stream/filters/SortSelect.tsx` |
| View-mode control (same collapse pattern) | `src/shared/stream/filters/ViewSwitcher.tsx` |
| Panel state + floating placement | `src/shared/stream/filters/createPopover.ts` |
| Network wiring | `modules/network/views/StreamFilters.tsx`, `modules/network/api.ts` |
| Channel wiring | `modules/channel/widgets/ChannelFeedShell.tsx` |
| Poll guard | `src/shared/stream/store/createStreamStore.ts` |

The client sends `?order=<key>`; everything else about ordering is decided
server-side. `range` never reaches the API — see [Time ranges](#time-ranges).

## The orders

Three are **chronological** (a plain indexed column) and four are **ranked**
(computed from reaction counts). Only the ranked ones are new; `created`,
`commented` and `unthreaded` predate this system.

| Key | Label | ORDER BY | Ranked |
|---|---|---|---|
| `created` | Latest | `item.created` | no |
| `commented` | Active | `item.commented` (timestamp of the newest reply) | no |
| `unthreaded` | Unthreaded | `item.created`, **and** switches the query to flat mode | no |
| `top` | Top | like count | yes |
| `hot` | Hot | log(likes) + age term | yes |
| `discussed` | Most discussed | comment count | yes |
| `controversial` | Controversial | engagement × how even the split is | yes |

`unthreaded` is the only order that also changes the *shape* of the response:
it sets `nouveau`, so the handler returns a flat list of every matching row
(comments and reactions included) rather than thread roots. The rest only
change the ORDER BY.

Unknown keys fall back to `created` — `StreamOrdering::isValid()` whitelists,
so a hand-edited URL can't inject SQL through `?order=`.

### The counts

All four ranked orders count sibling `item` rows, not columns. Hubzilla stores
a like as its own `item` row whose `thr_parent` is the mid of the thing it
likes, so there is no `like_count` column to sort on. Conceptually the count
is:

```sql
(SELECT COUNT(DISTINCT r.author_xchan) FROM item r
  WHERE r.uid = item.uid AND r.thr_parent = item.mid
    AND r.item_thread_top = 0 AND r.obj_type != 'Answer'
    AND r.verb = 'Like' AND <item_normal flags>)
```

`COUNT(DISTINCT author_xchan)` is what makes a like worth one vote no matter
how many duplicate activities federation delivers. `obj_type != 'Answer'`
keeps poll votes out, and the `item_normal` flag set (shared with
`ReactionCounts::normalFlags()`) keeps deleted/hidden/unpublished rows out.

These are deliberately the **same** counts `ReactionCounts::subqueries()` uses
to produce the `like_count` / `comment_count` the client renders on each post,
so a post's rank always matches the numbers shown on it. For *ranking* they are
computed once per candidate through a joined aggregate rather than as a
per-row subquery — see [How the counts are joined](#how-the-counts-are-joined-and-why).

### `top`

Straight like count, descending. Ties fall back to nothing in particular —
add a secondary sort if that ever matters.

### `hot`

Reddit's hotness, minus the up/down sign handling (a Hubzilla dislike is not a
downvote — it doesn't subtract from a post's score):

```
LOG10(GREATEST(likes, 1)) + created_epoch / 45000
```

The two terms trade off against each other:

- **`LOG10(likes)`** — each order of magnitude of likes is worth one point.
  10 likes beat 1 like by exactly as much as 100 beat 10. `GREATEST(likes, 1)`
  keeps the log defined (and 0 likes → 0 points) rather than `-inf`.
- **`created_epoch / 45000`** — 45000 seconds is 12.5 hours, so a post gains
  one point per 12.5 hours of newness.

One point is the unit of both, so: **a post needs 10× the likes to outrank one
posted 12.5 hours later.** Nothing decays — an old post's score is fixed, and
new posts simply start higher, which is why `hot` needs no time range.

On a stream with few likes the log term is ~0 for everything and `hot`
degenerates into `created`. That is expected, not a bug.

### `discussed`

Reply count, descending. Note it counts the whole thread — nested replies
included — and counts *replies* only: the `Create`/`Update`/`EmojiReact`
allowlist, so thread-subscription `Follow`/`Ignore` activities and hidden
group-boost `Announce`s don't inflate it.

Distinct from `commented`/Active, which is a *timestamp*: `discussed` is "the
biggest conversations", `commented` is "the conversations that moved most
recently".

### `controversial`

```
(likes + dislikes) × (1 − |likes − dislikes| / GREATEST(likes + dislikes, 1))
```

Two factors:

- **Volume** — `likes + dislikes`. Nobody argues about a post nobody reacted to.
- **Balance** — the bracket is 1 when likes and dislikes are equal, and 0 when
  every reaction agrees. It scales the volume down the more one-sided the
  split is.

So 50 likes / 50 dislikes scores 100; 100 likes / 0 dislikes scores 0; 5/5
scores 10. A post needs *both* engagement and disagreement to rank.

`GREATEST(…, 1)` guards the divide-by-zero when a post has no reactions at all.

### Portability

`StreamOrdering::clause()` branches on `ACTIVE_DBTYPE` because three pieces
differ between the two databases Hubzilla supports:

| | MySQL | Postgres |
|---|---|---|
| epoch | `UNIX_TIMESTAMP(item.created)` | `EXTRACT(EPOCH FROM item.created)` |
| log base 10 | `LOG10(x)` | `LOG(x::numeric)` (1-arg `LOG` is base-10) |
| division | `a / b` | `a::numeric / b` — bigint/bigint truncates otherwise |

`db_getfunc()` in core has no entries for any of these, hence the local branch.

### How the counts are joined, and why

`StreamOrdering::clause($order, $uid)` returns **both** an ORDER BY expression
and the JOIN that expression reads from:

```php
['join' => 'LEFT JOIN ( … GROUP BY r.thr_parent ) rx ON rx.tp = item.mid ',
 'order' => 'COALESCE(rx.likes, 0)']
```

It did not always work this way. The first version inlined the count subqueries
directly into the ORDER BY, which `EXPLAIN` showed as a `DEPENDENT SUBQUERY`
plus a filesort: the database ran a fresh index dive **per candidate row**, on
the single-column `thr_parent` index (`key_len 764`, a wide varchar). Worse,
the expressions are textual — `controversial` mentions the like subquery twice
and the dislike subquery twice, so it paid *four* dives per row, and `hot` two.

The aggregate join replaces all of that with one grouped pass over the reaction
rows, materialised once and hash-joined to the candidates. Each count is now
computed once per candidate no matter how often the formula mentions it, and
`controversial` has the same query plan as `top`.

Two derived tables, joined only when the chosen order needs one:

- **`rx`** (`top`, `hot`, `controversial`) — likes and dislikes, `GROUP BY
  r.thr_parent`, joined on the root's `mid`.
- **`cx`** (`discussed`) — replies, **`GROUP BY r.parent`**, joined on the
  root's `id`.

That grouping difference is load-bearing, not an inconsistency: the reaction
counts only count direct reactions to the root, while
`commentCountSubquery()` correlates on `r.parent = item.id` and counts the
whole thread including nested replies. Grouping comments by `thr_parent` would
quietly redefine what "Most discussed" means.

What remains is a filesort over the full candidate set on a cold cache —
inherent to ranking without capping the candidate pool, which was a deliberate
choice in favour of exact rankings over approximate ones. The cache below is
what keeps that from being paid per page.

### Ranked results are cached for 15 minutes

Ranking is the expensive half of a ranked page; fetching the ten rows it picked
is trivial. And because pagination is `LIMIT/OFFSET`, scrolling to page 5 used
to re-rank the whole set five times over.

`Concerns/CachesRanking.php` stores the ordered parent-id list under
`Zotlabs\Lib\Cache` (already used in this API by `Linkmeta.php` and
`PasswordReset.php`) for **15 minutes**, so a whole scroll session — and every
revisit inside the window — costs one ranking pass.

- **Key**: `spa:rank:<scope>:<uid>:<order>:<md5 of the filter params>`. Every
  filter is in the key, or a tag-filtered stream would serve the unfiltered
  ranking; params are sorted so query-string order can't split the cache, and
  `start` is excluded on purpose so one ranking serves all its pages. The
  channel scope also carries the observer hash, since `item_permissions_sql()`
  fences visitors and two viewers of the same wall can legitimately rank
  differently.
- **Depth**: 1000 ids (~100 pages). This is *not* a candidate cap — the
  database still ranks the entire set; it only bounds how much of the sorted
  output is carried around. A page past it falls through to a plain paged
  query.
- **Scope**: ranked orders only. The chronological orders walk an index and
  stop at `LIMIT`; they're already cheap and stay live.

`meta.cached` reports whether a page was served from the ranking cache.

**The trade-off**: a ranked view can lag up to 15 minutes behind new likes.
That's consistent with the rest of the feature — live polling is already
disabled under ranked orders, so there's no second freshness path to
contradict it.
## Time ranges

`top`, `discussed` and `controversial` accumulate counts forever, so without a
window they return the same all-time-best posts on every visit. Those three are
listed in `RANGE_AWARE` and get a range picker; `hot` does not, because its age
term already is the window.

The range is a **UI-only URL param**. `rangeToDbegin()` maps it to a date and
the caller passes that as `dbegin`; `range` itself is never sent:

| `range` | `dbegin` |
|---|---|
| `day` | today − 1 day |
| `week` | today − 7 days |
| `month` | today − 30 days |
| `year` | today − 365 days |
| `all` | *(no dbegin)* |
| *absent* | same as `day` — see below |

**Absent means `day`, not `all`.** `DEFAULT_RANGE` in `ranked.ts` is what a
range-aware order resolves to with nothing in the URL, via `resolveRange()`.
An unbounded count sort is both the least useful (same all-time winners every
visit) and the most expensive query the stream can run, so it isn't the thing
you get by default.

The consequence to keep in mind when touching this: **absent and `all` are
different states**, so `all` has to be written to the URL explicitly. The two
`setOrder` helpers omit only `DEFAULT_RANGE` from the query string — omitting
`all` instead, as they originally did, would make "All time" unselectable.

Keeping `range` in the URL rather than only the derived date means the picker
round-trips exactly on reload instead of reverse-guessing a range from a date.

**An explicit `dbegin` always wins.** The network sidebar
(`StreamFiltersWidget`) has its own date-range control that writes `dbegin`
directly; both `parseNetworkParams` (network) and the load effect in
`ChannelFeedShell` only fall back to the range-derived date when no `dbegin`
is present.

## Interaction with flat (`nouveau`) mode

Both handlers force flat mode for certain filters — `search`, `file`,
`hashtags`, `verb`, `category`, `conv`, `unseen` on network; `search`,
`hashtags`, `category`, `dm`, `mid` on channel.

The flat query honours the same ORDER BY expression as the threaded one. This
matters: without it, `top` + a hashtag filter would silently return
chronological results, because the hashtag forced flat mode and the flat query
used to hardcode `ORDER BY item.created DESC`.

In the threaded path the parent query does the ordering and the follow-up
fetch re-applies that order in PHP by parent-id position — *not* by re-sorting
on a date column, which would throw a ranked order away.

`dend` ("jump to this date") still forces `created`, but only for the
chronological orders: "best posts before this date" is a legitimate query.

## Live updates are off under ranked orders

`createStreamStore.checkForNew()` polls for new posts by taking
`posts()[0].created` as its cursor — it assumes the first post is the newest.
Under any ranked order that assumption is false, so the poll would ask for the
wrong window. The store checks `RANKED_ORDERS` and skips polling entirely
while one is active; there's no "new posts" pill until the user switches back
to a chronological order.

`RANKED_ORDERS` lives in `ranked.ts` rather than `SortSelect.tsx` precisely so
the store can import it without pulling solid-icons and the dropdown into its
bundle.

## The control

`SortSelect` is responsive, not two components:

- **≥768px** — a segmented tab row; labels appear at `lg`, icons only below
  that. The row scrolls horizontally rather than wrapping, so the toolbar keeps
  its height whatever `available` list the caller passes.
- **<768px** — a single dropdown button (`icon + label + chevron`) opening a
  listbox, with the ranges as `FilterChip`s at the bottom.

The selected range is shown **in the order's own label** — "Most discussed
(Week)" — rather than as a separate always-visible control, and the range
options open in a **floating popover** anchored under the tab row. Both exist
for the same reason: a second inline group of range buttons resized the toolbar
every time a ranked order was picked. A chevron on the active range-aware tab
marks it as openable; clicking that tab again toggles the popover, and picking
a range closes it.

Props mirror `ViewSwitcher`'s: `order`, `range`, `onChange(order, range?)`, and
`available?` to restrict which orders are offered.

Picking a non-range-aware order clears `range`, so a stale `?range=` can't
linger in the URL where nothing reads it.

`ViewSwitcher` collapses the same way at a tighter breakpoint (`<640px`), since
below that it shares a row with the sort control and the compose/DM/search
buttons.

Both get their panels from `createPopover()` (`filters/createPopover.ts`):
open state, outside-click / Escape dismissal, and placement via
`useFloating()` — the same `@floating-ui/dom` wrapper `Tooltip.tsx` uses.
Panels render in a `<Portal>` with `position: fixed`, which matters because the
wide sort row is an `overflow-x-auto` scroller that would otherwise clip them;
`flip()`/`shift()` also keep a panel on screen near the viewport edge, where the
old `absolute left-0 top-full` could not. Dismissal checks the portalled panel
as well as the anchor, since the panel is no longer a DOM descendant of it.

### Which orders each module offers

**Network** offers all seven.

**Channel** offers `created`, `commented`, `top`, `discussed`, `unthreaded`
(`CHANNEL_ORDERS` in `ChannelFeedShell.tsx`). A wall is one person's posts, so
the discovery-oriented orders don't carry over: `hot` degenerates to `created`
without a firehose to rank against, and `controversial` needs a dislike volume
a personal wall rarely sees. The backend still accepts both on
`/spa/channel` — the restriction is purely which options the UI shows.

Pinned posts survive the ranked orders (they only drop out under `unthreaded`,
since `Channel.php` emits them only when `!$nouveau && $offset === 0`).

## User-facing docs

The orders are explained for end users under **Sort Order** in
`src/docs/user/en/network.md` (all seven, including how the hot and
controversial rankings behave) and `src/docs/user/en/channel.md` (the five a
wall offers, and why the other two are missing).

`SortSelect` is `use:helpable`, so those sections open in the help overlay when
a user clicks the control in help mode. The target defaults to
`network.sort_order`; channel passes `help="channel.sort_order"`. Both headings
carry a `<!-- sort_order -->` anchor comment so they keep resolving once the
docs are translated.

## Adding an order

1. Add the key to `StreamOrdering::clause()` — returning the ORDER BY
   expression and whichever aggregate join it reads from — and, if it isn't
   chronological, to `StreamOrdering::RANKED` (which also opts it into the
   ranking cache).
2. Add it to `ALL_ORDERS` in `SortSelect.tsx` with an icon, and to
   `RANKED_ORDERS` / `RANGE_AWARE` in `ranked.ts` as applicable.
3. Add the label to the `network` namespace in all three locales and to
   `locales/namespaces/types.ts` (a missing locale key is a build error).
4. Extend `SortOrder` in `ranked.ts`.

`node --experimental-strip-types src/shared/stream/filters/ranked.test.ts`
checks the invariants between those lists (every range-aware order is ranked;
no chronological order is).
