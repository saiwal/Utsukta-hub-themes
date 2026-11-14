# Importing federated posts

How pasting a remote post URL into the search box pulls that post — and its
discussion — into the local database, and why each protocol needs different
handling to get there.

## The pieces

| Layer | File |
|---|---|
| Import endpoint | `packages/spa-core/php/Api/Handlers/Search.php` |
| Reply discovery + storage | `packages/spa-core/php/Api/Concerns/FetchesRemoteReplies.php` |
| "Fetch more replies" endpoint | `Handlers/Item.php` → `fetchMoreReplies()` |
| Search input + URL detection | `src/modules/network/widgets/StreamFiltersWidget.tsx` |
| Result display + button | `src/shared/views/PostDetailModal.tsx` |
| Imported-thread flag | `iconfig` cat `spa` key `imported`, set in `Search.php`, read in `Display.php` |
| API helper | `packages/spa-core/src/lib/item-api.ts` → `apiFetchRemoteReplies` |

Core mirrors: `Zotlabs/Module/Search.php` (the URL branch),
`addon/pubcrawl/pubcrawl.php::pubcrawl_fetch_provider()`, and
`Zotlabs/Daemon/Convo.php`.

## The flow

It is deliberately two hops, matching core:

1. `GET /spa/search?url=<permalink>` fetches the post from the remote server,
   stores it locally, and returns `{ uuid }`.
2. The frontend opens `PostDetailModal`, which reads it back with
   `GET /spa/display/{uuid}`.

`Display.php` is a pure local DB read with no remote fallback — that is *why*
step 1 has to store before step 2 can render. It also means step 1 returning a
uuid for a row that was never written produces a 404 in the modal, which is the
failure mode most of this code exists to prevent.

The search input treats a value starting with `https://` as a URL: no debounced
stream search, import on Enter only, and the magnifier turns green
(`isUrl()` in `StreamFiltersWidget.tsx`). This matches core's own
`str_starts_with($search, 'https://')` check.

## Two protocols

`Search.php` tries Zot first, then ActivityPub.

### Zot (Hubzilla → Hubzilla)

`Libzot::fetch_conversation()` issues a signed Zotfinger POST and gets back an
`OrderedCollection` containing the **whole thread**. Root, comments, reactions —
all of it, in one request. Nothing extra to do.

### ActivityPub (Mastodon, Lemmy, …)

Gated on the `pubcrawl` addon app being installed for the channel:

```php
if (!Apps::addon_app_installed($channel['channel_id'], 'pubcrawl')) { … }
```

This gate is not bureaucracy. `Activity::fetch()` builds its Accept header from
`ActivityStreams::get_accept_header_string($channel)`, and pubcrawl's
`get_accept_header_string` hook is what adds `application/activity+json`.
Without the app, Mastodon serves HTML, `json_decode` yields null, and the import
fails with no clue why — hence the dedicated error message.

AP gives you one object. The rest of the thread has to be assembled in two
directions.

## Upward: ancestors

`Activity::store()` **stores nothing** when the object is a child node whose
parent isn't local yet (`Zotlabs/Lib/Activity.php`, the `$is_child_node` branch).
Instead it registers the mid in `App::$cache['as_fetch_objects']` for the
background `Fetchparents` daemon and returns. Most real permalinks hit this —
every reply, and every `Announce`.

Meanwhile `decode_note()` always sets `$s['uuid']`. So a handler that trusts
`$item['uuid']` after `store()` hands back a uuid for a row that does not exist.

The fix mirrors `pubcrawl_fetch_provider()`:

```php
Activity::store($channel, get_observer_hash(), $AS, $item, true, true);

if (isset(App::$cache['as_fetch_objects'])) {
    Activity::fetch_and_store_parents($channel, get_observer_hash(), $item, $AS, true);
    unset(App::$cache['as_fetch_objects']);
}
Activity::init_background_fetch(get_observer_hash());
```

`fetch_and_store_parents()` walks `parent_mid` to the thread root and stores the
chain **including the originally requested item**, so it is what actually makes
the reply land.

### Never trust the reported uuid

Both paths report success while storing nothing:

- `fetch_conversation()` returns DReport rows (`Zotlabs/Lib/DReport.php`) that
  carry a `message_uuid` even when `status` is `permission denied`,
  `post ignored` or `storage failed`.
