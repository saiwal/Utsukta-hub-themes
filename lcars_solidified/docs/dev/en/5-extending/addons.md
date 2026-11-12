# Addon Development

An **addon** is a feature added to a running hub by an admin, without rebuilding
the SPA. Regular modules under `src/modules/` are compiled into the bundle at
build time; an addon is fetched and imported at boot instead.

There is no separate addon system. An SPA addon *is* an ordinary Hubzilla addon
— installed, enabled and disabled through `/admin/addons` like any other — that
happens to ship one extra file:

```
addon/hello/
├── hello.php          # ordinary Hubzilla addon header; may do nothing at all
└── spa/entry.js       # ES module, calls registerModule()
```

`hello.php` is required even for a pure-frontend addon, because `App::$plugins`
(what `/admin/addons` writes) is the only enable switch, and core only lists a
plugin it can find a php file for.

## How it loads

1. `GET /spa/addons` (`packages/spa-core/php/Api/Handlers/Addons.php`) walks
   `App::$plugins` and returns every enabled addon that has
   `addon/<name>/spa/entry.js`, as `{ id, url }`. The url carries a `?v=`
   cache-buster so updating an addon busts the browser cache — an HMAC of the
   mtime, not the mtime, since this endpoint is public and a bare timestamp
   says whether a known-vulnerable addon has been patched.
2. `loadAddons()` (`packages/spa-core/src/lib/addons.ts`) publishes the shared
   runtime on `window.__spa`, fetches that list, and `import()`s each entry.
3. `src/index.tsx` awaits `loadAddons()` before `render()`.

Nothing in the module registry needed changing for this. `registerModule()`
writes into signals (`setRoutes`, `setNavItems`, `setWidgetVersion`) and
`getRoutes()` is reactive, so a module registered after boot lights up on its
own — route, nav item and widgets included.

The endpoint is unauthenticated on purpose: the list is site-global, the entry
files are served statically to anyone anyway, and visitors run the SPA too (an
addon may register visitor-visible widgets).

Boot blocks on that one request so a deep link into an addon route matches on
the first render. Offline boots are covered for free — `offline-fallback.ts`
replays `/spa/` GETs from IndexedDB when the fetch throws.

A failed addon is isolated, not fatal: the imports run under `Promise.allSettled`
and a broken entry logs to the console and is skipped. Both the list fetch and
the imports are bounded (5s / 10s), because a blocking boot step with no timeout
is a blank page rather than a degraded one. An addon that lands after its
deadline still registers — it just misses the first render instead of holding it.

Every entry url is checked by `addonUrl()` (`packages/spa-core/src/lib/addon-url.ts`)
before it is imported: same origin, under `/addon/`, ending in `.js`. The list
is not guaranteed to come from the hub — `offline-fallback.ts` replays `/spa/`
GETs out of IndexedDB, which any same-origin script can write — so without that
check an XSS could plant an off-origin entry and have it imported on every later
boot, turning a one-shot XSS into persistent code execution. Check:
`node --experimental-strip-types packages/spa-core/src/lib/addon-url.test.ts`.

## The shared runtime (`window.__spa`)

**An addon must never bundle its own copy of solid-js.** Two reactive graphs in
one page means broken context, signals that don't propagate, and components that
render once and freeze. The host publishes its own instances instead:

```ts
window.__spa = {
  solid,          // solid-js
  solidStore,     // solid-js/store
  solidWeb,       // solid-js/web
  registerModule, // @utsukta/spa-core/module-registry
  getLazy,        //   "
  useI18n,        // @utsukta/spa-core/i18n
  apiFetch,       // @utsukta/spa-core/lib/fetch
};
```

`apiFetch` is the one to use for API calls — it carries credentials, the CSRF
header and the offline fallback. A raw `fetch()` gets none of that.

## Writing an addon, no build step

Solid renders a raw DOM node fine, so a small addon can skip JSX and the whole
toolchain. `addon/hello/spa/entry.js` is the working reference:

