# Routing

## Framework

The SPA uses **@solidjs/router** (Solid Router). The root route wraps all pages in `Layout`, and child routes are generated dynamically from registered modules.

## How Routes Are Built

1. Every module calls `registerModule()`, which appends its routes to a Solid signal.
2. `getRoutes()` in `src/router.tsx` exposes that signal.
3. `App.tsx` reads the signal reactively and renders `<Route>` elements with `<For>`.

```typescript
// App.tsx
<For each={getRoutes()()}>
  {(route) => {
    const Comp = lazy(route.component);
    const mid = route.moduleId;
    if (mid && getModule(mid)?.appName) {
      const Guarded = () => (
        <ModuleGuard moduleId={mid}>
          <Comp />
        </ModuleGuard>
      );
      return <Route path={route.path} component={Guarded} />;
    }
    return <Route path={route.path} component={Comp} />;
  }}
</For>
```

## Lazy Loading

Every route component is loaded lazily. Module `index.ts` files use:

```typescript
component: () => import("./views/MyView")
```

`App.tsx` wraps this in `lazy()` at render time. Code splitting is handled automatically by Vite, producing `app-[name].js` chunks.

## ModuleGuard

When a module declares an `appName`, its routes are wrapped in `ModuleGuard`:

```typescript
const ModuleGuard: ParentComponent<{ moduleId: string }> = (props) => {
  const installedApps = useInstalledApps();
  const navigate = useNavigate();
  const active = createMemo(() => isModuleActive(props.moduleId, installedApps()));

  createEffect(() => {
    if (!active()) navigate("/", { replace: true });
  });

  return <Show when={active()}>{props.children}</Show>;
};
```

If the required app is not installed, the user is redirected to `/` (which redirects to `/hq`).

## Default Redirect

```typescript
<Route path="/" component={() => <Redirect to="/hq" />} />
```

The root path always redirects to `/hq`.

## 404

```typescript
<Route path="*404" component={NotFound} />
```

Any unmatched route renders `src/shared/views/NotFound.tsx`.

## Route Path Conventions

| Pattern | Example | Usage |
|---------|---------|-------|
| `/module` | `/network` | Module index, no subject |
| `/module/:nick` | `/photos/alice` | Module scoped to a channel |
| `/module/:nick/:id` | `/articles/alice/abc123` | Single item within a channel's module |
| `/module/:nick/sub/:datum` | `/photos/alice/album/summer` | Sub-resource |

The `:nick` segment is always the channel nickname. Use `useSubjectNick()` from `@/shared/store/site-config` to read it reactively in components.

## Reactive Route List

Because `getRoutes()` returns a Solid signal, modules that are registered after the initial render (e.g. via async imports) will automatically appear as routes without any re-mount of the router.
