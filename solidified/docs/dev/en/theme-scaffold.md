# Scaffolding a New Theme

`@utsukta/spa-core` (`packages/spa-core/`) holds every part of this codebase
that isn't presentation: the fetch/API client, module registry, i18n
loading, all reactive stores, `useLayoutChrome()` (the root layout's
non-visual state), and the PHP `Router`/`Handlers`/`Concerns`/`Auth`/
`Response` layer. A second theme — differently branded, a different CSS
framework (Bootstrap, AdminLTE, whatever) — depends on that same package and
supplies only markup: `theme.css`, `fonts.css`, `Layout.tsx`, per-module
view files, and two small PHP metadata files.

This doc walks through generating one.

## Generate the skeleton

```bash
node scripts/create-theme.mjs <slug>
```

`<slug>` must be lowercase letters/digits only, starting with a letter (e.g.
`adminlte`) — it's embedded directly in generated PHP function names
(`{slug}_init()`, `{slug}_assets()`, `{slug}_webpush_send()`), which can't
contain hyphens or other punctuation safely.

This copies `scaffold/theme-template/` to `packages/theme-<slug>/`,
substituting the slug into every file that needs it. Then:

```bash
npm install                    # links @utsukta/spa-core + the new workspace member
cd packages/theme-<slug>
npm run build                  # verify the toolchain wires up before touching anything
```

The build writes to
`../../../hz-ddev/core/extend/theme/utsukta-themes/<slug>/assets` — same
DDEV-sibling-repo layout `hubzilla-spa` itself uses, just two directories
deeper since this package lives under `packages/`. Adjust
`theme.config.mjs`'s `BUILD_OUT_DIR_REL`/`SW_OUT_DIR_REL` if your local
layout differs.

## What you get, and what you don't

The scaffold is a **working minimal starter**, not a blank shell and not a
copy of solidified's full UI. Concretely:

- **`vite.config.ts`, `theme.config.mjs`, `build-sw.mjs`, tsconfig\*, `postcss.config.js`, `tailwind.config.js`** —
  copied from hubzilla-spa's own, parameterized by the new slug. These are
  per-project build config, not something spa-core exports for reuse — like
  most Vite scaffolds, they're meant to be copied and then diverge per
  project, not imported.
- **`src/php/theme.php`, `manifest.php`, `default.php`, `doubleleft.php`, `config.php`, `src/mod/spa.php`, `src/hooks/webpush.php`, `src/composer.json`** —
  fully templated and working as-is. `mod/spa.php` requires Composer's
  autoloader and dispatches through `Utsukta\SpaCore\Api\Router` directly —
  that namespace is fixed (not slug-templated) because it's the same shared
  package for every theme, installed via the path repository in
  `composer.json` (`utsukta/spa-core`, resolved against the `spa-core/`
  folder `vite.config.ts` copies in alongside this theme's own deployed
  output — see the `viteStaticCopy` target with `rename: "spa-core"`).
- **`src/index.tsx`, `src/App.tsx`, `src/router.tsx`** — generic app
  bootstrap (query client, i18n provider, router, auth/module guards). Zero
  branding in these; copied verbatim.
- **`src/Layout.tsx`, `src/shared/views/{NavItem,Slot}.tsx`** — **minimal
  starters, not solidified's real ones.** `Layout.tsx` calls
  `useLayoutChrome()` from spa-core (the same hook solidified's ~750-line
  Layout.tsx uses) for all state — nav data, panel open/close, page-chrome
  mode, drag-to-reorder, scroll tracking — and renders bare-bones markup
  around it. `Slot.tsx` resolves and renders a slot's default widgets only:
  no user layout overrides, no edit-mode add/remove/reorder picker, no
  masonry packing, no per-instance widget config. `NavItem.tsx` renders a
  generic dot icon for every nav item instead of solidified's ~30-entry
  icon map. None of the omitted features are hard requirements — the app
  runs without them — they're just not built out yet. Read solidified's own
  versions of these three files as the reference for the full pattern, and
  `slot-system.md` for the complete `WidgetDef`/layout-override reference.
- **Not mounted at all in the starter `Layout.tsx`**: `HelpOverlay`,
  `ToastContainer`, `ConnectionRequestModalHost`, `FeedModalHost`,
  `RemoteAuthBanner`, the mobile "more" overflow drawer, the desktop
  action-items overlay, the channel switcher. Add them back (copying from
  solidified's `Layout.tsx`/`shared/views/`) once you need those features —
  their absence doesn't break anything else, those specific UI surfaces
  just have nowhere to render yet.
- **`src/index.css`, `src/styles/theme.css`** — copied from solidified's
  real files, not blank, because they're not purely branding. A large part
  of `index.css` (`.bb-share`, `.bb-nsfw-*`, `.bb-event`, `.bb-latex-img`,
  `img.emoji`, the `.rich-editor [contenteditable]` rules) styles class
  names that `@utsukta/spa-core/lib/bbcode.ts` bakes directly into rendered
  post/comment HTML — skip these and posts/comments/the NSFW reveal panel/
  the editor render visibly broken, not just unbranded. `theme.css`'s
  `[data-theme="<id>"]` blocks match `@utsukta/spa-core/types/theme.types.ts`'s
  `THEMES` array (shared infra, iterated by the color-scheme picker
  regardless of theme) — an id with no block here just silently falls back
  to `:root`'s colors. Recolor freely; keep the selectors.
- **`src/styles/fonts.css`** — genuinely blank. Solidified's font files
  aren't copied, so neither is the CSS that references them. Note
  `@utsukta/spa-core/lib/typography.ts`'s `FONT_FAMILIES` map includes a
  `"nunito"` option — picking it without a matching `@font-face` here just
  falls through to the generic sans-serif stack, not broken.
- **`public/` assets** (favicon, touch icons, PWA icons) — not generated at
  all. `index.html`/`default.php`/`doubleleft.php` reference
  `/favicon.ico` etc.; add your own.

## Adding modules

Same as any module in this codebase — see `module-system.md`. Nothing
module-specific is scaffolded; you write `src/modules/<id>/index.ts` +
`views/*.tsx` per module, same `registerModule()` pattern, same
`@utsukta/spa-core` imports for data/logic. Start with a small number of
modules, not all of them at once.

## Same CSS framework, different Solid.js code — not needed

If the new theme is staying on Tailwind and Solid.js, and only the branding
differs, you may not need a second `packages/theme-*` package at all —
consider whether overriding `src/styles/theme.css`'s color values and
`fonts.css` in a fork of solidified itself is simpler than a fully separate
package. The scaffold in this doc is for a genuinely independent
theme (different CSS framework, or maintained separately from solidified).
