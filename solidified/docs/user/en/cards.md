# Cards

Cards are short, self-contained notes on your channel — a quote you want to keep, a link worth remembering, a definition, a scrap of writing. They live at `/cards/<nick>`, separate from your stream posts, and they federate like everything else on Hubzilla.

Unlike articles, cards have no reading order. The cards page is a pinboard, not a list.

[IMAGE: Cards board with a mix of quote, link and definition cards]

## The four kinds of card

When you write a card you pick a template, and the card is laid out for what it holds:

- **Freeform** — ordinary text. If you give it a title, the title leads and the text follows as a teaser; an untitled card is just its text.
- **Quote** — the quotation is set as a pull-quote with the attribution as a byline underneath.
- **Link** — the page title, a clickable shortened URL, your note about it, and the page's image if it has one.
- **Definition** — the term as a heading with the definition in full underneath.

The template is only a shape. Every card is stored as ordinary Hubzilla markup, so your cards still read correctly in the classic interface and on other servers.

Every card carries a **share** button and a **read full card** link, plus its categories, tags, and deck.

## Decks

A **deck** (or shelf) is an ordered group of cards — a sequence you want read in order, like a set of numbered study cards. Set a deck name and a position when you write the card.

- `/cards/<nick>/deck` lists every deck with its card count.
- `/cards/<nick>/deck/<name>` shows one deck in order, and lets the owner reorder it.
- The **Decks** widget in the right sidebar lists them all, with a shuffle button that opens a random card from that deck.

## Kanban boards

A card's deck can also be a *column*. Turn that on and your cards page gains a second view: a kanban board where each column is a deck and you drag cards between them.

### Turning it on

Go to **Settings → Integrations**, find the **Cards** row, and click its gear. Switch **Kanban board** on and save.

[IMAGE: Cards row in Integrations with its gear button and the kanban switch]

A **Board / Kanban** switcher then appears at the top of your cards page.

### How a board is put together

Two things you already have decide everything:

- **A board is a category.** A card is on the board named `kanban` because it carries `kanban` as one of its categories. A card with the category `roadmap` is on the roadmap board.
- **A column is a deck.** Within a board, the card's deck name is the column it sits in, and its deck position is where it sits in that column.

So putting a card on a board is just giving it a category and a deck — both ordinary fields in the card editor. Nothing about a kanban card is special, which is why it still reads as a normal card everywhere else.

### Using the board

[IMAGE: Kanban board with three columns and a card mid-drag]

- **Tabs** across the top switch between boards. The board you're on is in the page address, so you can bookmark or share a link straight to one.
- **Drag the grip** in a card's top-right corner to move it — to another position in its column, or to another column entirely. It's saved as you drop it.
- **+ in a column header** writes a new card already filled in with that board's category and that column's deck.
- **Add column** at the right-hand edge creates a new empty column to drag into.
- **Rename / remove** on a column: renaming moves every card in it; removing takes the column off the board and its cards drop into **Unfiled**, where you can re-file them. Nothing is deleted.
- **Unfiled** collects cards on this board that have no deck, or a deck that isn't one of its columns — so a card is never invisible.

Removing a *board* works the same way: the board disappears from the tabs, but its cards keep their category and come straight back if you add the board again.

### Visitors

Visitors see your boards too, with your columns — but only the cards they're allowed to see. A card whose privacy excludes someone simply isn't in their copy of the column. Only you can drag cards or change columns and boards.

## Right sidebar

The cards page carries the same sidebar widgets as the rest of the app:

- **Decks** — every deck, with counts and shuffle.
- **Categories** and **Tags** — click one to filter the board.
- **Card drafts** — cards you started and didn't finish (only you see this).
- **Popular cards** and **Card showcase** — optional, added from the widget picker.

## Going back to the classic interface

Your cards, decks and boards all survive in classic Hubzilla: a kanban card shows up as an ordinary card carrying its category, and its deck is preserved even if you edit it there.

One caveat — if you edit a card in the classic interface **while your channel has the "categories" feature switched off**, that editor has no category field and saving drops the card's categories, which takes it off its board. The deck is untouched, and re-adding the category puts it straight back.
