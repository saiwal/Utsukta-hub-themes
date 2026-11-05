# Links and graph

Link your wiki pages, articles, cards, notes and posts to each other, and Solidified keeps track. Every page shows what links to it, and the **Graph** draws a map of how all your writing connects.

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

The link goes to whichever page has that title: a wiki page first, then an article or card. Capital letters don't matter. Inside a wiki, `[[Title]]` looks in the same wiki first, as Hubzilla's wikis always have.

If nothing has that title yet, clicking the link shows a "no page called … yet" message, and the Graph shows it as a grey, outlined dot. That's a handy reminder of pages you meant to write.

### By pasting a link

Paste the address of any of your own wiki pages, articles, cards or posts, as a plain URL or with the link button in the editor. Both count.

Links to other people's channels, or to other websites, don't count. The graph is about your own writing.

## "Linked from"

On a wiki page, article, card or post, the **Linked from** widget in the right sidebar lists everything that links to it. Click an entry to go there. The coloured dot tells you what kind of item it is. The widget is hidden when nothing links to the page.

You can move or remove it like any other widget (see [Widgets](widgets)), and also place it in a page's footer.

## The Graph

The Graph is an optional feature. Switch it on in **Settings → Integrations**; it then appears in your menu and as a **Graph** tab on your channel. Open **Graph** from the menu, or click the **Graph** link at the bottom of the *Linked from* widget. You'll see a map of your writing:

- Each **dot** is a wiki page, article, card, note or post, coloured by kind. The bigger the dot, the more links it has.
- Each **line** is a link between two of them.
- Wiki pages, articles, cards and notes always appear, even with no links yet. Posts appear only when they link to something or something links to them, and disappear when every link they have goes to a kind you've hidden.
- When only a few dots are showing, every one is named. In a bigger graph only the most-linked are, so the names don't pile up. Zoom in or hover to see the rest.

| To… | Do this |
|-----|---------|
| See an item's details | Click its dot. A small panel shows what it links to and what links to it; click a name there to jump to that dot, or **Open** to go to the item. **Esc** or **×** closes it. |
| See an item's neighbours | Hover over its dot. Its links light up, everything else fades, and its name appears just above the dot. |
| Move things around | Drag a dot |
| Pan | Drag the empty background |
| Zoom | Scroll the mouse wheel. Names appear for every dot when you zoom in. |
| See everything | Click **Fit all** (top right of the map) to zoom so every dot fits |
| Start over | Click **Reset view**, next to Fit all |
| Show only some kinds | Click the coloured chips above the map to switch kinds on and off. Your choice is remembered in this browser. |
| Find something | Type in **Find in graph…**. Matching dots stay bright and the rest fade. |

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
