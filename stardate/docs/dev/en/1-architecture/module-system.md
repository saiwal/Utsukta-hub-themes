# Module System

Every feature in the SPA is a self-contained **module**. A module declares its routes, navigation item, UI slot contributions, and (optionally) a Hubzilla app prerequisite.

## Registering a Module

Create `src/modules/<id>/index.ts` and call `registerModule()`:

```typescript
import { registerModule } from "@utsukta/spa-core/module-registry";
import { useI18n } from "@utsukta/spa-core/i18n";

registerModule({
  id: "myfeature",
  routes: [
    { path: "/myfeature", component: () => import("./views/MyView") },
    { path: "/myfeature/:nick", component: () => import("./views/MyView") },
  ],
  navItem: {
    label: () => useI18n().t("nav.myfeature"),
    icon: "star",
    path: "/myfeature",
    href: "/myfeature",
    context: "owner",        // who sees this nav item
  },
  widgets: [
    {
      id: "myfeature.sidebar",         // stable, persisted — never rename once shipped
      label: () => useI18n().t("widgets.myfeature_sidebar"),
      loader: () => import("./widgets/MySidebarWidget"),
      slot: "right",
    },
  ],
  appUrlSlug: "/myfeature",  // omit if no Hubzilla app prerequisite
});
```

The file is auto-imported by `App.tsx` via `import.meta.glob("./modules/*/index.ts", { eager: true })`. You never need to update a central list.

See `slot-system.md` for the full `WidgetDef` reference (multi-instance widgets, config panels, global vs. module-local widgets, layout templates).

## ModuleDef Interface

```typescript
interface ModuleDef {
  id: string;                        // unique module identifier
  routes: RouteDef[];                // SPA routes (lazy-loaded)
  navItem?: NavItemDef;              // navigation entry (optional — some modules are widget-only, e.g. "blocks")
  widgets?: WidgetDef[];             // widget contributions — see slot-system.md
  /** @deprecated Ignored by the registry; migrate to `widgets`. */
  slots?: SlotsDef;
  permissions?: string[];            // declared but nothing reads it yet
  /** Stable URL fragment from the app's .apd (e.g. "/articles/", "/cdav/addressbook").
   *  Matched against installed app *urls*, not display names. */
  appUrlSlug?: string;
  /** SPA-only feature with no backing Hubzilla app — appears as a toggle in
   *  Settings → Integrations, gated off a pconfig list. */
  frontendFeature?: {
    label: string | (() => string);
    description?: string | (() => string);
    defaultEnabled?: boolean;        // false = opt-in. Defaults to true.
  };
  requiresAuth?: boolean;            // true = anonymous visitors are redirected to /login
  /** Reactive accessor for the layout-template id assigned to the item the
   * active route is showing (e.g. a webpage's assigned template). See
   * "Layout templates" in slot-system.md. */
  pageTemplate?: () => string | null | undefined;
  /** Reactive chrome mode for the item the active route is showing.
   *  "zen" hides all chrome; "focus" keeps header/contentTop/footer;
   *  "wide" hides only the right sidebar; "compact" hides the nav rail and
   *  mobile bars. See slot-system.md. */
  pageChrome?: () => "default" | "zen" | "focus" | "wide" | "compact" | undefined;
}
```

`slots` is a deprecated, ignored field kept only for backward compatibility with older module code — new modules should declare sidebar/region content via `widgets`, not `slots`. A console warning fires if a module still sets a non-empty `slots` object.

## NavItemDef

```typescript
interface NavItemDef {
  label: string | (() => string);  // static string or reactive i18n accessor
  icon: string;                    // icon key (see solid-icons / icon map)
  path: string;                    // route path (for active-link matching)
  href: string | (() => string);   // navigation target (can be reactive)
  context?: NavContext | NavContext[];
  hidden?: boolean;
  /** Help-mode target ("nav.<topic>" form) — see help-mode.md. */
  helpTarget?: string;
}

type NavContext =
  | "owner"      // logged-in user on their own channel
  | "local"      // logged-in user on someone else's channel
  | "remote"     // OWA / remote-authenticated visitor
  | "anonymous"  // unauthenticated
  | "admin"      // administrator
  | "all";       // always visible
```

Use reactive `href` when the destination depends on the current channel nick:

```typescript
import { usePageNick } from "@utsukta/spa-core/store/site-config";

href: () => `/photos/${usePageNick()()}`,
```

