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
  before?: () => void;           // runs before the step shows; the step then waits for `selector`
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

Add a `data-tour="<module>.<name>"` attribute directly to the widget/element's root DOM node — not a `Slot.tsx`-wide change, since only the module actually being toured needs it (see the five `data-tour="hq.*"` attributes on the HQ widgets in `src/modules/hq/widgets/*.tsx`). Before deciding anything, `startTour()` waits (up to 2s) for the **first** non-`before` step's target to appear: the caller may have just navigated, and a route's widgets are lazy chunks that mount many frames later on a first visit — without the wait, a cold route filters every step out and the tour silently never starts. Only the first step is waited for, so a later target that is deliberately absent doesn't cost a timeout each. It then filters out any step whose target isn't *visible*, so a widget the user has removed from their layout, or hasn't loaded yet, is silently skipped rather than breaking the tour.

## Spotlighting Something That Isn't On Screen Yet

A step whose target lives inside a modal sets `before` — a plain callback run just before the step shows, which opens that target, normally by clicking another tour anchor:

```typescript
const click = (anchor: string) => () =>
  document.querySelector<HTMLElement>(`[data-tour="${anchor}"]`)?.click();

{ selector: '[data-tour="composer.recipient"]', before: click("hq.quick_compose.dm"), ... }
```

Clicking through the DOM rather than calling into the component keeps the tour data a plain object with no imports from the modules it tours. A step with `before` is exempt from the up-front DOM filter (its target can't exist yet) and instead waits via Shepherd's `beforeShowPromise` until `selector` appears, or 3s elapses — the wait never rejects, since a rejected `beforeShowPromise` aborts the whole tour. `src/modules/hq/tours.ts`'s `dm-demo` tour uses this to walk through the DM composer and the full post composer; its steps are ordered so at most one modal is open at a time (a step clicks `composer.close` before moving on).

## Starting a Tour

`src/modules/help/widgets/HelpToursWidget.tsx` lists every registered tour (`getAllTours()`) with a Start button. If the tour has a `path` and the current route doesn't match, it navigates there first — this route-awareness lives in the widget, not in `tours.ts`, so the registry itself has no dependency on `@solidjs/router`.

## Styling

Shepherd ships its own CSS (`shepherd.js/dist/css/shepherd.css`, imported once in `src/index.css`). `startTour()` sets `classes: "hz-shepherd"` on every step so `src/index.css`'s `.hz-shepherd` block can retheme it against the app's CSS custom-property tokens (`--color-surface`, `--color-txt`, `--color-accent`, etc.) instead of Shepherd's hardcoded light-grey defaults — same integration pattern as the Plyr overrides right above it. Shepherd's own `.shepherd-has-title .shepherd-content .shepherd-header` rule has higher selector specificity than a plain `.hz-shepherd .shepherd-header`, so the header background/title/text color overrides need `!important` to actually win.

## Adding a New Tour

1. Add `data-tour="..."` attributes to the elements you want to spotlight.
2. Call `registerTour({ id, label, path, steps: [...] })` from your module's `index.ts` (or an imported side-effect file, mirroring `hq/tours.ts`).
3. Add the tour's i18n keys to the `tour` namespace (`packages/spa-core/src/i18n/locales/{en,de,hi}/tour.ts`) — the dictionary shape is enforced (`namespaces/types.ts`), so a missing locale key is a build error.

Targets are matched with `findVisible()` — the first match with a layout box — and resolved again at show time via `attachTo: { element: () => … }`. A responsive layout keeps both the desktop chrome (`hidden lg:flex`) and the mobile chrome (`lg:hidden`) mounted, so a plain `querySelector` returns whichever comes first in the DOM even when it is `display: none`. This lets a tour list a desktop step and its mobile counterpart side by side (see `ui-basics` in `src/modules/help/tours.ts`, which lists both the left nav and the bottom tab bar): only the one on screen survives the filter.

No per-user "seen this tour" state is tracked — a tour can always be re-run from the Guided Tours widget.
