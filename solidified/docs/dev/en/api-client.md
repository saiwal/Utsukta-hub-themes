# API Client

## Overview

All API communication goes through one thin wrapper, `apiFetch`, in
`packages/spa-core/src/lib/fetch.ts`. It returns the raw `Response` — it does
not unwrap the JSON envelope — so call sites stay explicit about status
handling.

> An older `api.ts` exported `apiGet` / `moduleGet` / `modulePost` against
> `/spa/z/1.0/`. Those were removed; nothing in the tree uses them. If you find
> them referenced anywhere, that reference is stale.

Most component code should not call the network layer directly for GETs — reads
go through the TanStack Query cache instead; see
[data-fetching.md](data-fetching.md).

## apiFetch

```typescript
import { apiFetch, apiError } from "@utsukta/spa-core/lib/fetch";

const res = await apiFetch("/spa/network?start=0");
if (!res.ok) throw await apiError(res);
const { data } = await res.json();
```

It adds, on every request:

- `credentials: "include"`
- `Content-Type: application/json`

and on `POST` / `PUT` / `PATCH` / `DELETE`:

- `X-CSRF-Token`, fetched via `getCsrfToken()`

Any headers you pass in `init.headers` are merged in, and the CSRF header is
applied last so it can't be accidentally overwritten.

### Automatic CSRF retry

If a mutating request comes back `403` and the body is a CSRF error
(`error.code === "csrf_invalid"` or `error.message === "Invalid CSRF token"`),
`apiFetch` calls `resetCsrfToken()` and retries the request **once** with a
fresh token. This is the main reason to prefer it over a hand-rolled `fetch` —
a session that outlives the 30-minute token cache otherwise fails a save.

### Writing a mutation

```typescript
export async function renameThing(id: number, name: string) {
  const res = await apiFetch(`/spa/thing/${id}/edit`, {
    method: "POST",
    body: JSON.stringify({ name }),
  });
  if (!res.ok) throw await apiError(res);
  return (await res.json()).data;
}
```

## When to use raw `fetch` instead

`apiFetch` always sets a JSON content type, so two cases must bypass it:

- **Multipart uploads** — build a `FormData` and set `X-CSRF-Token` yourself
  (`Avatar.php`, `Photos.php` and the vCard/iCal import paths do this).
- **File downloads** — use a plain `<a href>`; the handler writes its own
  headers and `exit`s rather than returning an envelope (see `Cal.php`'s
  `?export=ical` and `Files.php`).

Both still work offline-safely: the fetch fallback in
`packages/spa-core/src/lib/offline-fallback.ts` wraps `window.fetch` globally,
beneath every call site.

## Error Handling

`apiError(res, label?)` builds an `Error` from the response, preferring the
server's `error.message` over the bare status code, and truncating it to 200
characters (server messages can carry a PHP stack trace).

```typescript
import { apiError, truncateError } from "@utsukta/spa-core/lib/fetch";
```

The house pattern for surfacing one:

```typescript
import { toast } from "@utsukta/spa-core/store/toast";

try {
  await renameThing(id, name);
} catch (err) {
  toast.error(err instanceof Error ? err.message : "Rename failed");
}
```

## CSRF Token Management

File: `packages/spa-core/src/lib/csrf.ts`

The token is fetched once from `/spa/csrf` and cached for 30 minutes.
`apiFetch` handles this for you; call it directly only in the raw-`fetch` cases
above.

```typescript
import { getCsrfToken, resetCsrfToken } from "@utsukta/spa-core/lib/csrf";

const token = await getCsrfToken();  // cached, or fetched
resetCsrfToken();                    // invalidate (e.g. after a 403)
```

Server side, `Auth::requireLocalJson()` validates the header against
`$_SESSION['solidified_csrf']`.

## API Response Envelope

All PHP API responses use a consistent envelope (see [php-api.md](php-api.md)):

```json
{ "data": <payload> }
{ "data": [...], "meta": { "offset": 0, "limit": 20, "has_more": true, ... } }
{ "error": { "status": 404, "message": "Not found" } }
```

Unwrap `data` in the module's `api.ts`, so views and widgets never see the
envelope.

## Dev Proxy

The Vite dev server proxies to `https://hz-ddev.ddev.site`, so module code can
use relative `/spa/` URLs in both dev and production.

Proxied outright: `/spa`, `/perfstats`, `/cloud`, `/photo`, `/attach`,
`/wall_upload`, `/wall_attach`, `/item`, `/acl`, `/follow`, `/subthread`,
`/sse_bs`, `/starred`, `/smilies`.

`/hq`, `/notify`, `/notifications` and `/cdav` are **also SPA routes**, so they
are proxied only for the app's own `fetch()`es — browser navigations
(`sec-fetch-mode: navigate`) fall through to the dev server, so a hard reload
still opens the SPA rather than the legacy theme.