## App Gating (appUrlSlug)

When `appUrlSlug` is set, the module is suppressed unless an installed Hubzilla app's **url** contains that fragment.

> **Match on url, never on app name.** `app_name` is a translated string that can stay frozen at whatever it was when an old channel installed the app, so name matching silently fails on non-English or long-lived channels. The url is never translated. The value comes from the app's `.apd` — e.g. `carddav.apd` has `url: $baseurl/cdav/addressbook`, so the slug is `/cdav/addressbook`.

- Routes redirect away (via `ModuleGuard` in `App.tsx`).
- Slot widgets are not rendered (checked in `Slot.tsx`).
- The nav item comes from the server's app list and is resolved back to the module by `moduleIdForPath()`, so the module should register a route at the app url itself.

The installed-apps list comes from `/spa/nav` (it holds raw app urls) and is an empty `Set` during the initial load — while the set is empty every gated module is treated as active (pass-through).

```typescript
// module-registry.ts
export function isAppInstalled(installedApps: Set<string>, urlSlug: string): boolean {
  for (const url of installedApps) if (url.includes(urlSlug)) return true;
  return false;
}

export function isModuleActive(
  moduleId: string,
  installedApps: Set<string>,
  disabledFrontendModules?: Set<string>,
): boolean {
  const mod = modules.get(moduleId);
  if (!mod) return false;
  if (!frontendFeatureEnabled(mod.frontendFeature, moduleId, disabledFrontendModules)) return false;
  if (!mod.appUrlSlug) return true;          // no gate
  if (installedApps.size === 0) return true; // not yet loaded
  return isAppInstalled(installedApps, mod.appUrlSlug);
}
```

`frontendFeature` modules are gated by the same function, off the persisted
disabled-module list rather than the installed-apps set. The stored list holds
ids whose state *differs* from the module's default, so the effective state is
`default XOR override` (`frontendFeatureEnabled`).

## Recommended Folder Structure

```
src/modules/myfeature/
├── index.ts        # registerModule() call — the only auto-imported file
├── api.ts          # typed fetch wrappers (apiFetch + apiError)
├── store.ts        # module-local signals, only if state outlives one component
├── views/
│   └── MyView.tsx  # lazy-loaded page components
└── widgets/
    └── MySidebarWidget.tsx
```

Newer modules keep `api.ts` and `store.ts` as flat files rather than
`api/api.ts` + `store/store.ts`; both spellings exist in the tree. Skip
`store.ts` entirely unless state genuinely has to outlive a single component —
prefer `createQueryResource` + `useMutation` with query invalidation.

A module also needs i18n entries before it will compile: a `nav.<id>` label, its
own namespace in `packages/spa-core/packages/spa-core/src/i18n/locales/` (types.ts + all three
locales), and a `widgets.*` label per registered widget.

## In-Component App Gating

`appUrlSlug` gates an entire module. To gate only a **section within a component**, combine `useInstalledApps()` with `isAppInstalled()` — never `installedApps().has(name)`, for the same reason the module gate matches on url:

```typescript
import { useInstalledApps } from "@utsukta/spa-core/store/nav-store";
import { isAppInstalled } from "@utsukta/spa-core/module-registry";

const installedApps = useInstalledApps();
const affinityInstalled = () => isAppInstalled(installedApps(), "/affinity");
```

Then wrap the conditional UI in a `<Show>`:

```tsx
<Show when={affinityInstalled()}>
  <AffinitySlider ... />
</Show>
```

**Where this is used:**
- `StreamFiltersWidget.tsx` — the Closeness / Affinity slider (`/affinity`) and the Privacy Groups picker (`/group`).
- `SubPageLayout.tsx` — sub-nav items carry a `requiresApp` slug, checked the same way.

The same empty-set pass-through rule applies here: `useInstalledApps()` returns an empty `Set` until `/spa/nav` responds, so the section is visible during the initial render. If you want to hide-by-default instead, guard with `installedApps().size > 0 && affinityInstalled()`.

## Multiple Routes

A module can register as many routes as needed. All share the same `moduleId`:

```typescript
routes: [
  { path: "/articles",           component: () => import("./views/ArticlesView") },
  { path: "/articles/:nick",     component: () => import("./views/ArticlesView") },
  { path: "/articles/:nick/:uuid", component: () => import("./views/ArticleView") },
],
```

`moduleId` is set automatically on every route by `registerModule()` — do not supply it manually.
