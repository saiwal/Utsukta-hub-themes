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

## Desktop rail groups

`DesktopMenu.tsx`, in your own context (`viewerRole() === "owner"`): favorites
(pinned apps, `pinnedNavItems()`) always shown on top, then every other nav item
in `@ui/Collapsible` categories from `groupNavItems()` — the same
`app-categories.ts` grouping the mobile drawer uses. Groups open independently;
the open set is a per-browser convenience in localStorage `hz-nav-groups`.
Navigating into an app opens its group; a closed group shows a dot when one of
its items has a badge. Visiting a channel the rail stays the flat tab list (a
channel's sections aren't apps to categorise). In edit-layout mode the groups
stay and the favorites get drag handles — `nav_order` only sorts pinned apps
server-side anyway (`commitNavOrder` with the favorites as the subset).

## Desktop rail header & footer

The rail opens with the site brand block — logo, a short vertical rule and the
site name (`BrandBlock`) — centred in its own band with a rule beneath it.
Keeping the wordmark at the top leaves the bottom free for the utilities, and
the mobile drawer uses the same lockup.

The cover header is 3.4:1 with a 48px avatar. The avatar's left edge, the name
and every nav-item icon sit on one 18px edge — the aside's `px-2` plus a nav
item's `px-2.5`. A short `from-surface` scrim at the bottom of the cover fades
a busy image into the rail instead of ending in a hard seam, and the avatar's
ring follows the identity row's tone (`surface`, or `elevated` while hovered or
open). The visited channel's card — the static one for anyone but its owner —
carries a "Visiting" pill; without it the two covers differed only by the
chevron. That card's name links to `/channel/<nick>`, so the identity doubles
as the way back to the channel's own page from a subpage.

Below the nav (and the `leftBottom` slot) there is exactly one footer block
with one rule. On someone else's channel a logged-in viewer gets one identity
row showing their own avatar and name — the cover above is the channel
owner's, so this is the only place the rail names the viewer. The name links to
their own channel; the chevron beside it opens the account menu upward with the
same `ActionList` the owner's cover opens downward.
Anonymous and remote-auth visitors keep their short inline Sign In / Register
list.

The rest of the footer is just the utility strip: inset 4px so its first and
last glyphs land on the same 18px edge as the masthead, cover identity and nav
icons, with tightened controls (`[&_button]:p-1.5`) so all five fit without
spilling over the rail.