```js
const { solid, registerModule } = window.__spa;
const { createSignal, createEffect } = solid;

function HelloView() {
  const root = document.createElement("div");
  root.className = "p-4 space-y-3";

  const [count, setCount] = createSignal(0);
  const button = document.createElement("button");
  createEffect(() => (button.textContent = `clicked ${count()} times`));
  button.addEventListener("click", () => setCount((c) => c + 1));
  root.appendChild(button);

  return root;
}

registerModule({
  id: "hello",
  routes: [{ path: "/hello", component: async () => ({ default: HelloView }) }],
  navItem: { label: "Hello", icon: "home", path: "/hello", href: "/hello" },
  widgets: [{
    id: "hello.demo",
    label: "Hello (addon)",
    loader: async () => ({ default: HelloWidget }),
    slot: "right",
    contexts: "any",
  }],
});
```

Note the route `component` and the widget `loader` are `ComponentLoader`s —
functions returning `Promise<{ default: Component }>`. An addon that has its
component already in hand just wraps it in `async () => ({ default: X })`; one
that wants code splitting uses a real `import()`.

Tailwind classes work, since the host's stylesheet is already on the page — but
only classes the host build already emitted. Tailwind v4 scans source files at
build time and has never seen the addon, so a utility no host file uses does not
exist in the CSS. Stick to classes used elsewhere in the SPA, or ship a
`<style>` block.

## Writing an addon with JSX

Not yet packaged. The shape is a normal Vite + `vite-plugin-solid` build in
`lib` mode, with everything the host provides marked external and resolved
against `window.__spa`:

```ts
// vite.config.ts of the addon — sketch, no template shipped yet
build: {
  lib: { entry: "src/entry.tsx", formats: ["es"], fileName: () => "entry.js" },
  outDir: "spa",
  rollupOptions: {
    external: ["solid-js", "solid-js/web", "solid-js/store"],
  },
},
```

Externalised bare specifiers still need to resolve in the browser. Either add a
tiny alias plugin rewriting them to a local shim that re-exports from
`window.__spa`, or declare an import map. Whichever route, the check is the
same: the built `entry.js` must contain no copy of solid-js.

## Serving

`addon/<name>/spa/entry.js` is served statically — nginx's `.js` location hits
`try_files $uri` before the front controller, and Apache's `.htaccess` only
rewrites when the file does not exist. No webserver configuration is needed.

In dev, `/addon` is in the Vite proxy list (`vite.config.ts`), so `npm run dev`
loads addons from the ddev hub like everything else.

## Constraints and gotchas

- **Widget ids are persisted** in user layouts (pconfig `spa`/`widget_layout`).
  Namespace them `<addonId>.<name>` and never rename one once shipped, or users
  silently lose a placed widget. The registry rejects duplicate module and
  widget ids with a `console.warn` and keeps the first registration.
- **There is no unregister.** Disabling an addon in `/admin/addons` removes it
  from the next boot's list; anything already loaded in an open tab stays until
  reload.
- **Addon JS runs with the user's full session**, at the same trust level as
  core. That is inherent — sandboxing in an iframe would break the widget and
  slot model, which is the point of the system. The trust boundary is therefore
  *install time*, and it belongs to whatever installs the addon, not to the
  loader. Core's CSP can't help either: it is `script-src 'self'`, and addons
  are same-origin by construction.
- Because this endpoint is unauthenticated, the enabled-SPA-addon list is
  public — deliberate (visitors run the SPA and may see addon widgets), but it
  is a fingerprinting aid for targeting a vulnerable addon.
- Addons are imported strictly *after* the built-in module glob, and
  `registerModule()` keeps the first registration of a colliding id. An addon
  therefore cannot hijack a core module or widget id. That ordering is
  load-bearing; don't reorder it.
- **i18n**: addons can call `useI18n().t`, but they cannot add namespaces to the
  host dictionary (`RawDictionary` is compile-time checked). Ship literal
  strings, or your own lookup table.
- Route paths and module ids share a namespace with built-in modules. Prefix
  them.

## Testing an addon

1. Drop the addon in `addon/<name>/` and enable it in `/admin/addons`.
2. `curl -sk https://<hub>/spa/addons` — the addon should appear with a
   `?v=<digest>` url.
3. `curl -sk -o /dev/null -w "%{http_code} %{content_type}\n" https://<hub>/addon/<name>/spa/entry.js`
   — expect `200 text/javascript`.
4. Reload the SPA and visit the route. Check the console: a failed addon logs
   `addon "<id>" failed to load` and the rest of the app keeps working.
