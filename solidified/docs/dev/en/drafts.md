# Drafts

Drafts let a composer save unfinished content server-side and resume it later — either in the same composer or, for the types the HQ **DraftsWidget** understands, from anywhere in the app. The mechanism is deliberately generic: one backend endpoint, one client store, and a **scope** string convention that keys everything. Adding draft support to a new content type is almost entirely a client-side change.

## Storage model

A draft is just an `item` row that never got published:

- `resource_type = 'draft'`, `item_unpublished = 1`, `item_deleted = 0`.
- `route` holds a small JSON blob: `{"scope": "...", "slug": "...", "category": "..."}` — the one non-generic field, `scope`, is what the listing query filters on and what the client uses to route a draft back to the right composer.
- `mid`/`uuid` are freshly minted at creation (`item_message_id()`), independent of any "real" item — an edit-draft's `scope` carries the *target* item's own id (uuid/mid) separately; the draft row itself is a throwaway container for body/title/summary text.

Backend: `src/Api/Handlers/Drafts.php`.

```
GET  /spa/drafts[?type=post,article,...]   list drafts, one or more comma-separated scope-prefixes
POST /spa/drafts                            create
POST /spa/drafts/update                     update (mid in body, never in the URL — mids are full URLs)
POST /spa/drafts/delete                     delete
```

`type` tokens are whitelisted to `/^[a-z]+$/` and turned into `route LIKE '%"scope":"<type>:%'` conditions — the backend has no per-type knowledge at all; it only ever matches on the scope string's prefix before the first `:`. This is why adding a new draft-producing content type needs zero backend changes, as long as its scope starts with a plain lowercase word.

## Scope convention

`<type>:<action>[:<id>]`, parsed by a small `scopeParts()` helper wherever it's consumed (composer store, HQ widget):

| Scope | Meaning |
|---|---|
| `post:new` | New top-level post |
| `post:reply:<parentMid>` | Reply draft (not loadable from HQ — see below) |
| `article:new` / `article:edit:<uuid>` | New / editing article `<uuid>` |
| `webpage:new` / `webpage:edit:<uuid>` | New / editing webpage `<uuid>` |
| `note:new` / `note:edit:<mid>` | New / editing notepad note `<mid>` |
| `wiki:<wikiName>:<pageName>` | Wiki page `<wikiName>/<pageName>` (see "Wiki is different" below) |

`id` is reconstructed with `rest.join(":")` (not just `rest[0]`), because mids/uuids embedded there can themselves contain colons (`https://host/item/...`) — splitting and rejoining on `:` round-trips them intact as long as exactly two colons precede the id.

## The composer side: `createComposerStore`

`src/shared/editor/store/createComposerStore.ts` is the shared factory behind Post/Article/Webpage/Note composers (**wiki does not use it** — see below). Given a `scope`, it wires up three independent persistence layers:

1. **Local autosave** — every keystroke is debounced into IndexedDB under `draft:<scope>`, restored on next mount. Pure client-side convenience, never touches the server.
2. **`saveAsDraft()`** — explicit "Save as draft" button action. POSTs the current body/title/summary/slug/category/mimetype plus `scope` to `/spa/drafts`, and pushes the result into `savedDrafts()` (the composer's own in-panel drafts list, opened via a "Drafts (n)" button — see `DraftsList` component). This is the action that makes a draft visible to the HQ DraftsWidget.
3. **`pending-draft:<scope>`** — the cross-navigation handoff. On mount, before falling back to the IDB autosave, the store checks `storageGet("pending-draft:<scope>")`; if present, it hydrates every field from it, sets `loadedDraftId` to the draft's id, and deletes the pending key immediately (so it's consumed exactly once). This is how the HQ widget hands a draft to a composer that hasn't mounted yet: write the pending key, then either open the composer (modal) or navigate to the page that renders it (webpage).