The nav's scroll region uses `nav-rail-scroll` (`src/index.css`) for the
scrollbar, which only appears on `:hover` / `:focus-within`. Its edge fade is
an inline mask set by `DesktopMenu`: a 12px fade appears on the top and/or
bottom edge only when that edge has hidden rows (measured on scroll, resize and
after the nav's contents change), so an overflow no longer slices a row in half
and a short list isn't dimmed for no reason.

## Favorites (pinning)

A favorite is Hubzilla's `nav_pinned_app`; "in the nav" is pinned *or*
`nav_featured_app`. `FavoriteStar` (`shared/views/FavoriteStar.tsx`) toggles it
from three places: Settings → Integrations (before the app icon, keyed by the
canonical app `name`), the desktop rail (hover/focus star) and the mobile drawer
(corner star on each tile) — the last two key by the app's raw `url`, because
`/spa/nav` names are translated. All post `action: "pin"` to
`/spa/settings/integrations`:

| action | enabled | result |
|---|---|---|
| `pin` | on | adds pinned |
| `pin` | off | removes pinned, adds featured — un-favoriting never hides the app |
| `nav` | on | adds featured if neither is set (in the nav, not a favorite) |
| `nav` | off | removes both — the app leaves the nav |

SPA-only modules (messenger, inbox…) have no app, so their star pins the nav
*path* instead: `pin` with `path` toggles it in pconfig `spa`/`pinned_paths`,
returned as `navData().pinned_paths` (owner only). `favoriteTarget()` in
`app-categories.ts` picks app url vs path; `pinnedNavItems()` merges both.

## Mobile app drawer & bar

`src/shared/views/layout/MobileMenu.tsx` is the mobile nav shell (below `lg`):

- **Bottom bar**: always exactly four slots — account (avatar/`Menu` of action
  items), favorites (opens the favorites sheet; an empty-state hint when
  nothing is pinned), apps (`MdFillGrid_view`, opens the drawer) and the
  right-panel toggle (disabled, not removed, where `hidesRightSidebar()`).
  The count never changes, so the bar doesn't reflow on navigation. Page
  actions are not in the bar — see "Contextual page actions". Every slot is
  `flex-1 min-w-0` so labels truncate instead of one button stretching.
- **Favorites** are the user's Hubzilla pinned apps (`navData().pinned`) plus
  pinned SPA-only paths (`navData().pinned_paths`). They are matched back to `NavItemDef`s by path, so the
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

A view declares its common actions as data and each place renders them its
own way. Tabs are navigation, not actions — they stay in the header.

```tsx
<PageActions actions={[
  { label: t("cards.new_card"), icon: BiRegularEdit, onClick: openCard, primary: true, show: isOwner() },
  { label: t("calendar.export_ical"), icon: MdOutlineFile_download, href, download, overflow: true },
]} />
```

- `PageAction` (`shared/views/layout/PageActions.tsx`): `label`, `icon` (a
  solid-icons component, rendered once per place), `onClick` or `href`
  (+`download`), `title`, `disabled`, `show` (false hides), `primary` and
  `overflow` (behind a "More" `Menu` on desktop). The array is reactive — a
  label or `show` can read signals.
- **Desktop** (`lg`+, or any width while the nav chrome is hidden): renders
  inline where it sits as a `flex` cluster (`class` for placement), primary
  filled.
- **Mobile** (`useMobileActions()`: below `lg` with the nav bar): every mounted
  instance registers its actions and renders nothing inline.
  - **Floating column** (`FloatingActions.tsx`, mounted in `Layout.tsx`): a
    "more" button (`MdFillMore_vert`), always, plus
    scroll-to-top once the page is scrolled. "More" fans out a column of labelled
    round buttons above it, over a scrim (tap outside / Escape / picking one
    closes it): the main action first, then 🔍 search, then every other
    action — a column even for a single entry (e.g. just Compose). The
    main action is `mobilePrimary()`: the innermost (last mounted) visible
    `primary`, so a section's beats its module header's. With none, the owner
    in a **social** module (`moduleCategory()`, `app-categories.ts`) gets
    **Compose**, which swaps the column for the create actions (`useQuickActions()`, HQ's
    quick-compose list; `ComposeDialItems.tsx`, lazy-loaded). The
    column hides while a text field has focus (the keyboard is up).
  - The other actions come from `pageMenuGroups()`: every mounted instance's,
    in mount order (module header, then section/item view), flattened. `label`
    names a group (inside `SubPageContent` it defaults to the section title,
    `PageActionsLabel` context) — the column doesn't show group headings.
- **`mobileOnly`**: registers, never renders inline. For a view that
  keeps its own inline controls on desktop (icon toolbars in files/photos,
  the inbox sidebar's compose buttons): it hides them while
  `useMobileActions()` and hands the same actions over. A file input the
  action clicks must stay mounted outside the hidden markup. A two-step
  inline delete confirm becomes a native `confirm()`.
- **Search**: `usePageSearch(() => ({ value, placeholder, onSearch, onClear }))`
  offers a view's search as the floating 🔍 (mobile only; innermost wins),
  which opens a field docked above the bar. The view hides its inline search
  toggle while `useMobileActions()`. Registered by articles, cards, notepad,
  jellyfin, the channel feed, pubstream (tag) and network (its filters panel
  is a drawer on mobile; a pasted post URL imports, as in the panel).
- **Form Save** (`SaveBar`, `settings/store/FormHelpers.tsx`, rendered inside
  the form) renders nothing visible: on mobile it is a `primary` action (the
  floating button), elsewhere `useFloatingAction()` puts it in the same
  floating slot. Either way it calls `form.requestSubmit()`.
- `Layout.tsx` calls `setMobileNavAvailable(!hidesNavChrome())`: chrome modes
  that hide the nav (`focus`, `compact`) keep the desktop rendering.
- All registries write in `onMount` (`useRegistration`): the router renders
  the next page inside a transition, and a write there could resurrect the
  outgoing page's entries.
- Adding actions to a new module = one `<PageActions actions={…} />` in its
  header; no registry entry needed.
