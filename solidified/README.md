# Solidified — Hubzilla SPA Theme

A [Solid.js](https://www.solidjs.com/) single-page application (SPA) that ships as the **Solidified** theme for [Hubzilla](https://framagit.org/hubzilla/core), a federated social networking platform. It replaces the classic server-rendered UI with a fast, reactive client-side experience while staying fully integrated with Hubzilla's PHP backend.

> The source for this theme is distributed at **[saiwal/Hubzilla-Solidified-Source](https://github.com/saiwal/Hubzilla-Solidified-Source)** on GitHub.

---

## Features at a Glance

- **modules** — channel, network, articles, photos, files, chat, calendar, wiki, webpages, directory, admin, and more
- **Rich text editor** — BBCode with WYSIWYG, Source, and Preview tabs
- **Threaded feed** — like, dislike, repeat, comment, reshare, star, and delete with live state
- **24 UI themes** — presets (Nord, Dracula, Catppuccin, Tokyo Night…) plus fully customizable
- **Cached data layer** — TanStack Query: instant back-navigation, request dedup, background revalidation
- **Progressive Web App** — service worker caching, push notifications, background update detection
- **Responsive layout** — desktop three-column, tablet collapsible sidebar, mobile bottom tab bar
- **Customizable Widget layouts** — add, remove, and reorder sidebar widgets, configure widgets that support multiple instances, a few widgets are pinned on every page
- **i18n** — locale switcher with localStorage persistence
- **Pluggable module system** — every feature self-registers routes, nav items, and sidebar slots

---

## Modules

### Core social

| Module | Routes | What it does |
|---|---|---|
| **Channel** | `/channel/:nick` | A user's public wall with posts, categories, and tags |
| **Network** | `/network` | The authenticated user's personal federated home feed |
| **HQ** | `/hq` | Personal dashboard with notifications and quick actions |
| **Public Stream** | `/pubstream` | Public posts from all federated channels on this server |

### Content apps

| Module | Routes | What it does |
|---|---|---|
| **Articles** | `/articles/:nick/:uuid` | Long-form posts with title, summary, categories, and tags |
| **Photos** | `/photos/:nick/album/:id` | Photo albums with image viewer |
| **Files / Cloud** | `/cloud/:nick/*` | Cloud file browser and storage |
| **Webpages** | `/page/:nick/*` | Static pages with custom slugs |
| **Wiki** | `/wiki/:nick/:wiki/:page` | Hierarchical per-channel wikis |
| **Chat** | `/chat/:nick/:roomId` | Real-time chatrooms |
| **Calendar** | `/cal/:nick` | Channel calendar with CalDAV support |
| **Cart** | `/cart/:nick` | Shopping cart integration |

### Discovery & connections

| Module | Routes | What it does |
|---|---|---|
| **Directory** | `/directory/*` | Connections, contact roles, privacy groups, people search, hub browser |
| **Siteinfo** | `/siteinfo` | Public site statistics and metadata |

### User & admin

| Module | Routes | What it does |
|---|---|---|
| **Settings** | `/settings/*` | 8 sections: display, profile, account, privacy, notifications, integrations, apps, danger |
| **Admin** | `/admin/*` | 13 admin subpages: summary, accounts, channels, security, features, addons, themes, logs, and more |
| **Manage** | `/manage` | Multi-channel identity management |
| **Tools** | `/tools` | Developer and utility tools |
| **Help** | `/help/*` | Integrated documentation with wildcard routing |

### Auth

| Module | Routes | What it does |
|---|---|---|
| **Login** | `/login` | Authentication interface |
| **Logout** | `/logout` | Session termination |

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
- Draft auto-save to IndexedDB, keyed per composer scope — survives page refresh
- Ctrl+Enter to submit
- ACL picker for post-level access control (privacy groups, individual channels)
- Category tagging on posts and articles

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

Three full locales ship out of the box:

| Code | Language | Script |
|---|---|---|
| `en` | English | Latin |
| `hi` | Hindi | Devanagari |
| `de` | Deutsch  | Latin |


## Tech Stack

| Area | Library | Version |
|---|---|---|
| Framework | Solid.js | 1.9.10 |
| Router | @solidjs/router | 0.15.4 |
| Data fetching | @tanstack/solid-query | 5.101.2 |
| Styling | Tailwind CSS | 4.2.1 |
| Icons | solid-icons | 1.2.0 |
| Animations | solid-motionone | 1.0.4 |
| i18n | @solid-primitives/i18n | 2.2.1 |
| Markdown | marked | 18.0.0 |
| HTML sanitization | dompurify | 3.3.1 |
| BBCode | @bbob/parser + @bbob/html | 4.3.1 |
| Draft persistence | idb-keyval (IndexedDB) | 6.2.2 |
| Popovers | @floating-ui/dom | 1.7.6 |
| Service worker | Workbox | 7.4.0 |
| Build tool | Vite | 7.3.1 |
| TypeScript | — | 5.9.3 |

---

## AI Assistance

Parts of this codebase were developed with the assistance of [Claude Code](https://claude.ai/code), Anthropic's AI coding tool. This includes code generation, translation key authoring (i18n locale files), and documentation. All AI-generated content has been reviewed and is the responsibility of the project maintainers.

---

## License

See [LICENSE](../LICENSE).
