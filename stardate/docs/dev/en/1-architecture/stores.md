# Reactive Stores

State is managed with **Solid's reactive primitives** (signals, memos, resources). There is no centralised store — state is scoped to where it is needed. The shared stores cover cross-cutting concerns: authentication, navigation data, and page context.

## Auth Store (`packages/spa-core/src/store/auth-store.ts`)

Fetches viewer identity once at startup from `/spa/pconfig`.

```typescript
type AuthState = {
  isLocal: boolean;      // native logged-in user
  isLoggedIn: boolean;   // any authenticated user (local or remote)
  isAdmin: boolean;
  nick: string;          // channel nick, "" if anonymous
  uid: number;           // local channel id, 0 for visitors
  pageSize: number;      // preferred items per page
  updateInterval: number; // polling interval (seconds)
};
```

**Usage:**
```typescript
import { useAuth, isAdmin } from "@utsukta/spa-core/store/auth-store";

const auth = useAuth();     // () => AuthState | undefined
const admin = isAdmin();    // boolean
```

On load the store also reads `spa` preferences from the pconfig response and applies typography, background, and theme settings immediately.

## Nav Store (`packages/spa-core/src/store/nav-store.ts`)

Fetches `/spa/nav` (optionally with `?channel_nick=<nick>`) whenever the subject channel changes. Returns viewer identity, pinned/featured apps, channel tabs, installed app names, and system apps.

The nick signal `navNick` is initialised from the URL at startup and updated by `Layout.tsx` on every navigation.

**Usage:**
```typescript
import {
  useNavData,
  usePinnedApps,
  useFeaturedApps,
  useSystemApps,
  useInstalledApps,
  useChannelNav,
} from "@utsukta/spa-core/store/nav-store";

const navData = useNavData();       // Resource<NavApiResponse>
const pinned  = usePinnedApps();    // () => NavApp[]
const installed = useInstalledApps(); // () => Set<string>
```

`useChannelNav(subjectNick)` returns a resource that includes `channel_tabs` — only populated when a nick is provided.

## Site Config Store (`packages/spa-core/src/store/site-config.ts`)

Derives page context from the current URL and auth state — no API call needed.

### useSubjectNick

```typescript
import { useSubjectNick } from "@utsukta/spa-core/store/site-config";
const nick = useSubjectNick(); // () => string — "" on pages without a nick
```

Returns the `:nick` URL segment for routes like `/channel/:nick`, `/photos/:nick`, etc.

### useViewerRole

```typescript
import { useViewerRole } from "@utsukta/spa-core/store/site-config";
const role = useViewerRole(); // () => ViewerRole
```

Derives the viewer's relationship to the current page subject:

| Role | Condition |
|------|-----------|
| `"anonymous"` | Not logged in |
| `"remote"` | Authenticated but not a local user |
| `"local"` | Local user viewing someone else's channel |
| `"owner"` | Local user on their own channel, or on a non-channel page |

### usePageNick

```typescript
import { usePageNick } from "@utsukta/spa-core/store/site-config";
const nick = usePageNick(); // () => string
```

Returns the nick to use for API calls:
- On `/channel/:nick` → the URL nick (works for both owner and visitors)
- On `/hq` or other owner-only pages → the logged-in user's nick
- Anonymous on a non-channel page → `""`

## Module-Level Stores

Each module typically has its own `store/store.ts` using `createResource` for data fetching:

```typescript
// Example pattern — module-level stores stay on createResource because they
// have no component context. Inside components, prefer createQueryResource.
const [posts, { refetch }] = createResource(
  () => `/spa/network?start=${offset()}`,
  async (url) => {
    const res = await apiFetch(url);
    if (!res.ok) throw await apiError(res);
    return (await res.json()).data;
  },
);
```

Keep module stores inside the module folder. Do not share mutable state across modules via global signals unless it truly belongs in the shared layer.