`loadedDraftId` is the thread that ties a loaded draft to cleanup: on successful `submit()`, if it's set, `deleteSavedDraft()` fires automatically — no caller-side bookkeeping needed. Canceling does *not* delete the draft (the pending key was already consumed, but the server-side draft item stays put for next time).

## HQ DraftsWidget

`src/modules/hq/widgets/DraftsWidget.tsx` is the cross-type list. It:

- Fetches `listServerDrafts("post,article,webpage,wiki,note")` and filters client-side to the types it knows how to render (`SHOWN_TYPES`).
- Renders three lines per entry: title (or an "Untitled draft" fallback) / preview snippet / a footer row with the type badge, a delete button, and the draft's `created` date, right-aligned.
- Uses a per-type `isLoadable()` gate — clicking a non-loadable entry does nothing (delete still works). An entry is loadable only when the scope carries everything the target composer needs to render *without* an extra fetch:
  - `post:new`, `article:new`/`edit`, `note:new`/`edit` — always loadable; these composers need nothing beyond what the draft itself stores.
  - `webpage:new` — loadable; `webpage:edit:<uuid>` is **not**, because `WebpageEditorView` needs the page's numeric `iid` to fetch the rest of the page (ACL, layout template, etc.) before it can mount the composer, and the scope only carries the `uuid`. Fixing this would mean either encoding `iid` into the scope or adding a uuid→iid lookup — left as a known gap.
  - `wiki:<wikiName>:<pageName>` — always loadable; both scope segments are literally the route params, so there's nothing else to fetch.
- Loading takes one of two shapes:
  - **Modal-based** (post, article, note): write the pending-draft key, then mount a small composer modal (`ArticleComposerModal`, `NoteComposerModal`) or the existing `PostComposer`. The pending-draft mechanism inside `createComposerStore` does the rest.
  - **Navigate-based** (webpage, wiki): write the pending-draft key, then `navigate()` to the page that owns the composer. `webpage:new` relies on the same generic `createComposerStore` pending-draft pickup once `WebpageEditorView` mounts; wiki needs its own restore logic (next section) since it doesn't use `createComposerStore` at all.

Editing an article or webpage draft only ever restores `title`/`summary`/`slug`/`category`/`body` from the draft — ACL and (for webpages) `layout_template` are not part of the draft and simply default, same limitation the in-composer "Drafts" panel already has for edit-scoped drafts.

## Per-module drafts widgets: `DraftsWidgetBase`

The HQ widget is the cross-type view; a module can also show *just its own* drafts on its own pages (Articles, Webpages, Wiki, and Notepad all do). Rather than four copies of the same list chrome, they share `src/shared/editor/components/DraftsWidgetBase.tsx` — one component that owns fetching, deleting, and rendering, parameterised by what differs per type:

```typescript
<DraftsWidgetBase
  scopeType="webpage"                 // listServerDrafts() filter + client-side scope check
  title={t("webpages.drafts")}
  emptyText={t("webpages.no_drafts")}
  refreshTitle={t("webpages.refresh_drafts")}
  deleteTitle={t("webpages.delete_draft")}
  untitledText={t("webpages.untitled")}
  emptyDraftText={t("webpages.empty_draft")}
  badgeClassName="bg-violet-500/10 text-violet-600 dark:text-violet-400 border-violet-500/25"
  badgeLabel={(scope) => /* "New" / "Edit" from the scope's action segment */}
  isLoadable={(scope) => /* per-type resumability rule, e.g. webpage:new only */}
  onLoad={(entry) => /* open a modal, or navigate() to a routed editor */}
  apiRef={(api) => { /* keep api.reload() around to refetch after an external save */ }}
/>
```

`onLoad` is the only place behavior actually forks:

