# Hubzilla *Solidified* - Developer Guide

This is the developer reference for **hubzilla-spa**, a Solid.js single-page application for the Hubzilla federated social platform.

## Contents

- [Overview](overview) — Architecture overview, tech stack, project layout
- [Module-system](module-system) — Creating, registering, and gating feature modules
- [Slot-system](slot-system) — Injecting widgets into layout regions
- [Drafts](drafts) — Draft storage, the composer autosave/pending-draft mechanism, and the HQ DraftsWidget
- [Help-mode](help-mode) — The click-to-see-docs contextual help overlay
- [Routing](routing) — SPA routing, lazy loading, and the ModuleGuard
- [Stores](stores) — Reactive state: auth, nav, site-config
- [Nav-system](nav-system) — Navigation computation and viewer roles
- [Api-client](api-client) — Frontend API utilities and CSRF handling
- [Api-reference](api-reference) — Frontend API reference
- [Data-fetching](data-fetching) — TanStack Query caching, createQueryResource, mutations
- [Php-api](php-api) — Backend PHP API: router, auth, response, handlers
- [I18n](i18n) — Internationalization (i18n) system
- [Theme-scaffold](theme-scaffold) — Generating a new, independently-branded theme package from `@utsukta/spa-core`
- [Todo](todo) — Planned-but-not-yet-built work, with design decisions and implementation shape

## Quick Start

```bash
npm run dev        # dev server (proxies /api to https://hz-ddev.ddev.site)
npm run build      # production build
npm run typecheck  # TypeScript watch
```

The app entry point is `src/index.tsx`. All feature modules live under `src/modules/` and self-register via `import.meta.glob` in `src/App.tsx`.
