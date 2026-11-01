# Hubzilla *Solidified* - Developer Guide

This is the developer reference for **hubzilla-spa**, a Solid.js single-page application for the Hubzilla federated social platform.

## Contents

### Architecture

- [Overview](overview): Architecture overview, tech stack, project layout
- [Module system](module-system): Creating, registering, and gating feature modules
- [Slot system](slot-system): Injecting widgets into layout regions
- [Routing](routing): SPA routing, lazy loading, and the ModuleGuard
- [Nav system](nav-system): Navigation computation and viewer roles
- [Stores](stores): Reactive state: auth, nav, site-config
- [i18n](i18n): Internationalization (i18n) system

### Interface

- [Design guidelines](design-guidelines): How a module should look: page anatomy, tokens, type, controls, motion, and a redesign checklist (reference: the Jellyfin module)
- [Help mode](help-mode): The click-to-see-docs contextual help overlay
- [Tours](tours): The shepherd.js-based guided-tour registry
- [File preview](file-preview): Inline preview for image/pdf/epub/video/audio/text/markdown files
- [Excalidraw](excalidraw): The React-in-Solid Excalidraw whiteboard integration
- [Diagrams](diagrams): Mermaid code blocks rendered client-side: the class marker each renderer must emit, the wiki bbcode token swap, bundle/offline
- [Markdown](markdown): Markdown as an input layer: the two converters, the extended syntax, and why the composer and the saved post must agree

### Data

- [Data fetching](data-fetching): TanStack Query caching, createQueryResource, mutations
- [API client](api-client): Frontend API utilities and CSRF handling
- [API reference](api-reference): Frontend API reference
- [PHP API](php-api): Backend PHP API: router, auth, response, handlers
- [Offline](offline): Service worker, fetch fallback, and the local message store
- [Core parity](core-parity): Posting through core: delivery and clone-sync side effects the SPA must not skip

### Features

- [Jellyfin](jellyfin): Jellyfin player: browser-direct architecture, player store, remote/WebSocket, SyncPlay parties, video/HLS, clips, profile card, API quirks
- [Drafts](drafts): Draft storage, the composer autosave/pending-draft mechanism, and the HQ DraftsWidget
- [Cards and kanban](cards-kanban): Card metadata, the card face, and the kanban board (boards as categories, columns as decks)
- [Stream sorting](stream-sorting): Post sort orders, the ranked (top/hot/discussed/controversial) algorithms, and the shared SortSelect control
- [Chat federation](chat-federation): Unread dots for bookmarked rooms on other SPA hubs: signed subscribe/notice between hubs, storage, throttle
- [Post import](post-import): Importing a federated post by URL: Zot vs ActivityPub, ancestor and reply discovery, the Lemmy fallback
- [Cloud module](cloud-module): Cloud/Files: folder paths as URLs, the folder-hash resolver, and the SabreDAV CSP workaround

### Extending

- [Addons](addons): Runtime-loaded addons: `spa/entry.js`, the shared `window.__spa` runtime, styling, security rules
- [Theme scaffold](theme-scaffold): Generating a new, independently-branded theme package from `@utsukta/spa-core`

### Roadmap

- [To do](todo): Planned-but-not-yet-built work, with design decisions and implementation shape

## Quick Start

```bash
npm run dev        # dev server (proxies /api to https://hz-ddev.ddev.site)
npm run build      # production build
npm run typecheck  # TypeScript watch
```

The app entry point is `src/index.tsx`. All feature modules live under `src/modules/` and self-register via `import.meta.glob` in `src/App.tsx`.