- **Article** — sets a local `activeEntry` signal and renders `ArticleComposerModal` below `DraftsWidgetBase`, same as the HQ widget's article flow. `apiRef` captures `reload()` so the modal's `onSaved` can refresh the list after `createComposerStore`'s own auto-delete-on-publish has already removed the server draft.
- **Webpage** — no modal; `onLoad` just `navigate()`s to `/webpages/:nick/new` (only `webpage:new` is ever loadable here — see the HQ section above for why edit isn't). `WebpageEditorView`'s `createComposerStore` picks up the pending-draft key generically, so there's nothing else to wire.
- **Wiki** — no modal either; `onLoad` decodes `wikiName`/`pageName` from the scope and `navigate()`s straight to that wiki page. `WikiPageView`'s own pending-draft-restore effect (see below) does the rest. The badge shows the decoded wiki name instead of "New"/"Edit" — wiki scopes don't carry that distinction (see the scope table above) — so users with more than one wiki can tell entries apart.
- **Notepad** — same modal shape as Article, using `NoteComposerModal`; `onSaved` calls `loadNotes(true)` (the notepad module's own list refresh) alongside `api.reload()`.

Each per-module widget wraps `DraftsWidgetBase` in whatever visibility check fits its module. Articles/Webpages/Wiki are channel content — visitable by other users — so they gate on `<Show when={role() === "owner"}>` in addition to `visitorVisible: false` on the registration. `/notepad` has no `:nick` route param at all (it's always "your own notepad"), so `NoteDraftsWidget` skips the role check and relies on `visitorVisible: false` alone, same as its sibling notepad widgets. Registration otherwise looks like any other widget:

```typescript
// modules/webpages/index.ts
widgets: [
  {
    id: "webpages.drafts",
    label: () => useI18n().t("widgets.webpage_drafts"),
    loader: () => import("./widgets/WebpageDraftsWidget"),
    slot: "right",
    visitorVisible: false,
    helpTarget: "webpages.drafts_widget",
  },
],
```

Omitting `defaultModules` defaults it to `[def.id]` (see `module-registry.ts`), which is what makes it appear by default on that module's own pages without any extra config.

## Wiki is different

Wiki pages are git-committed content (`savePage()` in `src/modules/wiki/api.ts`), not `item` rows, and `WikiComposer` is a plain controlled component (body/tab/commit-message state owned by its parent) — there's no `createComposerStore` to hook into. Draft support was added by hand, in parallel to the generic mechanism rather than through it:

- **Scope** is `wiki:<wikiName>:<pageName>`, built directly from the route params in `WikiPageView.tsx` (both are already URL-path-safe, so no extra encode/decode is needed — see the scope table above).
- **Saving**: `WikiComposer` takes an optional `onSaveDraft?: (body: string) => void` prop and renders a "Save as draft" button next to Cancel whenever it's provided. `WikiPageView.handleSaveDraft()` calls `saveServerDraft()` directly with `title` set to the page name, so the HQ widget's title line shows something meaningful without any extra widget-side logic.
- **Loading**: since `loadPage()` always resets `editMode`/`draftContent` from the live page (or blank, if the page doesn't exist yet), restoring a pending draft has to happen *after* that settles. A `createEffect` gated on `!pageLoading()` checks `pending-draft:wiki:<wikiName>:<pageName>`, and if present calls `enterEditModeWithContent(body)` — a small store addition (`src/modules/wiki/store.ts`) that sets `draftContent`/`editMode` directly instead of seeding from `pageData()`. Because the pending-key read is async, it always runs after the same-tick "auto-open edit mode for a not-yet-existing page" effect, so it correctly wins and overrides the auto-opened blank editor.
- **Cleanup**: `WikiPageView` tracks `loadedDraftId` itself (there's no store to do it for it) and calls `deleteServerDraft()` on successful `handleSave()`, mirroring what `createComposerStore.submit()` does generically for the other types.

If another git-backed or otherwise non-`item` content type ever needs drafts, this is the pattern to copy: a scope that's cheaply reconstructible from route params, a manual pending-draft-restore effect gated on the page's own loading state, and a locally-tracked `loadedDraftId` for cleanup.
