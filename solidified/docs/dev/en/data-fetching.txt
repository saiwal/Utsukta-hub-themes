# Data Fetching & Caching

## Overview

Server data is fetched through **TanStack Solid Query** (`@tanstack/solid-query`). Every GET response is cached in memory under a **query key**; components reading the same key share one request and one cache entry. Revisiting a page renders instantly from cache while the data revalidates in the background ("stale-while-revalidate").

The raw fetch wrappers (`apiGet`, `moduleGet`, `modulePost` — see [api-client.txt](api-client.txt)) are still the network layer. TanStack Query wraps them; it does not replace them.

## Cache Configuration

File: `src/shared/lib/query-client.ts` — a single shared `QueryClient`, mounted via `QueryClientProvider` in `App.tsx`.

| Option | Value | Meaning |
|---|---|---|
| `staleTime` | 60 s | Data younger than this is served from cache with **no network request**. Older data still renders instantly but triggers a background refetch on next use. |
| `gcTime` | 30 min | Unused cache entries are garbage-collected after this. Returning later means a cold fetch. |
| `retry` | 2 | Failed queries retry twice with exponential backoff before erroring. |
| `refetchOnWindowFocus` | true | Stale data refetches when the tab regains focus. |
| `refetchOnReconnect` | true | Stale data refetches when the network comes back. |

The cache is **in-memory only** — a full page reload starts empty.

In dev builds a floating TanStack Query devtools panel shows every cached key, its fresh/stale state, and lets you force refetches.

## Reading Data: createQueryResource

File: `src/shared/lib/createQueryResource.ts`

A drop-in replacement for Solid's `createResource`, backed by the query cache. Same tuple shape, same reactive semantics, plus caching and request dedup.

```typescript
import { createQueryResource } from "@/shared/lib/createQueryResource";

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

`createQueryResource` needs component context (it calls `useQuery` internally). New code may also use `useQuery` from `@tanstack/solid-query` directly — pass options as a function: `useQuery(() => ({ queryKey: [...], queryFn: ... }))`.

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

`queryClient` is available via `useQueryClient()` inside components, or imported from `@/shared/lib/query-client` in store code that runs outside component context (e.g. `refetchNavData()` in `nav-store.ts`).

## Nav Data

`src/shared/store/nav-store.ts` caches `/spa/nav` under `["nav", nick]`. All nav hooks (`useNavData`, `useInstalledApps`, …) share it. After installing/uninstalling an app call `refetchNavData()` — it invalidates every nav variant.

## What NOT to Convert

Some code intentionally stays on plain `createResource`:

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
