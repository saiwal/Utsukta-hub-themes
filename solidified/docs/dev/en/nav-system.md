# Navigation System

## Overview

The navigation list is computed dynamically from several sources:
- Pinned apps (user's personal pinned apps from `/spa/nav`)
- Featured apps (Hubzilla's installed featured apps)
- Channel tabs (tabs visible on a channel page)
- System apps (apps visible to non-owner visitors)
- Hardcoded entries: admin panel

The result depends on the **viewer role** and the current **subject nick** (channel being viewed).

## useNav

`packages/spa-core/src/lib/useNav.ts` — the main hook for computing the nav list.

```typescript
import { useNav } from "@utsukta/spa-core/lib/useNav";
import { useSubjectNick } from "@utsukta/spa-core/store/site-config";

const subjectNick = useSubjectNick();
const navItems = useNav(subjectNick);  // () => NavItemDef[]
```

### Role-Based Nav Logic

| Context | Nav shown |
|---------|-----------|
| `local` (visiting someone else's channel) | Channel tabs only |
| `anonymous` or `remote` (visiting a channel) | Channel tabs + system apps |
| `owner` (own channel / personal pages) | Pinned + featured (if installed) + admin (if admin) |
| Visitor not on a channel | System apps only |

### App Filtering

Only apps that have a registered SPA route are included. `isSpaApp()` checks the app's URL against the set of known SPA route roots (derived from `getRoutes()`). Apps that link outside the SPA are excluded from the nav.

Installed-app filtering: featured apps that are not in the viewer's installed set are omitted (the installed set comes from `/spa/nav`).

Deduplication: `dedupByHref()` removes items with identical `href` values.

## useNavActionItems

Returns contextual action items (settings, manage, login, logout, etc.) filtered by viewer role:

```typescript
import { useNavActionItems } from "@utsukta/spa-core/lib/useNav";

const actionItems = useNavActionItems(); // () => NavItemDef[]
```

Actions rendered, in order: `admin`, `settings`, `manage`, `navhome`, `logout`, `login`, `remote_login`, `register`.

Actions that have SPA routes (`/admin`, `/settings`, `/manage`) use in-app navigation; others use the URL from the API response to avoid a hard page reload.

## Viewer Role

`useViewerRole()` from `packages/spa-core/src/store/site-config.ts` returns the viewer's relationship to the current page:

```typescript
type ViewerRole = "owner" | "local" | "remote" | "anonymous";
```

The role is derived from:
1. Auth state (is the user logged in? are they local?)
2. Subject nick (is there a `:nick` in the URL, and does it match the logged-in user's nick?)

This is a pure computation — no extra API call.

## NavContext on Module navItem

Every module's `navItem` carries a `context` field that controls visibility:

```typescript
context: "owner"         // show only to the channel owner
context: "all"           // show to everyone
context: ["owner", "local"]  // multiple roles
```

`matchesRole(context, role)` in `useNav.ts` enforces this. The `admin` context is handled separately via `isAdmin()`.

## Channel Tabs vs Pinned Apps

- **Channel tabs** come from `/spa/nav?channel_nick=<nick>` — they are permission-gated by the server and represent the channel's public-facing feature tabs.
- **Pinned apps** come from `/spa/nav` (no nick) — they are the viewer's personal pinned shortcuts.

These two lists are never mixed for the same request. The nav switches between them based on the subject nick and viewer role.

## Mobile app drawer & bar

`src/shared/views/layout/MobileMenu.tsx` is the mobile nav shell (below `lg`):

- **Bottom bar**: account button (avatar/`Menu` of action items, first), favorites
  quick menu (a `Menu` of pinned apps, hidden when there are none), apps button
  (`MdFillGrid_view`, opens the drawer), the page-actions button (only while
  the active module has `PageActions`, see below) and the right-panel toggle.
  Every slot is `flex-1 min-w-0` so labels truncate instead of one button
  stretching and leaving a gap. It replaces the old four-tab "Most / More"
  split.
- **Favorites** are the user's Hubzilla pinned apps (`navData().pinned`) — no
  second favorites list. They are matched back to `NavItemDef`s by path, so the
  bar's quick menu and the drawer show the same resolved labels/icons.
- **`AppDrawer.tsx`**: centred brand (`BrandBlock`) → centred utility icon row
  (`NavUtilityIcons`) → app groups, with `SearchField` pinned in a footer at the
  bottom of the sheet, in thumb reach (the nav is a flex column; only the middle
  region scrolls). Apps render as a four-column launcher grid (48px icon tile,
  two-line label; the current app's tile is accent-tinted). Account actions
  live in the bar's account `Menu` only, so the drawer no longer duplicates
  them. Apps are grouped by `app-categories.ts`: the curated module-id table,
  then core's own app categories (`categories` on each `/spa/nav` app, from the
  `.apd`, mapped onto the three groups — how addons group themselves), then the
  path-root table, then `system`. Favorites are not repeated as their own section —
  they're one tap away in the bar's quick menu — but stay in their category.
  Social/Productivity/System are a `@ui/Collapsible` accordion (one open at a
  time, 48px headers with a count). Each open starts on the category holding
  the current app, if any. A non-empty search term replaces the groups with one
  flat results grid over `navItems()`, and clears when the
  drawer closes.

## Contextual page actions

A module header declares its common actions as data and each place renders
them its own way: buttons in the header on desktop, rows in the actions sheet
opened by the bar's bolt button on mobile. Tabs and inline search are
navigation, not actions — they stay in the header at every width.

```tsx
<PageActions actions={[
  { label: t("cards.new_card"), icon: BiRegularEdit, onClick: openCard, primary: true, show: isOwner() },
  { label: t("calendar.export_ical"), icon: MdOutlineFile_download, href, download, overflow: true },
]} />
```

- `PageAction` (`shared/views/layout/PageActions.tsx`): `label`, `icon` (a
  solid-icons component, rendered once per place), `onClick` or `href`
  (+`download`), `title`, `disabled`, `show` (false hides), `primary` (filled
  button on desktop, accent row on mobile) and `overflow` (behind a "More"
  `Menu` on desktop; an ordinary row on mobile). The array is reactive — a
  label or `show` can read signals.
- On `lg` and up it renders inline as a `flex` cluster (`class` for placement,
  e.g. `ml-auto`). Below `lg` it renders nothing and registers its actions;
  `pageActions()` flattens every mounted instance for the sheet, which renders
  them as `menuItemClass` rows and closes before running `onClick`.
- Anything that must outlive the sheet/menu closing (a hidden file input) or is
  anchored to its own button (a popover) stays in the header, outside
  `PageActions` — see calendar's import input and jellyfin's devices popover.
- `hasPageActions()` picks what the bar's actions button
  does: with page actions it shows the module name and the sheet lists them;
  without them, an owner sees **Compose** and the sheet lazy-loads
  `modules/hq/widgets/QuickComposeWidget` (the same quick-compose action list as
  HQ). Non-owners have no self-targeted compose actions, so the button stays
  hidden there. The sheet keeps its content `inert` while closed.
- `Layout.tsx` calls `setMobileNavAvailable(!hidesNavChrome())`: in chrome modes
  that hide the nav (`focus`, `compact`) there is no sheet, so `PageActions`
  stays inline instead of registering into nothing.
- Adding actions to a new module = one `<PageActions actions={…} />` in its
  header; no registry entry needed.
