# Cards and the kanban board

The cards module (`src/modules/cards/`) renders a channel's `ITEM_TYPE_CARD` items. It has two views of the same data: a masonry pinboard and a kanban board. This page covers the card face, the deck ("shelf") metadata both views read, and the kanban board built on top of it.

## Card metadata

Beyond the ordinary item columns, a card carries three `iconfig` rows and its category terms:

| Where | Key | Meaning |
|---|---|---|
| `iconfig` `cat='system'` | `CARD` | the card's slug (core's own convention) |
| `iconfig` `cat='card'` | `deck` | deck / shelf / kanban column name |
| `iconfig` `cat='card'` | `deck_order` | position within that deck |
| `iconfig` `cat='card'` | `template` | which authoring tab produced the body |
| `term` `ttype=TERM_CATEGORY` | — | categories; also what puts a card on a board |

`FormatsItems`/`Cards.php` normalise these into `Post.deck = { name, order } | null` and `Post.template`.

**Nothing here is a new storage format.** A template only shapes a body out of bbcode core already renders (`src/shared/editor/lib/cardTemplates.ts`), and deck rows are invisible extras to any client that doesn't know them. That is what keeps cards readable in redbasic and on other servers — see "Cross-compatibility" below.

## The card face

`src/modules/cards/components/CardFace.tsx` renders one card, laid out by template. The template is a *hint*: cards authored before the field existed (and federated ones) fall back to `sniffTemplate()`.

Bodies are unpacked with the composer's own `parseTemplate()` rather than a second set of regexes, so the reader and the writer cannot drift. If you change how a template composes, both sides move together — `cardTemplates.test.ts` asserts the round-trip.

```
node --experimental-strip-types src/shared/editor/lib/cardTemplates.test.ts
```

Heights are natural (no fixed `h-64`); the masonry packer in `CardsContentWidget.tsx` handles unequal columns.

## The board model

Two facts define the whole kanban feature:

- **A board is a category.** The board named `roadmap` shows exactly the cards returned by `GET /spa/cards/:nick?cat=roadmap`.
- **A column is a deck.** Within a board, `deck.name` is the column and `deck.order` the position in it.

Everything else is configuration: which boards exist and what columns each has, stored per-channel in pconfig `spa` / `kanban_boards`:

```json
[
  { "name": "kanban",  "columns": ["Todo", "Doing", "Done"] },
  { "name": "roadmap", "columns": ["Q3", "Q4"] }
]
```

Channels configured before multi-board support stored a bare column list in `spa/kanban_columns`. `getKanban()` reads that as a single `kanban` board when `kanban_boards` is absent — a fallback, not a migration, so nothing has to be rewritten and an old client keeps working.

The feature flag is `spa/kanban` (0/1), written from Settings → Integrations.

### Why this shape

Choosing the category as the board axis means:

- **ACL comes for free.** The board's cards come from the ordinary card list, which already appends `item_permissions_sql()` + `item_normal()`. A visitor sees the columns but only their own permitted cards, with no permission code in the kanban path at all.
- **It survives the classic UI.** Categories and decks are both things core stores and preserves.
- **Putting a card on a board is authoring, not a special action** — the composer already has a category field and a deck field.

## Endpoints

All on `Handlers/Cards.php`. Config reads are public (board and column *names* are metadata, like deck names in the deck overview); every write requires the owner.

```
GET  /spa/cards/:nick/kanban        → { enabled, boards: [{ name, columns[] }] }

POST /spa/cards/:nick/kanban-boards → { boards: [...] }        replace the board list
POST /spa/cards/:nick/board-rename  → { from, to }             retag the board's cards
POST /spa/cards/:nick/deck-move     → { uuid, deck, order }    the drag commit
POST /spa/cards/:nick/deck-rename   → { from, to }             (pre-existing) rename a column
POST /spa/cards/:nick/deck-reorder  → { deck, order[] }        (pre-existing)
```

Two notes:

- **`deck-move` exists so a drag is not an edit.** The full card POST wants a whole card payload and runs `item_store_update()` — a term rebuild, a notifier summon, a fresh `edited` timestamp — for what is two `iconfig` rows.
- **`board-rename` is a retag.** Because a board *is* a category, renaming one updates the `TERM_CATEGORY` rows on that channel's cards. The client calls it *before* saving the new board list, so a failure leaves both the name and the cards alone.