- `Activity::store()` can no-op as described above.

So `Search.php` reads the row back out of `item` before responding
(`storedRow()`). That is the only honest success signal, and it settles every
rejection case at once without a `status` allowlist to keep in sync with core.

## Downward: replies

`Activity::store()` only ever *queues* an object's replies collection
(`App::$cache['as_fetch_collection']`) for the background `Convo` daemon. That
works — the daemon does run, and being untimed it reaches replies on servers our
inline pass never gets to — but it lands rows seconds to minutes later, into a
modal that already rendered without them.

So the import also walks replies **inline**, bounded, before responding.
`FetchesRemoteReplies::fetchRemoteReplies()` is `Convo::run()`'s loop minus the
daemon shell: same `ASCollection` → `decode_note` →
`Activity::store(fetch_parents: false, force: true)` sequence, minus the
half-second-per-item throttle, plus bounds.

It walks the replies of the **thread root**, not the requested item — for a reply
permalink the interesting collection hangs off the root, and for a root URL the
two are the same object, so one code path covers both.

### Discovery is per-platform

The protocol does not settle where replies live, so `replyCandidates()` handles
each shape:

| Server | Where replies are |
|---|---|
| Mastodon, Pleroma, … | the object's `replies` collection, walked with `ASCollection` |
| Lemmy | **nowhere in the AP object** — its `Page` has no `replies` key at all |

Lemmy's comments are only reachable through its own API, so there is a fallback:

```
GET {host}/api/v3/comment/list?type_=All&sort=Old&max_depth=8&post_id={id}
```

Each result's `ap_id` then goes through the same fetch/decode/store path as any
other reply. `sort=Old` is load-bearing: the walk stores with `fetch_parents`
off, so `Activity::store()` drops a comment whose parent isn't in yet, and
oldest-first guarantees every parent precedes its children.

Before fetching, `rejectKnownMids()` drops candidates already in the DB. Each
surviving candidate is a remote round-trip, so this is what makes a repeat press
cheap *and* progressive instead of re-paying for replies already held.

### Bounds: time, not just count

A count cap does not bound wall clock, because each uncached reply is its own
signed round-trip (~1s+ against a busy host). A 15-reply thread measured 20
seconds. So `fetchRemoteReplies()` takes a **deadline** as well:

| Config (`Config::Get('spa', …)`) | Default | Meaning |
|---|---|---|
| `import_replies_limit` | 25 | max replies considered; **`0` disables the inline walk** |
| `import_replies_budget` | 6 | seconds of wall clock; `0` = no time bound (count only) |

Note the asymmetry: `limit = 0` is the off switch, `budget = 0` means
*unbounded*. The deadline is checked at the top of each iteration, so worst case
is budget + one in-flight fetch.

Whatever the budget doesn't reach stays queued for the `Convo` daemon and is
reachable via the button below.

> Import latency is dominated by per-author xchan and avatar fetches, not by the
> replies walk — a run with the walk fully disabled was measured at 79s. The
> budget bounds our part; it can't make imports uniformly fast.

## Fetch more replies

`POST /spa/item/:mid/fetchreplies` → `Item::fetchMoreReplies()`.

Semantics: **descend one more level.** It collects the thread's frontier —
every item with no local children, **plus the thread root unconditionally** —
and walks each one's replies.

The root is always included because on Lemmy it is the only object that can name
the thread's comments; drop it once it has children and a second press finds
nothing.

Bounds are `REPLY_LEAVES_PER_CALL` (10), `REPLIES_PER_LEAF` (25) and
`REPLY_TIME_BUDGET` (15s).

The response reports the **row delta**, not the number of `store()` calls —
`Activity::store()` drops duplicates and permission-denied activities silently,
so counting attempts would make the toast overstate what the viewer can see.

Frontend: `apiFetchRemoteReplies()` in `item-api.ts`, wired to a button in
`PostDetailModal`'s existing `contextBanner` slot. On success it toasts the count
and calls the existing `refetch()`.

### When the button shows

Only on threads **this hub pulled in by URL** — `imported && authorNetwork ===
"activitypub"`.

