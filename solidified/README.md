# Solidified — Hubzilla SPA Theme

A [Solid.js](https://www.solidjs.com/) single-page application (SPA) that ships as the **Solidified** theme for [Hubzilla](https://framagit.org/hubzilla/core), a federated social networking platform. It replaces the classic server-rendered UI with a fast, reactive client-side experience while staying fully integrated with Hubzilla's PHP backend.

> The compiled theme is distributed at **[saiwal/Utsukta-hub-themes](https://github.com/saiwal/Utsukta-hub-themes)** on GitHub.

---

## Features at a Glance

- **35 modules** — channel, network, articles, photos, files, chat, calendar, wiki, webpages, directory, admin, games, and more
- **Rich text editor** — BBCode with WYSIWYG, Source, and Preview tabs, plus LaTeX rendering
- **Threaded feed** — like, dislike, repeat, comment, reshare, star, and delete with live state
- **24 UI themes** — presets (Nord, Dracula, Catppuccin, Tokyo Night…) plus fully customizable
- **Cached data layer** — TanStack Query: instant back-navigation, request dedup, background revalidation
- **Progressive Web App** — service worker caching, push notifications, background update detection
- **Responsive layout** — desktop three-column, tablet collapsible sidebar, mobile bottom tab bar
- **Customizable Widget layouts** — add, remove, and reorder sidebar widgets, configure widgets that support multiple instances, a few widgets are pinned on every page
- **i18n** — locale switcher with localStorage persistence, three locales ship out of the box
- **Pluggable module system** — every feature self-registers routes, nav items, and sidebar slots
- **In-browser image editor** — crop, filters, annotations, and adjustments before upload (Filerobot)
- **Native video/audio player** — Plyr-based player with YouTube/Vimeo support for embedded media
- **End-to-end encrypted DMs** — libsodium (XSalsa20-Poly1305), interoperable with Hubzilla's native crypto
- **40 built-in puzzle games** — the Simon Tatham puzzle collection (Mines, Loopy, Net, Solo, and more), playable at `/games`
- **Photo lightbox** — PhotoSwipe-based gallery viewer for photo albums and inline attachments

---

## Modules

### Core social

| Module | Routes | What it does |
|---|---|---|
| **Channel** | `/channel/:nick` | A user's public wall with posts, categories, and tags |
| **Network** | `/network` | The authenticated user's personal federated home feed |
| **HQ** | `/hq` | Personal dashboard with notifications and quick actions |
| **Public Stream** | `/pubstream` | Public posts from all federated channels on this server |
| **Post** | `/display/:uuid`, `/item/:uuid` | Permalink view of a single post and its full comment thread |
| **Profile** | `/profile/:nick` | A channel's full profile page (bio/details), linking back to its wall |
| **Profiles** | `/settings/profile/:id` | Manage multiple profiles per channel for privacy-scoped visibility |
| **Bookmarks** | `/bookmarks` | Saved posts and links list |
| **Notify** | `/notify`, `/notifications` | Full notification list with per-item read/redirect handling |

### Content apps

| Module | Routes | What it does |
|---|---|---|
| **Articles** | `/articles/:nick/:uuid` | Long-form posts with title, summary, categories, and tags |
| **Photos** | `/photos/:nick/album/:id` | Photo albums with a PhotoSwipe-powered image viewer |
| **Files / Cloud** | `/cloud/:nick/*` | Cloud file browser and storage |
| **Webpages** | `/page/:nick/*` | Static pages with custom slugs, plus a page builder, menus, and layout templates |
| **Wiki** | `/wiki/:nick/:wiki/:page` | Hierarchical per-channel wikis |
| **Chat** | `/chat/:nick/:roomId` | Real-time chatrooms |
| **Calendar** | `/cal/:nick` | Channel calendar with CalDAV support |
| **Cart** | `/cart/:nick` | Shopping cart integration |
| **Notepad** | `/notepad` | Private, server-synced scratchpad |
| **Games** | `/games/:id` | 40 built-in puzzle games (the Simon Tatham collection: Mines, Loopy, Net, Solo, Towers, Undead, and more) |

### Discovery & connections

| Module | Routes | What it does |
|---|---|---|
| **Directory** | `/directory/*` | Connections, contact roles, privacy groups, people search, hub browser |
| **Siteinfo** | `/siteinfo` | Public site statistics and metadata |
| **Chanview** | `/chanview` | Read-only remote-channel viewer, used when browsing a channel on another hub |

### User & admin

| Module | Routes | What it does |
|---|---|---|
| **Settings** | `/settings/*` | Sections: display, profile, account, privacy, locations, notifications, integrations, features, blocked, portability, danger |
| **Admin** | `/admin/*` | 13 admin subpages: summary, site, accounts, channels, security, features, addons, themes, inspect-queue, queueworker, profile-fields, db-updates, logs |
| **Manage** | `/manage` | Multi-channel identity management |
| **Tools** | `/tools` | Developer and utility tools |
| **Help** | `/help/*` | Integrated documentation with wildcard routing |
| **Blocks** | *(widgets only, no routes)* | Reusable content-block widgets — RSS, HTML block, clock, weather, quote-of-the-day, embeds, menus, link lists, Pomodoro timer |

### Auth & onboarding

| Module | Routes | What it does |
|---|---|---|
| **Login** | `/login` | Authentication interface |
| **Logout** | `/logout` | Session termination |
| **Register** | `/register`, `/regate/:token` | New account registration and invite-token gated signup |
| **Channel Create** | `/new_channel` | First-channel creation flow for a freshly registered account |
| **Channel Import** | `/import` | Import a channel identity from a file or another hub |
| **Password Reset** | `/forgot-password`, `/reset-password/:token` | Self-service password recovery |
| **Rmagic** | `/rmagic` | "Remote magic-auth" — single-sign-on login when arriving from another Hubzilla hub |

---

## Rich Text Editor

The editor powers post, comment, article, and webpage composition. It shares a single implementation across all composer variants.

**Three tabs:**
- **WYSIWYG** — contenteditable with toolbar
- **Source** — raw markup (BBCode)
- **Preview** — rendered read-only output

**Toolbar (full level — posts and articles):**
Bold, Italic, Underline, Strikethrough, Highlight, Link, Bullet list, Numbered list, H2, H3, Blockquote, Code block, Align left, Align center, Clear formatting

**Comment level** strips structural formatting and shows only inline styles.

**Attachment system:**
- Upload files directly or pick from the cloud files library or photos library
- Tabbed picker modal with multi-select
- Inline thumbnail previews with upload progress and alt-text support
- Per-attachment ACL (allow/deny channels and privacy groups)

**Other editor features:**
- `@`-mention autocomplete with live user search
- `:`-emoji autocomplete
- LaTeX composer — renders math expressions to inline images via KaTeX
- In-browser image editor (Filerobot) — crop, filter, and annotate images before attaching
- Draft auto-save to IndexedDB, keyed per composer scope — survives page refresh
- Ctrl+Enter to submit
- ACL picker for post-level access control (privacy groups, individual channels)
- Category tagging on posts and articles
- End-to-end encrypted direct messages — libsodium (XSalsa20-Poly1305, PBKDF2-derived keys), byte-for-byte compatible with Hubzilla's native `[crypt]` BBCode tag

---

## Feed & Stream

All stream views (network, channel, public, HQ) share the same underlying store and PostCard component.

**View modes** — toggle between Feed, Masonry (grid), List (compact), and Inbox.

**PostCard actions:**
- Like / Dislike (with live count)
- Repeat with dropdown variants
- Reshare with optional added text
- Inline comment composer (threaded)
- Star (bookmarks, local users only)
- Follow / Unfollow author
- Delete (author only, federated)
- Copy link, View source, Refresh post

**Thread rendering:**
- Threaded or flat comment display, toggled per post
- Lazy-loaded comments (load most-recent N, then expand)
- Thread tree building with parent–child hierarchy
- Event posts detected from BBCode and rendered as event cards
- Attachment galleries (images, files) inline

**Stream controls:**
- Background polling for new posts
- Pagination / infinite scroll
- Filter chips (starred, DMs, unread, spam, followed threads, privacy group…)
- Sidebar widgets for categories, tags, and popular posts

---

## Sidebar Widgets

The right sidebar (and a few other layout regions) show context-aware widgets — Notifications, Connections, Categories, Upcoming Events, and more — with sensible per-module defaults. Owners can customize their own pages via a pencil toggle in the sidebar header:

- **Add** — pick any widget allowed on the current page from an "Add widget" list
- **Remove** — take a widget off the page (it isn't deleted, just returns to the picker)
- **Reorder** — move-up / move-down controls on each widget
- **Multiple instances** — some widgets (e.g. Cart's item card) can be placed more than once on the same page, each with its own settings via a gear-icon config panel
- **Pinned widgets** — a small set (e.g. Notifications) are always mounted on every page and can't be removed or reordered
- **Reset to defaults** — discards customization for that page

Changes save immediately to the server (no separate save step), so the layout follows the user across devices, and visitors to a channel see the owner's arrangement rather than their own. See `src/docs/dev/en/slot-system.txt` for the implementation and `src/docs/user/en/widgets.txt` for the end-user guide.

---

## Data Fetching & Caching

Server reads go through [TanStack Solid Query](https://tanstack.com/query/latest/docs/framework/solid/overview). A shared client (`src/shared/lib/query-client.ts`) caches every GET response under a query key:

- **Fresh for 60 s** — repeat reads within a minute hit the cache with zero network requests
- **Stale-while-revalidate** — older data still renders instantly, then refetches in the background (also on window focus and network reconnect)
- **Garbage-collected after 30 min** unused; the cache is in-memory only
- **Request dedup** — components reading the same key share one in-flight request
- **Retries** — failed queries retry twice with backoff before surfacing an error

Component reads use `createQueryResource()` (`src/shared/lib/createQueryResource.ts`), a cache-backed drop-in for Solid's `createResource`. Writes use `useMutation` with cache invalidation — see `src/modules/settings/store/useSectionForm.ts` for the canonical pattern, and `src/docs/dev/en/data-fetching.txt` for the full guide (including which code intentionally stays on plain `createResource`).

---

## Theming

24 built-in themes plus full custom mode. Theme preference is stored in both localStorage (instant restore, no flash on reload) and the server (`/api/settings/display`) so it follows the user across devices.

| Category | Themes |
|---|---|
| Light | Light, Pastel Soft, Warm Paper, Mint Fresh, Sakura, Latte Cream, Gruvbox Light, Catppuccin Latte, Solarized Light, High Contrast Light |
| Dark | Dark, Nord, Dracula, Monokai, One Dark, CyberPunk, Rose Pine, Gruvbox Dark, Catppuccin Mocha, Solarized Dark, Tokyo Night, Matrix, High Contrast |
| Custom | User-defined CSS variables (base, text, accent, surfaces) stored as a JSON blob |

Themes are implemented as CSS custom properties on `data-theme` and integrate with Tailwind CSS v4 utilities throughout.

---

## Responsive Layout

| Breakpoint | Layout |
|---|---|
| Mobile (`< 768px`) | Single column, bottom tab bar (4 items), "More" drawer |
| Tablet (`768px – 1279px`) | Single column, collapsible right sidebar via FAB |
| Desktop (`≥ 1280px`) | Three-column: left nav (fixed) · main content · right sidebar (fixed) |

**Sidebar slot** — modules inject widgets into named region:
- `right` — contextual right sidebar (per-module + global)

---

## Progressive Web App

- Service worker built with [Workbox](https://developer.chrome.com/docs/workbox/) — asset caching for offline-capable use
- Update detection: a toast prompts the user to reload when a new version is available
- Push notification support
- Web app manifest for installability

---

## Internationalization

Three locales ship out of the box:

| Code | Language | Script |
|---|---|---|
| `en` | English | Latin |
| `hi` | Hindi | Devanagari |
| `de` | German | Latin |

Translations are organized into namespace files (`nav`, `layout`, `ui`, `widgets`, `tools`). Locale preference is saved to localStorage (`hz-locale`). Adding a new locale means creating matching namespace files and registering the locale label — no framework changes needed.

---

## Installation Gating

Modules that correspond to optional Hubzilla apps are silently suppressed when the user has not installed that app. The `/api/nav` response returns `installed_apps: string[]`; any module whose `appName` is absent from that list has its routes redirected to `/` and its sidebar widgets hidden.

| Module | Required Hubzilla app |
|---|---|
| Articles | `Articles` |
| Calendar | `Calendar` |
| Chat | `Chatrooms` |
| Files / Cloud | `Files` |
| Photos | `Photos` |
| Public Stream | `Public Stream` |
| Webpages | `Webpages` |
| Wiki | `Wiki` |

All other modules (channel, network, settings, admin, games, notepad, bookmarks, etc.) have no `appName` and are always active.

---

## PHP Backend (`src/Api/`)

A PHP API layer ships alongside the SPA as `Theme\Solidified\Api` inside the Hubzilla theme. It extends Hubzilla's native API with SPA-specific endpoints.

**Key handlers:** `Network`, `Channel`, `Item`, `Nav`, `Settings`, `Profile`, `Photos`, `Files`, `Articles`, `Chat`, `Cal`, `Wiki`, `Webpages`, `Admin`, `Manage`, `Directory`, `Display`, `Pubstream`, `Siteinfo`, `Sw`, `Manifest`

All responses use a consistent JSON envelope — `Response::send()`, `Response::paginate()`, `Response::error()` — with CSRF protection on all mutation endpoints.

---

## Tech Stack

| Area | Library | Version |
|---|---|---|
| Framework | Solid.js | 1.9.10 |
| Router | @solidjs/router | 0.16.1 |
| Data fetching | @tanstack/solid-query | 5.101.2 |
| List virtualization | @tanstack/solid-virtual | 3.13.30 |
| Styling | Tailwind CSS | 4.2.1 |
| Icons | solid-icons | 1.2.0 |
| Animations | solid-motionone | 1.0.4 |
| i18n | @solid-primitives/i18n | 2.2.1 |
| Responsive/media queries | @solid-primitives/media | 2.3.5 |
| Markdown | marked | 18.0.0 |
| HTML sanitization | dompurify | 3.3.1 |
| BBCode | @bbob/parser + @bbob/html | 4.3.1 |
| HTML → BBCode | turndown | 7.2.4 |
| Draft persistence | idb-keyval (IndexedDB) | 6.2.2 |
| Popovers | @floating-ui/dom | 1.7.6 |
| Video/audio player | plyr | 3.8.4 |
| Photo lightbox | photoswipe | 5.4.4 |
| Image editor | filerobot-image-editor | 5.0.0-beta.159 |
| Math rendering | katex | 0.16.22 |
| QR codes | qrcode | 1.5.4 |
| End-to-end encryption | libsodium-wrappers | 0.8.4 |
| Service worker | Workbox | 7.4.0 |
| Build tool | Vite | 8.1.0 |
| TypeScript | — | 6.0.3 |

---

## Getting Started (Development)

**Requirements:** Node.js 18+, a running Hubzilla instance.

```bash
npm install
npm run dev       # Dev server at http://localhost:5173
                  # Proxies /api → https://hz-ddev.ddev.site
```

### Commands

| Command | Description |
|---|---|
| `npm run dev` | Start Vite dev server with API proxy |
| `npm run build` | Production build (`tsc -b && vite build`) |
| `npm run build:all` | Production build + service worker |
| `npm run build:sw` | Build service worker only |
| `npm run watch` | Watch mode build |
| `npm run typecheck` | Type-check with watch |
| `npm run games:download` | Download/refresh the puzzle-game assets used by the Games module (`scripts/download-puzzles.mjs`) |

### Build Output

Vite outputs to `../hz-ddev/core/extend/theme/utsukta-themes/solidified/assets/` (configurable in `vite.config.ts`):

```
assets/
├── app.js           # Main entry
├── app-[name].js    # Code-split chunks
└── app.css          # Styles
```

Static docs and the PHP `src/Api/` directory are copied to the theme root via `vite-plugin-static-copy`.

---

## Project Structure

```
src/
├── App.tsx             # Root component, module auto-import
├── Layout.tsx          # Main layout (nav, sidebars, mobile tab)
├── index.tsx           # Entry point (PWA, theme setup)
├── router.tsx          # Router setup
├── pwa.ts              # PWA update detection
├── i18n/               # i18n provider and locale files
├── modules/            # Feature modules (35 directories)
│   ├── channel/
│   ├── network/
│   ├── articles/
│   └── ...
├── shared/
│   ├── lib/            # Utilities (API, module registry, BBCode, CSRF)
│   ├── store/          # Global state (auth, config, nav)
│   ├── types/          # Shared TypeScript types
│   ├── views/          # Shared UI components
│   ├── widgets/        # Shared widget components
│   ├── editor/         # Rich text editor
│   └── stream/         # Stream/feed components
└── Api/                # PHP backend handlers
```

### Adding a Module

```typescript
// src/modules/myfeature/index.ts
import { registerModule } from "@/shared/lib/module-registry";

registerModule({
  id: "myfeature",
  routes: [{ path: "/myfeature", component: () => import("./views/MyView") }],
  navItem: { label: "My Feature", icon: "star", path: "/myfeature" },
  slots: { right: [MyWidget] },
  // appName: "My App",  // optional: gate on Hubzilla app installation
});
```

Modules are auto-imported via `import.meta.glob()` — no changes to core files needed.

---

## AI Assistance

Parts of this codebase were developed with the assistance of [Claude Code](https://claude.ai/code), Anthropic's AI coding tool. This includes code generation, translation key authoring (i18n locale files), and documentation. All AI-generated content has been reviewed and is the responsibility of the project maintainers.

---

## License

See [LICENSE](LICENSE).