`GET .../kanban` is dispatched from `$sub = App::$argv[3]`, which is also the card slug/uuid position — so a card slugged literally `kanban` is shadowed, exactly as one slugged `deck` already was.

## Frontend layout

```
src/modules/cards/
├── api.ts                       fetchKanban, saveKanbanBoards, renameBoard, moveCard
├── lib/kanban.ts                DEFAULT_BOARD, UNFILED, boardView() view signal
├── lib/useKanbanDrag.ts         cross-column pointer drag (+ .test.ts)
├── components/CardFace.tsx      one card, laid out by template
├── components/KanbanBoard.tsx   tabs, columns, drag, board/column CRUD
└── widgets/
    ├── CardsHeaderWidget.tsx    title, search, Board/Kanban switcher
    └── CardsContentWidget.tsx   masonry board, or KanbanBoard
```

State that outlives a component lives in `lib/kanban.ts`: `boardView()` (masonry vs kanban) is a signal backed by `localStorage["hz-cards-view"]`. The **active board** is deliberately *not* — it lives in the URL (`/cards/:nick?board=roadmap`) so a board is linkable and survives reload, and so the cards query re-keys on it automatically.

`KanbanBoard` runs its own `createQueryResource("kanban-cards", { nick, board })` rather than the module's `createStreamStore` singleton: that store is the masonry view's paginated, filter-driven state, and sharing it would make a category click in one view mutate the other. The config query (`"kanban-config"`) is shared by the header switcher, the content widget and the board, so all three cost one request.

## The drag

`lib/useKanbanDrag.ts`. Same technique as spa-core's `createDragReorder` — a dedicated grip, window-level pointer listeners so the drag survives leaving the element, rect hit-testing rather than HTML5 drag events so touch works — but `createDragReorder` reorders **one** flat list and commits one key order, while a kanban move changes *which* list an item is in. So this keeps its own grouped state and is a separate hook rather than a generalisation of that one, whose three call sites all depend on the flat contract.

Render from `display()`, not from the source grouping, or the card won't follow the pointer.

Cards drag by a grip rather than by the card body on purpose: whole-card drag needs `touch-none`, which would kill page scroll over the board on mobile. Same reason `NavItem` has a handle.

The geometry is split out as two pure functions so it can be tested without a DOM:

```
node --experimental-strip-types src/modules/cards/lib/useKanbanDrag.test.ts
```

- `columnKeyAt(cols, x, y)` — the column under the pointer, else the horizontally nearest one (with vertical slack), so a drop in a gutter or below the columns never vanishes.
- `insertIndex(rects, y)` — before the first card whose centre is below `y`. An unmeasured rect counts as *passed*, never as a wall, so a stale ref can't pin every drop to index 0.

On drop the board patches its cached list optimistically, POSTs `deck-move`, then renumbers the rest of the target column so a later drop lands where it was aimed instead of behind every unnumbered sibling. Any failure toasts and refetches.

## Settings

The enable flag is an app-scoped config, not a list row: `IntegrationsSection.tsx` gives every app in `CONFIGURABLE_APPS` (`nsfw`, `cards`) a gear that opens its config dialog. `CardsConfigModal.tsx` holds the kanban switch; both it and `NsfwConfigModal.tsx` render inside the shared `ConfigModal.tsx` shell.

Server side, `postIntegrationsSettings()` gained a `kanban` action, and `getIntegrationsSettings()` returns the flag.

## Cross-compatibility

A kanban card in redbasic (the `hzaddons/cards` module) is an ordinary card:

- The addon reads only `iconfig cat='system' k='CARD'`, so deck/order/template rows are invisible extras it ignores.
- Core's edit path preloads *every* `iconfig` row before saving (`Zotlabs/Module/Item.php`), so editing a card there writes them back intact.
- The category renders as a chip and its `?cat=` filter works on it.

**One caveat.** Editing a card in redbasic while the channel has the `categories` feature disabled drops its categories — core rebuilds terms from the submitted `category` field alone, behind an unfiltered `delete from term where oid = …`, and the card edit form only renders that field when the feature is on. The deck survives; only board membership is lost, and re-adding the category restores it. This is core behaviour for every category on every item, and the SPA's own card POST already works around it by treating an absent `category` key as "keep what's stored".
