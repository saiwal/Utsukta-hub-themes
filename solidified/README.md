# Solidified — Hubzilla SPA Theme

A [Solid.js](https://www.solidjs.com/) single-page application (SPA) that ships as the **Solidified** theme for [Hubzilla](https://framagit.org/hubzilla/core), a federated social networking platform. It replaces the classic server-rendered UI with a fast, reactive client-side experience while staying fully integrated with Hubzilla's PHP backend.

> **Alpha:** This theme is in early development. Expect rough edges, and features that are incomplete or subject to change. Planned improvements include broader language support, additional UI themes, and expanded module coverage.

---

## Features at a Glance

- **22 feature modules** — channel, network, articles, photos, files, chat, calendar, wiki, webpages, directory, admin, and more
- **Rich text editor** — BBCode, HTML, and Markdown with WYSIWYG, Source, and Preview tabs
- **Threaded feed** — like, dislike, repeat, comment, reshare, star, and delete with live state
- **22 UI themes** — presets (Nord, Dracula, Catppuccin, Tokyo Night…) plus fully customizable
- **Progressive Web App** — service worker caching, push notifications, background update detection
- **Responsive layout** — desktop three-column, tablet collapsible sidebar, mobile bottom tab bar
- **i18n** — English and Hindi; locale switcher with localStorage persistence
- **Pluggable module system** — every feature self-registers routes, nav items, and sidebar slots

---

## Rich Text Editor

The editor powers post, comment, article, and webpage composition. It shares a single implementation across all composer variants.

**Three tabs:**
- **WYSIWYG** — contenteditable with toolbar
- **Source** — raw markup (BBCode, HTML, or Markdown)
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

## Theming

22 built-in themes plus full custom mode. Theme preference is stored in both localStorage (instant restore, no flash on reload) and the server (`/api/settings/display`) so it follows the user across devices.

| Category | Themes |
|---|---|
| Light | Light, Pastel Soft, Warm Paper, Mint Fresh, Sakura, Latte Cream, Gruvbox Light, Catppuccin Latte, Solarized Light |
| Dark | Dark, Nord, Dracula, Monokai, One Dark, CyberPunk, Rose Pine, Gruvbox Dark, Catppuccin Mocha, Solarized Dark, Tokyo Night, Matrix |
| Custom | User-defined CSS variables (base, text, accent, surfaces) stored as a JSON blob |

Themes are implemented as CSS custom properties on `data-theme` and integrate with Tailwind CSS v4 utilities throughout.

---

## Responsive Layout

| Breakpoint | Layout |
|---|---|
| Mobile (`< 768px`) | Single column, bottom tab bar (4 items), "More" drawer |
| Tablet (`768px – 1279px`) | Single column, collapsible right sidebar via FAB |
| Desktop (`≥ 1280px`) | Three-column: left nav (fixed) · main content · right sidebar (fixed) |

---

## Progressive Web App

- Service worker built with [Workbox](https://developer.chrome.com/docs/workbox/) — asset caching for offline-capable use
- Update detection: a toast prompts the user to reload when a new version is available
- Web app manifest for installability

---

## Internationalization

Two full locales ship out of the box:

| Code | Language | Script |
|---|---|---|
| `en` | English | Latin |
| `hi` | Hindi | Devanagari |

Translations are organized into namespace files (`nav`, `layout`, `ui`, `widgets`, `tools`). Locale preference is saved to localStorage (`hz-locale`). Adding a new locale means creating matching namespace files and registering the locale label — no framework changes needed.

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
| Router | @solidjs/router | 0.15.4 |
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

## Source

Built from [hubzilla_solidjs_spa](https://github.com/saiwal/hubzilla_solidjs_spa) — see that repo for development setup, build instructions, and project structure.

---

## License

See [LICENSE](../LICENSE).
