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
  Video, Board / Kanban): `flex gap-1 p-1 bg-elevated rounded-lg w-fit mx-auto`.
  The active segment is `bg-accent text-accent-fg font-semibold`, the others
  `text-muted hover:text-txt`, all `px-3 py-1 text-xs rounded-md font-medium`.
  Mark it with `aria-pressed`.
- **Square icon buttons** on the right:
  `p-1.5 rounded-lg border border-rim bg-surface text-muted hover:bg-elevated hover:text-txt`, icons at 15px.
  Search expands inline into a field and a submit button. Use a small accent
  dot on a button (`absolute -top-0.5 -right-0.5 w-2 h-2 rounded-full bg-accent`)
  for "something here" state rather than a count badge.
- **⋯ menu** for everything secondary (account switching, settings, sign
  out). Don't add another visible button for it.
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

Use opacity modifiers for softness instead of new colours: `bg-elevated/40`
(grouping), `bg-elevated/60` (selected row), `bg-surface/85` with
`backdrop-blur-xl` (floating), `text-muted/60` (de-emphasised), `ring-rim/40`.

Text in the accent colour uses `text-accent-txt`, never `text-accent`: the plain
accent is tuned for fills and can be unreadable as text in some schemes.

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

Reference: `jellyfin/ui.tsx` (`iconBtn`, `primaryPill`, `ghostPill`).

| Control | When |
|---|---|
| `primaryPill`: filled accent, `h-10 rounded-full` | The single main action of the view |
| `ghostPill`: no fill until hover | Secondary actions with a label (Shuffle, Start over, Invite) |
| `iconBtn`: bare round icon | Everything else (♥, Share, ⋯, transport, close) |
| Header square button | Header only (see above) |
| Underlined field: `bg-transparent border-0 border-b border-rim focus:border-accent` | Inputs and selects in content and dialogs |
| Bordered field (Cards style) | Header search only |

Every icon-only button has `title` and `aria-label`. Toggles use `aria-pressed`
and show "on" with `text-accent-txt`, not a border. Destructive or rare actions
go in a ⋯ menu.

## Surfaces

- **No bordered cards.** Use `rounded-2xl bg-elevated/40 p-4` when a group
  genuinely needs a surface (the party panel), otherwise just spacing.
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
