# Excalidraw Integration

A hand-drawn-style whiteboard (`@excalidraw/excalidraw`, a React component) embedded in an otherwise all-Solid app, offered both as a standalone Tools entry and as a post-composer insertion flow. Registered purely as a `frontendFeature` toggle — it has no routes or nav item of its own.

## Module Registration

`src/modules/excalidraw/index.ts`:

```typescript
registerModule({
  id: "excalidraw",
  routes: [],
  frontendFeature: {
    label: () => useI18n().t("nav.excalidraw"),
    defaultEnabled: false,
  },
});
```

`frontendFeature` (see `ModuleDef` in `packages/spa-core/src/types/module.types.ts`) marks a module as a pure-frontend feature with no backing Hubzilla app — it shows up as a toggle in Settings → Integrations and is gated the same way as `appUrlSlug`-gated modules (`isModuleActive`/`ModuleGuard`/`Slot`), keyed off a pconfig list instead of the installed-apps set. `defaultEnabled: false` means it's opt-in.

## The React-in-Solid Boundary

`src/modules/excalidraw/ExcalidrawCanvas.tsx` is the **only** place React is mounted for this feature. It's a plain Solid component that, on mount, creates a React root (`react-dom/client`'s `createRoot`) inside a bare `<div>` and renders the `Excalidraw` component into it via `createElement`, tearing the root down `onCleanup`. Everything above this component — the Tools subsection, the composer modal — is ordinary Solid.

```typescript
export interface ExcalidrawExport {
  toPngFile(filename?: string): Promise<File>;
}
```

`ExcalidrawCanvas` takes an `onReady?: (api: ExcalidrawExport) => void` prop, called once Excalidraw's own `excalidrawAPI` ref fires. `toPngFile()` wraps Excalidraw's `exportToBlob()` (scene elements + app state + files → PNG blob) and hands back a `File`, so both consumers below can upload/download without touching Excalidraw's internal API shape directly. The API type itself is left as `any` (see the eslint-disable comment in the file) — `excalidrawAPI`'s real type lives under the package's internal, unstable types entry point.

## Two Consumers, One Canvas

- **`src/modules/tools/components/ExcalidrawTool.tsx`** — standalone whiteboard under the Tools app. Renders `ExcalidrawCanvas`, and its "Download" button calls `toPngFile()` then triggers a client-side download via an object URL.
- **`src/shared/editor/excalidraw/ExcalidrawComposerModal.tsx`** — a composer popup (`lazy`-loaded from `EditorToolbar.tsx`, gated behind `isModuleActive("excalidraw", ...)`). Renders the same `ExcalidrawCanvas`; "Insert" calls `toPngFile()`, uploads the result through the normal photo pipeline (`wallAttach()`, the same `wall_attach` upload used by `LatexComposerModal`'s image mode), and inserts a plain `[img alt="Excalidraw drawing"]<url>[/img]` BBCode tag into the post via `props.onInsert`.

Both consumers only ever hold an `ExcalidrawExport` handle — neither reaches into Excalidraw's React internals directly, which keeps the React/Solid boundary at exactly one file.

## i18n

Keys live under `tools.excalidraw*` (Tools entry: `tools.excalidraw`, `tools.excalidraw_download`) and `editor.excalidraw*` (composer: toolbar tooltip, modal title, insert/uploading/rendering button states) in `packages/spa-core/src/i18n/locales/{en,de,hi}/{tools,editor}.ts`.
