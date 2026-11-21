# Data Fetching & Caching

## Overview

Server data is fetched through **TanStack Solid Query** (`@tanstack/solid-query`). Every GET response is cached in memory under a **query key**; components reading the same key share one request and one cache entry. Revisiting a page renders instantly from cache while the data revalidates in the background ("stale-while-revalidate").

`apiFetch` (see [api-client.md](api-client.md)) is still the network layer underneath. TanStack Query wraps it; it does not replace it.

## Cache Configuration

File: `packages/spa-core/src/lib/query-client.ts` — a single shared `QueryClient`, mounted via `QueryClientProvider` in `App.tsx`.

| Option | Value | Meaning |
|---|---|---|
| `staleTime` | 60 s | Data younger than this is served from cache with **no network request**. Older data still renders instantly but triggers a background refetch on next use. |
| `gcTime` | 5 min | Unused cache entries are garbage-collected after this — short for low-RAM devices; `offline-fallback.ts` (IndexedDB) still covers back-navigation. Returning later means a cold fetch. |
| `retry` | 2 | Failed queries retry twice with exponential backoff before erroring. |
| `refetchOnWindowFocus` | true | Stale data refetches when the tab regains focus. |
| `refetchOnReconnect` | true | Stale data refetches when the network comes back. |

The cache is **in-memory only** — a full page reload starts empty.

In dev builds a floating TanStack Query devtools panel shows every cached key, its fresh/stale state, and lets you force refetches.

## Reading Data: createQueryResource

File: `packages/spa-core/src/lib/createQueryResource.ts`

A drop-in replacement for Solid's `createResource`, backed by the query cache. Same tuple shape, same reactive semantics, plus caching and request dedup.

```typescript
import { createQueryResource } from "@utsukta/spa-core/lib/createQueryResource";

// Fetcher only — cached under ["pubsites"]
const [sites] = createQueryResource("pubsites", fetchPubsites);

// With a reactive source — cached under ["photo-albums", nick]
const [albums] = createQueryResource("photo-albums", () => props.nick, fetchAlbums);

// Object sources work too — key hash is content-based
const [files] = createQueryResource(
  "files-folder",
  () => ({ nick: nick(), hash: currentHash() }),
  ({ nick, hash }) => listFolder(nick, hash),
);
```

Semantics (mirroring `createResource`):

- Source of `null` / `undefined` / `false` → query disabled, no fetch.
- When the source changes, the previous data stays visible while the new data loads (`keepPreviousData`).
- Accessor exposes `.loading`, `.error` (typed `Error | undefined`), `.latest`.
- `.loading` is true for the initial load and source changes — **not** for background revalidation. No skeleton flash on refetch.
- Second tuple element: `{ refetch, mutate }`. `refetch()` forces a fetch; `mutate(valueOrUpdater)` patches the cache entry directly.
- Optional `{ initialValue }` maps to `placeholderData` — shown while loading, unlike TanStack's `initialData` it does not suppress the first fetch.

