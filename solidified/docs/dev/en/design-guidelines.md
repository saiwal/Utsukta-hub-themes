# Design Guidelines

How a module should look and feel, distilled from the Jellyfin module's
redesign (`src/modules/jellyfin/`). Use it as the brief when redesigning any
other module; the Jellyfin files named below are the reference
implementation.

The direction is **elegant and calm**: content first, quiet chrome, space
instead of boxes, and one clear action per screen. It never fights the user's
theme: colours come from the theme tokens and type from the theme font, so
every colour scheme and font setting still applies.

## Principles

1. **Space before boxes.** Group with spacing and soft tone, not outlines. A
   bordered card around every block is the thing to remove first.
2. **One primary action per view.** It is the only filled, accented control on
   screen (Play, Resume, Connect). Everything else is a quiet icon or a ghost
   button.
3. **Content leads, chrome follows.** Artwork, titles and text carry the page;
   controls stay muted until hovered or needed.
4. **The theme's colours and font, always.** Never a hex colour, a second
   typeface, `font-light` or tight tracking. Hierarchy comes from size and the
   app's normal weights.
5. **Motion answers the user.** Dialogs open, panels slide, tabs fade, an
   underline moves. Nothing animates on its own except, at most, one ambient
   focal element, and reduced motion turns that off too.
6. **One memorable moment per module.** Jellyfin's is the full player: the cover
   on a slowly breathing blur of itself. Keep everything around it disciplined.

## Page anatomy

Every module page is a **header widget** (slot `header`) plus a **content
widget** (slot `contentTop`), both `max-w-5xl mx-auto` so they line up. See
`slot-system.md` for the locked header/content split.

```
┌──────────────────────────────────────────────────────────────┐
│ Title               [ Seg | ment ]          🔍  ▢  ⋯          │  header widget
├──────────────────────────────────────────────────────────────┤
│ Tab  Tab  Tab  Tab                                            │  text tabs
│                                                               │
│ content…                                                      │  content widget
│                                                               │
│ ┌──────────────── floating bar (optional) ─────────────────┐ │  sticky, bottom
│ └──────────────────────────────────────────────────────────┘ │
└──────────────────────────────────────────────────────────────┘
```

### Header (same pattern as Cards)

Reference: `jellyfin/widgets/JellyfinHeaderWidget.tsx`, `cards/widgets/CardsHeaderWidget.tsx`.

- **Title** on the left: `text-xl font-bold text-txt`. Name what the user is
  looking at (Jellyfin uses the server's name, falling back to "Jellyfin").
- **Segmented switcher** in the middle, for a module's top-level halves (Music /
  Video, Board / Kanban): `<Tabs label value onChange items class="mx-auto">`
  (`@ui/Tabs`). It renders `role="tablist"`/`tab`, arrow keys switch, and only
  the selected tab is a Tab stop. `variant="underline"` is for in-page
  sections (jellyfin library); `stretch` splits the width equally. A switcher
  made of links (help) stays links.
- **Square icon buttons** on the right:
  `p-1.5 rounded-lg border border-rim bg-surface text-muted hover:bg-elevated hover:text-txt`, icons at 15px.
  Search expands inline into a field and a submit button. Use a small accent
  dot on a button (`absolute -top-0.5 -right-0.5 w-2 h-2 rounded-full bg-accent`)
  for "something here" state rather than a count badge.
- **⋯ menu** for everything secondary (account switching, settings, sign
  out). Don't add another visible button for it.
  Build it with `<Menu label>` + `<MenuItem icon onClick>` / `<MenuSeparator>`
  (`@ui/Menu`): it renders `role="menu"`, arrow/Home/End focus, Escape back to
  the trigger, and closes after an item. `trigger` swaps the default ⋯ button
  (spread its props onto yours); `href`/`download` make a link item; `danger`
  for destructive items; `keepOpen` for items that swap to a sub-panel;
  `useMenu().close()` for a form inside the menu. Value pickers (sort, view,
  language) are not menus.
- `flex-wrap` on the row, so an open search field drops to its own line at
  phone width instead of overflowing.

### Tabs

Reference: `TextTabs` in `jellyfin/library/views.tsx`.

Quiet text tabs with an accent underline that slides to the current one. Use
one row with `overflow-x-auto`, so it scrolls sideways on a phone and never
wraps. Mark tabs with `role="tab"` and `aria-selected`. Use them for views
within a section and for seasons; use the segmented switcher only in the header.

### Detail pages

Reference: `Hero` in `jellyfin/library/views.tsx`.

