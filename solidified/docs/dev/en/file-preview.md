# File Preview

Inline preview for files opened from Cloud/Files or a stream attachment, instead of a raw browser download/navigation. Shared by both call sites via one classifier and one modal.

## Classification

`packages/spa-core/src/lib/filePreview.ts` exports `classifyPreview(mimetype, filename): PreviewKind`, the single source of truth for "what can we preview inline":

```typescript
export type PreviewKind = "pdf" | "epub" | "video" | "audio" | "image" | "markdown" | "text" | "none";
```

It checks mimetype first, then falls back to a filename-extension regex per kind — attach records don't always carry a precise `filetype` (e.g. generic `application/octet-stream`), and without the fallback those files would silently stay download-only. `kind === "none"` means no inline preview exists; callers fall back to `window.open(davUrl, "_blank")`.

Callers: `src/modules/files/widgets/FilesContentWidget.tsx` (`openItem()`) and `src/shared/stream/components/AttachmentList.tsx` both gate purely on `classifyPreview(...) !== "none"` — adding a new previewable format only requires touching `filePreview.ts` and the modal below, not either call site.

## The Modal

`src/shared/views/FilePreviewModal.tsx` is the one shared viewer, a `Portal` modal that switches on `kind = classifyPreview(props.mimetype, props.filename)`:

- **image** — plain `<img src={url}>`, `onError` fallback to a "failed to load" message.
- **pdf** — fetched into a `blob:` URL via `fetchBlobUrl()`, rendered in an `<iframe>`. The blob URL step matters: some backend routes (e.g. the stream's `/attach/{hash}`) send `Content-Disposition: attachment`, which makes `<iframe src=directUrl>` hijack into a download instead of rendering — a `blob:` URL carries no HTTP headers, so it always renders inline. No PDF.js; relies on the browser's native PDF viewer.
- **epub** — fetched as an `ArrayBuffer` (same content-type guard pattern as pdf, via `fetchArrayBuffer()`) and rendered with `epubjs`: `ePub(buffer).renderTo(container, { flow: "scrolled-doc", ... })`, `flow: "scrolled-doc"` gives a single vertically-scrolling column sized to the container instead of epub.js's default horizontally-paginated two-page spread. Prev/Next buttons call `rendition.prev()`/`rendition.next()`. Since epub.js renders each section into its own iframe document, it can't see the host page's CSS custom properties — `rendition.themes.default({...})` is used to inject literal colors resolved from the host's `--color-surface`/`--color-txt`/`--color-accent` (read via `getComputedStyle(document.documentElement)`) so the book matches the active theme. `book`/`rendition` are torn down `onCleanup`.
- **video** / **audio** — native `<video>`/`<audio>`, enhanced via `mountPlyr()` (`@utsukta/spa-core/lib/usePlyr`, the already-installed `plyr` package).
- **text** — fetched as a string via `fetchText()`, rendered as a numbered, monospace line list. `fetchText`/`fetchBlobUrl`/`fetchArrayBuffer` all share a guard: if the response's `content-type` doesn't match what's expected, they throw instead of handing the caller HTML — some routes fall back to serving the SPA's own HTML shell (auth/routing issue, wrong hash) instead of the real file, and blindly trusting that body would boot a second, broken copy of the app inside an `<iframe>` or corrupt a text preview.
- **markdown** — same fetch as text, parsed with `marked.parse()` and sanitized with `sanitizeHtml()` (dompurify-based, from spa-core) before `innerHTML`.
- Size guard: `TEXT_PREVIEW_MAX_BYTES` (2MB) — text/markdown previews over that show a "too large, use Download" message instead of fetching. No analogous cap on epub/pdf/video/audio.
- Editing: image and video kinds additionally get an "Edit" button (`ImageEditor` / `VideoEditor`, lazy-loaded) — unrelated to preview classification.

## Adding a New Previewable Format

1. Add the `PreviewKind` variant and a mimetype/extension branch in `classifyPreview()` (`filePreview.ts`).
2. Add a rendering branch in `FilePreviewModal.tsx`'s `<Show when={kind() === "..."}>` list, following the fetch-guard pattern (`fetchText`/`fetchBlobUrl`/`fetchArrayBuffer`) that matches what the new format needs.
3. Nothing to change in `FilesContentWidget.tsx` or `AttachmentList.tsx`.