**Key naming:** kebab-case, unique per data shape. Reusing a name across call sites is deliberate cache sharing — only do it when the fetcher and result shape are identical (e.g. `files-folder` is shared by `FilesView` and the editor's `FilesPicker`).

`createQueryResource` needs component context (it calls `useQuery` internally). New code may also use `useQuery` directly — pass options as a function: `useQuery(() => ({ queryKey: [...], queryFn: ... }))` — but import it from `@utsukta/spa-core/lib/query`, **never** `@tanstack/solid-query`. In solid-query 6 `query.data` is a suspending async read; the `lib/query.ts` wrapper gives it the v5 behaviour this section describes (undefined until it lands, never suspends, errors stay on `.error`) by reading the cache keyed off the status fields. That matters beyond looks: Router 2 holds a navigation until every pending async read it touches settles, so a raw `data` read on a disabled or churning query freezes route changes.

A `queryFn` takes its inputs from `queryKey`, not from signals: it runs inside the query's effect, where a signal read is an untracked read (`STRICT_READ_UNTRACKED` in the dev runtime).

## Reading Data Natively: createAsyncResource

File: `packages/spa-core/src/lib/createAsyncResource.ts`

The Solid 2 way: the same call shape and cache keys as `createQueryResource`, but `data()` is an async read that suspends until the data lands. Render it inside `<AsyncBoundary>` (`src/shared/views/AsyncBoundary.tsx`, an `<Errored>` + `<Loading>` pair):

```tsx
const [albums, { refetch, pending, peek }] =
  createAsyncResource("photo-albums", () => props.nick, fetchAlbums);

<AsyncBoundary fallback={<Skeleton />}>
  <div classList={{ "opacity-60": pending() }}>
    <For each={albums()}>{(a) => <AlbumCard album={a} />}</For>
  </div>
</AsyncBoundary>
```

- The first load shows `fallback`. A later source change or refetch keeps the old content on screen; `pending()` is true meanwhile, for dimming.
- An error shows an `ErrorState` with a retry. `error` replaces it (JSX, or `(err) => JSX` — a widget's own "unavailable" line); `quiet` hides the boundary on error.
- A `null`/`undefined`/`false` source means no data: `data()` is `undefined` and never suspends. (A *disabled* native query would stay pending forever, hiding its boundary and holding navigations.)
- `refetch()` invalidates the key and resolves once the new data has landed. `peek()` is a non-suspending cache read, for a count in a widget header or footer that sits outside the boundary.
- The raw hook is `useAsyncQuery` (`lib/query.ts`); route views using it pin their params — `createMemo((prev) => params.x || prev)` — so the leaving view's key doesn't change mid-navigation.

**When not to use it:** data read in effects, event handlers or form seeding (a read throws while pending), and data passed as a prop that a child reads at setup (`PENDING_ASYNC_UNTRACKED_READ`). Those stay on `createQueryResource`.

### Optimistic writes

Native reads pair with Solid 2 actions. `createOptimistic` (or `createOptimisticStore` for lists edited in place) derives a view from the query; an `action` writes to the view at once, awaits the request, and ends by refetching. If the request throws, the view drops back to the confirmed data. `TasksWidget.tsx` is the reference:

```typescript
const [view, setView] = createOptimistic((): Task[] => tasks() ?? []);

const run = action(function* (show: (list: Task[]) => Task[], fn: () => Promise<unknown>) {
  setView(show);                       // shown at once
  try { yield fn(); }                  // the request
  catch { toast.error(...); return; }  // rolled back
  yield refetch();                     // confirmed
});
```

Writes are batched in Solid 2: inside an action, build the new state yourself instead of reading derived UI back right after a write. Other references: `DraftsWidgetBase.tsx` (delete), `CardView.tsx` (reactions, comments), `KanbanBoard.tsx` (drag moves).

## Writing Data: Mutations

Writes use `useMutation`; on success they either **invalidate** the affected keys (refetch) or **patch** the cache in place.

Reference implementations:

- `src/modules/settings/store/useSectionForm.ts` — the query + mutation pair every settings section uses. Save → `invalidateQueries(["settings", section])` → form re-renders with server truth.
- `src/modules/settings/views/sections/IntegrationsSection.tsx` — one mutation invalidating **two** caches (`["settings", "integrations"]` and `["nav"]`, via `refetchNavData()`), per-row busy state derived from `mutation.isPending` + `mutation.variables`.
- `src/modules/settings/views/sections/FeaturesSection.tsx` — `queryClient.setQueryData` to flip one feature's flag without refetching the whole list.

```typescript
const save = useMutation(() => ({
  mutationFn: (payload: Partial<T>) => saver(payload),
  onSuccess: () => {
    toast.success("Saved");
    queryClient.invalidateQueries({ queryKey: ["settings", section] });
  },
  onError: (err: Error) => toast.error(err.message),
}));
```

`queryClient` is available via `useQueryClient()` inside components, or imported from `@utsukta/spa-core/lib/query-client` in store code that runs outside component context (e.g. `refetchNavData()` in `nav-store.ts`).

## Nav Data

`packages/spa-core/src/store/nav-store.ts` caches `/spa/nav` under `["nav", nick]`. All nav hooks (`useNavData`, `useInstalledApps`, …) share it. After installing/uninstalling an app call `refetchNavData()` — it invalidates every nav variant.

## What NOT to Convert

Some code intentionally stays on `createResource` — Solid 2 dropped it, so these import the signal-based port in `packages/spa-core/src/lib/resource.ts` (`.loading`/`.error`/`.latest`, `refetch`, `mutate`; never suspends):

| Code | Why |
|---|---|
| Module-level stores: `auth-store`, `manage/store`, `directory/connections/store`, `i18n`, `useMention` | Run at import time — no component context for `useQuery`. |
| Editor-seed fetchers: `ProfileEditView`, `WebpageEditorView`, `ConnectionEditorModal` (`permsData`) | Their fetchers seed editors or set signals as a side effect. A cache hit skips the fetcher → stale content in an editor or unseeded state. |
| `NotificationsAside` | Live panel with its own tick-driven refresh machinery. |
| `createStreamStore` | Stream logic (thread trees, new-post buffering, offset pagination) is domain state, not response caching. |

## Rules of Thumb

1. **Form-seed queries must set `refetchOnWindowFocus: false`.** Settings forms are uncontrolled inputs seeded from fetched data; a focus refetch mid-edit silently overwrites the user's unsaved input. `useSectionForm` already does this.
2. **Never cache data whose fetcher has side effects.** Cache hits skip the fetcher.
3. **After a write, invalidate — don't manually refetch from the component.** The mutation declares which keys are now stale; every subscriber updates.
4. **Keys are contracts.** Renaming a key orphans nothing permanently (gcTime cleans up) but breaks intentional cache sharing — grep before renaming.
