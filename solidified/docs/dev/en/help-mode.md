# Help Mode

Help mode is an in-app "click anything to see its docs" overlay. A user turns
it on via `HelpTrigger` (the `?` button in the top nav), then clicks any
help-enabled element to open a modal rendering the relevant section of the
**user** docs (`src/docs/user/<locale>/*.md`) — no page navigation required.

## Turning It On

`src/shared/store/help-mode.ts` holds the state as plain signals:

```typescript
export function useHelpMode() {
  return {
    helpMode,       // is help mode currently armed?
    helpTarget,     // the "<module>.<section>" string of the open doc, or null
    docType,        // currently only "user"
    setDocType,
    enter: () => setHelpMode(true),
    exit: () => { setHelpMode(false); setHelpTarget(null); },
    pick: (target: string) => { setHelpTarget(target); setHelpMode(false); },
  };
}
```

`HelpTrigger.tsx` toggles `enter()`/`exit()`. While armed, a banner
("Click anything for help" / Cancel) renders via `HelpOverlay.tsx`.

## Marking an Element Helpable

The `use:helpable` directive (`src/shared/lib/helpable.ts`) wires an element
up to help mode: while help mode is armed, hovering the element outlines it
and hovering shows a "help" cursor; clicking it calls `pick(target)` instead
of the element's normal click behavior (stopped via `preventDefault` /
`stopPropagation`).

```tsx
import { helpable } from "@/shared/lib/helpable";
// Solid strips unused imports for JSX-only directives — this keeps it live:
void helpable;

<button use:helpable={"channel.connections_widget"}>...</button>
```

The directive accessor is any string in `"<module>.<section>"` form (section
optional). It's applied throughout the app — nav items, widget headers, and
composer buttons (`NavUtilities.tsx`, `WidgetArrangementEditor.tsx`,
`UserMenu.tsx`, `Slot.tsx`, `PostComposer.tsx`, `DMComposer.tsx`, and others).

## Resolving a Target to a Doc Section

`helpTarget` strings resolve the same way in two places:

- `NavItemDef.helpTarget` / the default derived from a nav item's path
  (`navItemHelpTarget()` in `useNav.ts`).
- `WidgetDef.helpTarget`, defaulting to `widgets.<id>` when unset.

Given a target like `"channel.connections_widget"`:

1. Split on the first `.` → module `"channel"`, section `"connections_widget"`.
2. Fetch `src/docs/user/<locale>/channel.md` (via `useDocs(module, docType)`,
   which the static-copy plugin ships alongside the built theme).
3. If a section is given, extract just the matching heading's body — any
   level (`#`-`######`), whitespace-tolerant — see `extractSection()` in
   `HelpOverlay.tsx`. A heading is matched one of two ways:
   - a `<!-- section_slug -->` comment on its own line immediately above it,
     matched literally against the target's section, or
   - failing that, the heading text itself (underscores in the target become
     spaces, case/whitespace-insensitive).
   Falls back to the whole document if neither matches.
4. Render the extracted Markdown with `marked`, rewriting any relative
   image `src` to the doc's own asset path
   (`/view/theme/solidified/docs/<docType>/<locale>/...`).

Text matching only works against the doc in the fetched locale, so a fully
translated heading (e.g. `hi/widgets.md`'s headings are translated, not
mirrors of the English wording) won't match the target's English-derived
slug. Anchor comments are the fix: keep the same `<!-- section_slug -->`
line above the heading in every locale's translation, and the section
resolves regardless of wording. Untranslated docs (or sections not yet
annotated) still fall back to text matching, which only works in `en`.

## The Modal

`HelpOverlay.tsx` renders two independent overlays from the same store:

- A small pinned banner while `helpMode()` is true (before anything is
  clicked).
- A centered modal while `helpTarget()` is set (after a click), with a
  breadcrumb-style header (`target.split(".").join(" › ")`) and a tab row
  reserved for future doc types beyond `"user"`.

Closing the modal (backdrop click or the × button) calls `exit()`, which
clears both `helpMode` and `helpTarget`.

## Adding Help to a New Widget or Nav Item

Nothing beyond setting `helpTarget` (or relying on the default) and wrapping
the relevant element with `use:helpable={target}` is required — there's no
central registry to update. Make sure the target's module doc
(`src/docs/user/en/<module>.md`) actually has a heading matching the
target's section, or the modal falls back to showing the whole document. If
the doc is translated into other locales, add a matching
`<!-- section_slug -->` anchor comment above the heading in each
translation so the section still resolves there.
