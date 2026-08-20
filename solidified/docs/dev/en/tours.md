# Guided Tours

A small `shepherd.js`-based registry for step-by-step, spotlight-style walkthroughs of a page — distinct from the contextual "click anything for help" system (see [help-mode.md](help-mode.md)), which answers "what is this?" one element at a time rather than walking a fixed sequence.

## Registry

`packages/spa-core/src/lib/tours.ts` exports:

```typescript
export type Translate = (key: any, ...args: any[]) => string;

export interface TourStepDef {
  selector: string;              // CSS selector for the element to spotlight
  title: (t: Translate) => string;
  text: (t: Translate) => string;
  on?: "top" | "bottom" | "left" | "right";
}

export interface TourDef {
  id: string;
  label: (t: Translate) => string;
  description?: (t: Translate) => string;
  path?: string;                 // route to navigate to first, if not already there
  steps: TourStepDef[];
}

registerTour(tour: TourDef)
getAllTours(): TourDef[]
getTour(id: string): TourDef | undefined
startTour(id: string, t: Translate, labels: TourButtonLabels)
```

`registerTour` follows the same eager-registration-on-import pattern as `registerModule`/widgets: a module calls it as an import-time side effect (see `src/modules/hq/tours.ts`, imported from `hq/index.ts` via `import "./tours"`).

## Why `title`/`text`/`label` take `t` as a parameter

They don't call `useI18n()` themselves. `startTour()` is invoked from a DOM event handler (a button's `onClick`), which runs outside Solid's reactive owner — `useContext` (which `useI18n()` relies on) throws `useI18n must be used within I18nProvider` if called from there, even though the exact same call works fine during render. Threading `t` through as a plain closure parameter sidesteps needing `runWithOwner`: the caller (a component, which does have owner context) calls `useI18n()` once and passes the resulting `t` down.

## Adding a Step Target

Add a `data-tour="<module>.<name>"` attribute directly to the widget/element's root DOM node — not a `Slot.tsx`-wide change, since only the module actually being toured needs it (see the five `data-tour="hq.*"` attributes on the HQ widgets in `src/modules/hq/widgets/*.tsx`). `startTour()` filters out any step whose selector isn't found in the DOM, so a widget the user has removed from their layout, or hasn't loaded yet, is silently skipped rather than breaking the tour.

## Starting a Tour

`src/modules/help/widgets/HelpToursWidget.tsx` lists every registered tour (`getAllTours()`) with a Start button. If the tour has a `path` and the current route doesn't match, it navigates there first and starts the tour on the next animation frame (so the target route's elements have mounted) — this route-awareness lives in the widget, not in `tours.ts`, so the registry itself has no dependency on `@solidjs/router`.

## Styling

Shepherd ships its own CSS (`shepherd.js/dist/css/shepherd.css`, imported once in `src/index.css`). `startTour()` sets `classes: "hz-shepherd"` on every step so `src/index.css`'s `.hz-shepherd` block can retheme it against the app's CSS custom-property tokens (`--color-surface`, `--color-txt`, `--color-accent`, etc.) instead of Shepherd's hardcoded light-grey defaults — same integration pattern as the Plyr overrides right above it. Shepherd's own `.shepherd-has-title .shepherd-content .shepherd-header` rule has higher selector specificity than a plain `.hz-shepherd .shepherd-header`, so the header background/title/text color overrides need `!important` to actually win.

## Adding a New Tour

1. Add `data-tour="..."` attributes to the elements you want to spotlight.
2. Call `registerTour({ id, label, path, steps: [...] })` from your module's `index.ts` (or an imported side-effect file, mirroring `hq/tours.ts`).
3. Add the tour's i18n keys to the `tour` namespace (`packages/spa-core/src/i18n/locales/{en,de,hi}/tour.ts`) — the dictionary shape is enforced (`namespaces/types.ts`), so a missing locale key is a build error.

No per-user "seen this tour" state is tracked — a tour can always be re-run from the Guided Tours widget.
