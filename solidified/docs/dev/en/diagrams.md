# Diagrams (Mermaid)

Mermaid code blocks are rendered client-side, after the HTML is on the page,
by `packages/spa-core/src/lib/hydrateMermaid.ts`. It is the same shape as
`hydrateLatex.ts`: a cheap DOM scan, and the library is only imported the
first time a page actually contains a diagram.

```ts
hydrateLatex(el); hydrateMermaid(el);
```

Every call site sits next to a `hydrateLatex()` call: wiki page + preview
(`WikiPageView.tsx`), `ArticleView`, `ArticlesContentWidget`, `CardView`,
`webpages/PageView`, `NotepadContentWidget`. Add both together if you add a
new rendered-content view.

## The marker

`hydrateMermaid()` matches `pre > code.language-mermaid, pre > code.mermaid`,
swaps each `<pre>` for a `<div class="mermaid">` holding the source text, and
calls `mermaid.run({ nodes, suppressErrors: true })`. Every way content reaches
the page has to end up carrying one of those two classes:

| Source | Renderer | Emits |
|---|---|---|
| bbcode `[code=mermaid]` (articles, cards, webpages, notepad) | `bbcode.ts` (browser) | `language-mermaid` |
| Markdown fence in a post/article/card | converted to `[code=mermaid]` on save by `markdown_to_bb()` | then as above |
| Markdown wiki page | `MarkdownExtra` (server, `Wiki.php`) | `mermaid` — its `code_class_prefix` is `""` |
| bbcode wiki page | core `bbcode()` (server, `Wiki.php`) | nothing — `bb_code_options()` drops the language |

The last row is why `Wiki::extractMermaid()` exists: before core's `bbcode()`
runs it lifts each `[code=mermaid]…[/code]` out for an alphanumeric token
(which survives `bbcode()`, `smilies()` and `convert_links()` untouched), and
the handler `strtr()`s the tokens back into
`<pre><code class="language-mermaid">` afterwards. Stored bbcode is already
escaped, so the block is re-escaped with `double_encode = false`.

## Why the wiki renders server-side at all

Unlike items, which `renderBody.ts` renders in the browser the way core's
`prepare_text()` does, `Wiki.php` mirrors core's `Mod_Wiki`: `[[Page]]` link
conversion, `generate_toc`, `NativeWikiPage::bbcode` inside markdown, the
`wiki_preprocess` addon hook, and the MarkdownExtra dialect. Moving it
client-side would mean porting all of that and losing the hook — a page would
render differently in the SPA and in classic Hubzilla.

Related: the wiki's markdown `raw` is unescaped once in `Wiki.php` before both
the editor and the renderer see it (core does the same, `Mod_Wiki.php:350`).
Without it the editor showed `A--&gt;B`.

## Gotchas

- **`securityLevel: "strict"` — keep it.** Bodies arrive from federated
  peers; `"loose"` enables click callbacks and raw HTML labels.
- **Non-breaking spaces.** `bbcode.ts` turns double spaces into `&nbsp;` even
  inside `[code]`. The hydrator replaces U+00A0 with a plain space before
  handing the text to mermaid.
- **Entities.** `mermaid.run()` reads `innerHTML` and entity-decodes it, so
  setting `textContent` is safe for `-->`, quotes and `&`.
- **Theme.** Picked once (`dark` vs `default`, from the `.dark` class on
  `<html>`) when mermaid first loads. Diagrams don't recolour on a theme switch.
- **Re-renders.** A block whose `<pre>` is no longer connected when the import
  resolves (the view re-rendered meanwhile) is skipped; the next effect run
  hydrates the new DOM.
- **Not in the stream.** The stream sanitizer (`lib/sanitize.ts`) uses a
  stricter allowlist; check it keeps `class` on `<code>` before adding the
  hydrator to post rendering.

## Composer (`shared/editor/diagram/`)

A toolbar button opens `DiagramComposerModal` (source + debounced live
preview). It follows `EditorCapabilities.latexMode` rather than adding a
setting of its own — that flag already says "federated body" vs "in-app body":

- **`image`** (post, comment, dm, quick, chat): `renderMermaidToPngFile()` →
  `wallAttach` → `[img width=…]` plus the source in
  `[open=Diagram source][code]…[/code][/open]`. Plain `[code]`, not
  `[code=mermaid]`, or an in-app reader would draw it twice. Shown only where
  `bbTokens()` holds, like the LaTeX image insert.
- **`live`** (article, card, wiki, webpage, block, note): a mermaid code block
  spelled for the body's format — ```` ```mermaid ```` in markdown,
  `<pre><code class="language-mermaid">` in HTML, `[code=mermaid]` otherwise.
  Hidden in text/plain.

`renderMermaidImage.ts` renders with an `%%{init}%%` directive (light theme,
SVG-text labels) instead of `mermaid.initialize()`, so the shared instance's
reader-facing config from `hydrateMermaid` is untouched. Labels as SVG text,
not `<foreignObject>` HTML, are what keep the canvas rasterizable; the canvas is
filled white because mermaid's SVG background is transparent. Both the
preview and the export use that look, so the preview is the uploaded image.

WYSIWYG inserts go through `bbcodeToHtml()` so `htmlToSource()` round-trips
them on blur. Its `pre` case keeps `[code=lang]` and maps U+00A0 back to a
space — before this, one WYSIWYG edit turned any `[code=mermaid]` (or
`[code=php]`) into plain `[code]`. Pinned in `htmlToSource.test.ts`.

**Gate:** the diagram and LaTeX buttons are Settings → Features → Editor
toggles, `spa_diagrams` (default off) and `spa_latex` (default on, so existing
users keep it), checked with `isFeatureEnabled()` in the toolbar. They're
SPA-only, so `Api/SpaFeatures.php` merges them into core's `editor` group at
the three places that read `get_features()` (Features GET, its POST
validation, `/spa/pconfig`) — not through core's `get_features` hook, which
would also list them in classic Hubzilla's settings and need a theme
re-enable. Read them via `SpaFeatures::enabled()`, never `feature_enabled()`:
core resolves an unset feature's default from a list without them, so both
would read false. The gate is authoring only; `hydrateMermaid` and
`hydrateLatex` render for every reader. Check: `Api/SpaFeatures.test.php`.

## Bundle and offline

`mermaid` is a direct dependency, deduped with the copy Excalidraw already pulls
in. Its core and per-type chunks (`app-mermaid*`, `app-*Diagram*`,
`app-cytoscape*`, `app-dagre*`, …) are excluded from the service-worker precache
by `globIgnores` in `build-sw.mjs` and runtime-cached on first use. If a mermaid
upgrade introduces a large chunk under a new name, the `[SW]` precache-size
assert is what catches it.

## Checks

`packages/spa-core/php/Api/Handlers/Wiki.mermaid.test.php` (run by
`npm run test:php`) runs `extractMermaid()` through core's real `bbcode()` and
asserts the token survives, the `language-mermaid` block comes back, nothing is
double-escaped and indentation is kept.