- A faint wash of the item's own image behind the header (`blur-3xl
  opacity-25`, masked to fade out downward). It's decoration, so give it
  `aria-hidden` and `pointer-events-none`.
- The cover, then the title (`text-2xl font-bold`), then details as **separate
  quiet lines** (`text-sm text-muted`), not one long string joined with dots.
- One `primaryPill` action, an optional `ghostPill` secondary, then ♥ / Share /
  ⋯ as bare icons.
- Long text at `max-w-prose leading-relaxed`.
- A `BackLink` above it. It says "Back" because it returns to wherever the user
  came from; don't label it with a place you can't know.

### Persistent bars and full views

Reference: `jellyfin/player/PlayerBar.tsx`, `player/FullPlayer.tsx`.

- A floating bar: `sticky bottom-[4.75rem] lg:bottom-4` (clears the mobile tab
  bar, which is `h-16`), `rounded-2xl bg-surface/85 backdrop-blur-xl shadow-xl
  ring-1 ring-rim/40`. Make the content column
  `min-h-[calc(100dvh-13rem)] flex flex-col` so the bar rests at the foot of the
  window even on short pages.
- A full view opens as a `Modal` (`bare`): full-screen on phones, a large
  `lg:rounded-3xl` sheet on desktop.

## Colour tokens

Use only these (from `src/styles/theme.css`). They switch with every theme and
custom scheme.

| Token | Use |
|---|---|
| `base` | Page background (note: `base`, not `bg`; `ring-offset-base`) |
| `surface` | Panels, bars, dialogs |
| `elevated` | Tonal grouping, hover and selected rows, switcher track |
| `overlay` | Hover on already-elevated controls |
| `txt` | Primary text |
| `muted` / `subtle` | Secondary text, idle icons |
| `rim` | The rare hairline: input underlines, dividers in menus |
| `accent` | **Fills only**: primary buttons, progress, underlines, dots |
| `accent-fg` | Text and icons on an accent fill |
| `accent-txt` | **Accent as text**: current item, highlighted lyric, active icon |
| `accent-muted` | Soft accent backgrounds |
| `danger` / `success` / `warning` | Status, with the same four roles as accent: the bare name for fills, borders and dots, `-fg` on that fill, `-txt` as text, `-muted` for soft backgrounds |

Statuses never use raw `red-*` / `green-*` / `amber-*`: those skip both the theme
and the custom-scheme editor. `dark:` pairs are unnecessary, because each preset
sets its own contrast-checked values. Raw palette colours remain for
*categories* only (platform badges, file-type icons, the scrapbook palette).

Use opacity modifiers for softness instead of new colours: `bg-elevated/40`
(grouping), `bg-elevated/60` (selected row), `bg-surface/85` with
`backdrop-blur-xl` (floating), `text-muted/60` (de-emphasised), `ring-rim/40`.

Text in the accent colour uses `text-accent-txt`, never `text-accent`: the plain
accent is tuned for fills and can be unreadable as text in some schemes. The
same applies to statuses: an error message is `text-danger-txt`.

## Type

The theme font only, at the app's normal weights.

| Role | Classes |
|---|---|
| Page title (header) | `text-xl font-bold` |
| Item title (detail hero) | `text-2xl font-bold` |
| Focus title (full view) | `text-xl sm:text-2xl font-semibold` |
| Section heading (shelf, list) | `text-sm font-semibold` |
| Body / list rows | `text-sm` |
| Details / captions | `text-xs text-muted` |
| Times, counts | add `tabular-nums` |

Avoid: `font-light`, `tracking-tight`, all-caps labels, monospace for data, a
label above every heading, and long `A · B · C` detail strings.

## Controls

Use `Button` / `IconButton` from `@ui/Button`, not hand-written classes: a theme
swaps the look there (default in `packages/spa-ui`, per-theme overrides in its
`src/ui/` — see theme-scaffold.md) (contract in spa-core `types/ui.types.ts`). `Button` takes
`variant` (`primary` default, `ghost`, `danger`) and `size` (`md` = `h-10` pill,
`sm` = `h-8` pill for dense rows and headers). `IconButton` requires `label`
(becomes `title` + `aria-label`) and takes `variant` (`default`, `danger`,
`square` for the header button), `size` and `pressed`. Style a link as a button
with `buttonClass()` / `iconButtonClass()`. The `iconBtn` / `primaryPill` /
`ghostPill` strings remain for code not yet migrated.

| Control | When |
|---|---|
| `<Button>`: filled accent pill | The single main action of the view |
| `<Button variant="ghost">`: no fill until hover | Secondary actions with a label (Shuffle, Start over, Invite) |
| `<IconButton>`: bare round icon | Everything else (♥, Share, ⋯, transport, close) |
| `<IconButton variant="square">` | Header only (see above) |
| `<Input>` / `<Select>` / `<Textarea>` (`@ui/Field`): bordered box, accent focus ring | Every text field, select and textarea. `size="sm"` in dense rows and widget configs; width comes from the call site (`class="w-full"`) |
| `<Field label hint error>` (`@ui/Field`) around one of those | Any labelled field. It wires `for`/`id`, `aria-describedby` and `aria-invalid` itself — never pass ids by hand. Check: `node scripts/check-ui.mjs` |
| `<Toggle label hint>` / `<Switch>` (`@ui/Toggle`) | Any on/off setting: `Toggle` is the labelled row (label + hint left, switch right), `Switch` the bare control (give it `label` for an accessible name). Native checkbox: `name` submits `"1"`. Return a promise from `onChange` for async toggles that can fail |
| `<Spinner size tone>` (`@ui/Spinner`) | Busy states. Ring in `currentColor` (so inside a button it takes the button's colour); `tone="accent"` for a standalone loader, which should also get `label` to become a `role="status"`. A refresh icon spinning while it reloads stays an icon |
| `<Badge tone size>` / `<CountBadge n>` (`@ui/Badge`) | `Badge` is a tag (`rounded-md`): `tone` neutral/accent/danger/success/warning, `size="sm"` beside metadata. `CountBadge` is the solid unread bubble — hidden at 0, `99+` above `max`, `label` for accessible text. Categorical colours (post type, platform) stay raw |
| `<Avatar src name size>` (`@ui/Avatar`) | Any person/channel picture. `size` in spacing units (`8` = `w-8`). Falls back to initials on a name-derived hue when there is no photo **or it fails to load**; no name → a person icon. Decorative by default; `alt` only when no name is printed beside it. `shape="rounded"` for square-ish slots. Not for product photos or app icons. Responsive sizes (`sm:w-22`) need a plain `<img>` |
| `<EmptyState icon action size>` (`@ui/EmptyState`) | "Nothing here" for an empty list, folder or view. `size="sm"` inside a panel or list, default for a whole view; `action` takes a `Button` or a link styled with `buttonClass()` |

Every icon-only button has `title` and `aria-label`. Toggles use `aria-pressed`
and show "on" with `text-accent-txt`, not a border. Destructive or rare actions
go in a ⋯ menu.

## Surfaces

- **Panels are `<Card>`** (`@ui/Card`): bordered `bg-surface`, `rounded-xl`;
  `raised` adds a small shadow. It has no padding of its own — pass
  `class="p-4"` (or a layout) at the call site. A header bar inside is
  `<CardHeader>` (bottom rule, `px-4 py-3`). Use `cardClass()` when the surface
  must stay an `<li>`/`<form>`/`<section>`. Only group with a card when a group
  genuinely needs a surface; otherwise just spacing. Dialog panels are not
  cards (they keep their large shadow).
- **Dialogs are `<Modal>` + `<ModalPanel>`** (`@ui/Modal`). `Modal` is the
  native `<dialog>` layer (focus trap, Escape, inert page, focus restore);
  `ModalPanel` is the box — a card with `shadow-2xl`, `size` `sm`/`md`/`lg`/`xl`
  for its max width. Give it `title` (+ `onClose` for a close button,
  `description` for a muted line under it) and the dialog is named and
  described for you — no `labelledBy`, no hand-built header bar; `footer`
  renders the action bar. Only a header with more than a title (an icon badge,
  an avatar, extra buttons) stays hand-built.
  Animated panels (`Motion.div`) use `modalPanelClass(size)`. Bottom sheets
  and full-screen viewers stay bespoke.
- **Lists:** `space-y-0.5`, rows `rounded-xl px-3 py-1`,
  `hover:bg-elevated/40`, current row `bg-elevated/60` with `text-accent-txt`.
  No dividers, no outer border. Row actions can fade in on hover, but must
  stay visible at phone width (`max-md:opacity-100`).
- **Grids:** `gap-x-5 gap-y-7`. Cards are `block w-full` buttons. A bare
  `<button>` shrinks to its content, so a `w-full` image inside it measures
  against itself. Hover changes only the title colour.
- **Popovers:** `createPopover` + `<Portal mount={topLayer()}>`, styled
  `rounded-2xl bg-surface/95 backdrop-blur-xl shadow-2xl ring-1 ring-rim/60 p-2`.
  Menu items: `px-3 py-2 rounded-xl text-sm hover:bg-elevated/70`.
- **Dialogs:** always `shared/views/Modal` (native `<dialog>`: focus trap,
  Escape, inert background). Panel `rounded-3xl bg-surface p-6 shadow-2xl`.
  Never add an inline panel that pushes the page down.

## Images

Reference: `Art`, `landscapeUrl()` in `jellyfin/ui.tsx` and `jellyfin/api.ts`.

- Images fade in when loaded and fall back to a quiet placeholder icon if
  missing or broken. Never show a broken-image box.
- Give each kind of thing its natural shape: square (albums), circle (people,
  artists), 2:3 (posters), 16:9 (episodes, video shelves).
- **One shape per row.** A row mixing shapes looks ragged; pick the row's shape
  and choose a matching image (Jellyfin uses backdrops for films in 16:9
  shelves).

## Loading, empty, error

Reference: `SkeletonGrid`, `SkeletonRows`, `SkeletonShelf` in `jellyfin/ui.tsx`.

- Skeletons shaped like the content that's coming, never a "Loading…" line for
  a whole page.
- Load in sections: each shelf or panel appears when its own data lands.
- Empty states say what to do ("No favourites yet — tap ♥ on a track…").
- Errors say what happened and offer the fix ("Your session expired" plus
  "Sign in again").

## Motion

Reference: `jellyfin/anim.ts` (the shared presets from
`spa-core/lib/motion-presets`, made instant under reduced motion).

| Allowed | Preset |
|---|---|
| Dialog / sheet opening | `pop` |
| Panel expanding in place | `slideDown` |
| Bar arriving at the bottom | `slideUp` |
| Switching tab / page content | `fade` (keyed on the view) |
| Tab / switcher indicator | CSS `transition-all duration-300` on the underline |
| Image arriving | opacity transition (in `Art`) |

Not allowed: staggered card entrances, hover lifts, pulsing decoration,
anything that loops without being the module's single focal moment. Any
looping animation goes through the Web Animations API and is skipped when
`prefers-reduced-motion` is set.

## Responsive

Check every screen at about 360px wide (see the "responsive on all screens"
rule).

- Rows of controls get `flex-wrap`; long single lines `truncate`.
- Tabs scroll sideways instead of wrapping.
- Floating bars clear the mobile tab bar (`bottom-[4.75rem] lg:bottom-4`).
- Full views are full-screen below `lg`.
- Side columns (like the watch-party panel) stack below the main content
  below `lg`.

## Tailwind traps

- Class names must appear literally in the source. Tailwind only sees what it
  scans, so build them from whole strings (`SIZES = ["text-sm", "text-base", …]`),
  never from fragments.
- After a build, confirm arbitrary classes actually landed in the CSS
  (`grep -F 'bottom-\[4\.75rem\]' assets/app-*.css`). A wrong token name
  generates nothing and fails silently. That's how `ring-offset-bg` slipped
  through: the token is `base`.

## Redesign checklist

1. Split the routed view into a locked header and content widget.
2. Header: title, switcher if the module has halves, square icon buttons, ⋯ menu.
3. Remove bordered cards; regroup with space and `bg-elevated/40`.
4. Find the one primary action per view and make it the only `primaryPill`.
5. Turn labelled secondary buttons into `iconBtn` (with `title`/`aria-label`)
   or `ghostPill`.
6. Type: replace ad-hoc sizes with the table above. No light weights, caps
   labels or other fonts.
7. Accent: fills `accent`, text `accent-txt`.
8. Replace spinners and "Loading…" with skeletons, loaded per section.
9. Inline settings and editors become `Modal` dialogs.
10. Motion limited to the allowed table, and checked with reduced motion on.
11. Check at 360px, with keyboard focus visible on every control.
12. Pick one memorable moment for the module, and keep it to one.

## Shared helpers

`iconBtn`, `primaryPill`, `ghostPill` and `SkeletonRows` live in
`src/shared/views/ui.tsx`, and the motion presets (`fade`, `pop`, `slideDown`,
`slideUp`, reduced-motion aware) in `src/shared/views/anim.ts`. Jellyfin
re-exports them from its own `ui.tsx` / `anim.ts`; `Art` and the other
skeletons are still Jellyfin-only. Calendar (`src/modules/calendar/`) is the
second module built to this guideline.
