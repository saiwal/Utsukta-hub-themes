# Links and graph

Link your wiki pages, articles, cards, webpages, notes and posts to each other, and Solidified keeps track. Every page shows what links to it, and the **Graph** draws a map of how all your writing connects.

## Making a link

There are two ways to link one piece of your writing to another.

### By title: `[[double brackets]]`

Put a title in double square brackets anywhere in a post, note, article, card or wiki page:

```
I wrote more about this in [[Sourdough starter]].
```

To show different words from the title, add them after a `|`:

```
See [[Sourdough starter|my starter notes]].
```

The link goes to whichever page has that title: a wiki page first, then an article, card or webpage. Capital letters don't matter. Inside a wiki, `[[Title]]` looks in the same wiki first, as Hubzilla's wikis always have.

To be precise, or to reach something a plain title can't, name what you mean:

| Write | Links to |
|-------|----------|
| `[[Title]]` | A page in this wiki (when you're in one), otherwise any wiki page, article, card or webpage with that title |
| `[[Other wiki/Page]]` | The page *Page* in the wiki *Other wiki* |
| `[[article:Title]]` | Your article with that title, or that link name (slug), e.g. `[[article:homelab-overview]]` |
| `[[card:Title]]` | Your card with that title or link name |
| `[[page:Title]]` | Your webpage with that title or page link, e.g. `[[page:about/team]]` |

All of them take a label too: `[[article:homelab-overview|the blueprint]]`. A link to an article or card shows just its title, without the `article:` part.

These work everywhere: in wiki pages, articles, cards, notes, webpages and posts.

**In posts and comments**, a `[[ ]]` that matches something is turned into an ordinary full link (`https://your-hub/…`) when you save. Posts are sent to other hubs, which can't look up your titles, so this keeps the link working for everyone, including people using classic Hubzilla. A `[[ ]]` that matches nothing yet is left as you wrote it. Links to notes aren't converted, since nobody else can open your notes.

If nothing has that title yet, clicking the link shows a "no page called … yet" message, and the Graph can show it as a *Not yet written* item (see *Drafts and Not yet written* below). That's a handy reminder of pages you meant to write.

### By pasting a link

Paste the address of any of your own wiki pages, articles, cards, webpages or posts, as a plain URL or with the link button in the editor. Both count.

In **posts**, paste the full address (`https://your-hub/articles/…`): a post travels to other hubs, where a short `/articles/…` address would point at *their* site. In wiki pages, articles and cards, which only appear on your hub, a short address is fine.

Links to other people's channels, or to other websites, don't count. The graph is about your own writing.

## "Linked from"

On a wiki page, article, card or post, the **Linked from** widget in the right sidebar lists everything that links to it. Click an entry to go there. The shape next to it tells you what kind of item it is (see *Shapes* below). The widget is hidden when nothing links to the page.

You can move or remove it like any other widget (see [Widgets](widgets)), and also place it in a page's footer.

## The Graph

The Graph is an optional feature. Switch it on in **Settings → Integrations**; it then appears in your menu and as a **Graph** tab on your channel. Open **Graph** from the menu, or click the **Graph** link at the bottom of the *Linked from* widget. You'll see a map of your writing:

- Each **shape** is a wiki page, article, card, webpage, note or post (see *Shapes* below). The bigger the shape, the more links it has.
- Each **line** is a link between two of them.
- Switch on **Hashtags** or **Categories** in the *Graph filters* panel to see what your writing shares: each hashtag or category becomes its own shape, with a dashed line to every item that carries it, so related items gather around it. A hashtag or category on only one item isn't shown, since it connects nothing. Click one to list its items, or **Open** to see your channel's posts filtered by it.
- Wiki pages, articles, cards, webpages, notes and your 500 most recent posts always appear, even with no links yet. Older posts appear when they link to something or something links to them.
- Switch off the **Unlinked posts** chip to hide posts that have no links (counting only links to kinds you're showing, and hashtags or categories while those are on).
- When only a few items are showing, every one is named. In a bigger graph only the most-linked are, so the names don't pile up. Zoom in or hover to see the rest.

| To… | Do this |
|-----|---------|
| See an item's details | Click it. A small panel shows what it links to and what links to it; click a name there to jump to it, or **Open** to go to the item. **Esc** or **×** closes it. |
| See an item's neighbours | Hover over it. Its links light up, everything else fades, and its name appears just above it. |
| Move things around | Drag an item |
| Pan | Drag the empty background |
| Zoom | Scroll the mouse wheel. Names appear for every item when you zoom in. |
| See everything | Click **Fit all** (top right of the map) to zoom so everything fits |
| Start over | Click **Reset view**, next to Fit all |
| Show only some kinds | Use the **Graph filters** panel in the right sidebar (on a phone, open it with **Panel** in the bottom bar). Click a chip to switch a kind on or off; under Hashtags and Categories, **Choose which** lets you hide single ones (a tag on everything, say). Your choices are remembered in this browser. *Not yet written*, Hashtags and Categories start switched off. |
| Find something | Type in **Find in graph…**. Matching items stay bright and the rest fade. |

### Shapes

Each kind of item has its own shape, and its own colour too:

| Shape | Kind |
|-------|------|
| Circle | Post |
| Square | Article |
| Diamond | Card |
| Hexagon | Webpage |
| Triangle | Note |
| Star | Wiki page |
| Dashed grey ring | Not yet written |
| Plus | Hashtag |
| Pentagon | Category |

A **filled** shape is published. A **hollow** shape (just the outline) is a draft.

### Drafts and Not yet written

These two can look alike, but they are different things.

- A **draft** is a real item you've started and saved but not published yet: a post, article, card, webpage, note or wiki page. It has a title and text, so any links in it show up as lines going *out* from it. Drafts are yours alone: they appear only on your own graph, and only when you switch on **Show my drafts** in the Graph filters panel (it starts off). Visitors never see them, and they never appear in *Linked from*. **Open** takes you to the page where that kind of draft is listed, e.g. your Articles page for an article draft.
- **Not yet written** isn't an item at all. It's a title that something links to with `[[Title]]` when nothing with that title exists yet. It has no text, so lines only ever come *into* it, and there's nothing to open. As soon as you write something with that title, it turns into that item.

For example, if a draft article *Brewing notes* contains `[[Fermentation]]` and you have nothing called *Fermentation*, the graph shows a hollow square (the draft) with a line to a dashed grey ring (*Fermentation*, not yet written). A *Not yet written* item can come from published writing too, not just drafts.

## What visitors see

While the Graph is switched on, other people can open it from the tab on your channel (or at `/graph/<your channel name>`) and see the *Linked from* widget on your pages, but only for things they're already allowed to see:

- Your **notes** are never shown to anyone else.
- A private article, card, wiki page or post appears only to the people it's shared with.
- A link appears only when the visitor can see both ends of it.

## Good to know

- A new link appears as soon as you save. If you edit in classic Hubzilla instead of Solidified, it can take up to 15 minutes to show up.
- `[[i]]`-style text inside old BBCode lists isn't treated as a link: titles need at least two characters and can't start with `/`.
- Sharing or quoting one of your own posts inside another counts as a link between them.

## Related posts

Separately from links, when you open a post (as a popup, docked at the side, or in the full view), a short **Related posts** list may appear at the bottom: up to three posts that share hashtags or categories with it. If nothing is related, nothing is shown. In the full view it sits under the post, with the comments in their own column.

## Related

- [Search and jump](search): find any of these items by name or text
- [Wiki](wiki), [Articles](articles), [Cards](cards), [Notepad](notepad)