The distinction that matters is fetched vs. delivered, not remote vs. local. A
connection's AP post is delivered with its comments and keeps receiving new ones,
so offering to fetch more is pure noise; an imported thread is a snapshot that
genuinely may be missing replies. Gating on `authorNetwork` alone put the button
on every AP post in the stream.

`item_fetched` cannot serve as the marker — `Activity::store()` consumes it and it
is never persisted (there is no such column). So `Search.php` records the fact
itself on the thread root:

```php
IConfig::Set($rootId, 'spa', 'imported', 1);   // markImported()
```

`Display.php` reads it back onto the root payload as `imported`. It is read there
rather than in `FormatsItems::formatItem()` on purpose: display handles one item,
while a per-row iconfig lookup would add a query per post in every stream.

Threads imported before this flag existed read `false` and simply don't offer the
button until re-imported.

Measured on a Lemmy post with 29 upstream comments: import brought root + 4
within the 6s budget, then presses took the thread 5 → 14 → 26.

## Failure messages

Both fetch paths collapse every failure into `null`, which made a deleted post
and a rejected request read identically. `explainFetchFailure()` does one
unsigned probe **on the already-failed path** and distinguishes them:

| Probe result | Message |
|---|---|
| 401 / 403 | remote refused; likely authorized fetch, check this hub is publicly reachable |
| 404 / 410 | the post no longer exists on the remote server |
| anything else | the generic not-found / unsupported-protocol message |

The 401 case is common in **local development**. A server running authorized
fetch (secure mode) verifies our HTTP signature by fetching our actor back at
`channel_url($channel)`. From a ddev host that is
`https://hz-ddev.ddev.site/channel/<nick>`, which the remote cannot resolve:

```
401 {"error":"Requests to private network addresses are disallowed
     (tried to query Mastodon::PrivateNetworkAddressError
      on https://hz-ddev.ddev.site/channel/admin)"}
```

That is an environment limitation, not a code defect — the same URL imports
fine from a publicly reachable hub.

## Testing

There is no browser-free route to these handlers, so drive them with a
throwaway harness under `core/`: `cli_startup()`, `session_start()`, set
`$_SESSION['uid']` / `authenticated`, `App::set_channel()` /
`set_observer()`, then invoke the handler. For POST verbs set
`$_SESSION['solidified_csrf']` and `$_SERVER['HTTP_X_CSRF_TOKEN']` to the same
value, and reach private methods by reflection.

Cases worth keeping honest:

1. **Mastodon reply permalink** — ancestors land, and `/spa/display/{uuid}`
   renders. The regression this whole area exists to prevent.
2. **Mastodon root with replies** — root plus direct replies.
3. **Lemmy post** — comments arrive despite no `replies` collection; press
   "fetch more" twice and confirm the count climbs and never double-counts.
4. **A delivered connection's AP post** — `/spa/display` reports
   `imported: false`, so no button. Only a freshly imported thread reports
   `true`.
5. **Zot permalink**, plain and `b64.`-encoded — whole conversation, AP path
   never entered.
6. **`import_replies_limit = 0`** — import succeeds carrying no replies; the
   `Convo` daemon fills them in afterwards (visible as rows whose `received`
   timestamps trail the request by seconds).
7. **pubcrawl uninstalled** — the explanatory error, not a bare not-found.
8. **Deleted / authorized-fetch URLs** — the two distinct failure messages.

Live public timelines are the easiest source of fixtures:
`https://mstdn.social/api/v1/timelines/public?limit=40&local=true` for fresh
posts, or an account's `statuses` endpoint filtered on `replies_count > 0` for
threads that actually have a discussion.

## Known ceilings

- **Mastodon's `replies` collection lists only replies its own instance knows
  about**, so a thread fanned across servers imports partially. Core has the
  same limit; there is no client-side fix.
- **Lemmy discovery costs one cheap API call but each comment is still an
  individual fetch**, so a busy thread needs two or three presses. Mapping
  Lemmy's JSON straight to items would be faster at the cost of a second
  content-mapping path to maintain.
- **`Convo::run()` never calls `init_background_fetch()`**, so the nested replies
  it re-queues are dropped — the daemon goes exactly one level deep. That is core
  behaviour, left alone; the button is how depth is reached here.
- **No per-comment fetch button.** The frontier walk is thread-level and blunt;
  a per-comment affordance is the upgrade if that proves annoying.
