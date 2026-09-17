# Markdown

Markdown is an **input layer**, not a storage format. A post or comment written
in Markdown is converted to bbcode before it is stored, so `item.mimetype` on a
saved post is always `text/bbcode` and classic Hubzilla, every other theme and
every federated receiver see an ordinary bbcode body.

That means there are two converters, and the whole feature rests on them
agreeing:

| | Where | What it does |
|---|---|---|
| Composer | `marked` (browser) | renders what you type, live |
| Save | `ContentTypes::toBbcode()` (PHP) | converts the body to bbcode, once |

If they disagree, the composer shows something the posted item does not — which
is the bug every rule below exists to prevent.

## The pipeline

```
source tab text
  → protectBbcode()          lift [img]/[zmg]/[card]/[attachment] out (markdownProtect.ts)
  → marked.parse()           + markedExtended.ts
  → HTML                     the WYSIWYG surface
  → markdownTurndown         back to markdown when the surface re-serializes
                             ────────── save ──────────
  → ContentTypes::gfmToBbcode()   the syntax MarkdownExtra doesn't know
  → markdown_to_bb()              MarkdownExtra → HTML → html2bbcode
  → item.body (bbcode)
```

A Markdown body still carries bbcode — the attachment bar inserts `[img]`/
`[zmg]`, the card picker inserts `[card=<id>]`, the submit path appends
`[attachment]` — so `markdownProtect.ts` lifts every such tag out before
`marked` sees it and re-emits it as a non-editable embed carrying its own
source. Anything bbcode passes through `markdown_to_bb()` untouched, which is
why "translate to bbcode first" is the standard way to add a construct.

The Markdown you actually typed is kept beside the item (`iconfig` cat `spa`,
key `md_source`, with `md_hash` guarding staleness) so re-opening a post for
editing gives you your source back rather than the converted bbcode. See
`ContentTypes::rememberMarkdown()`.

## Which syntax is handled where

| Construct | Composer | Save |
|---|---|---|
| Headings, emphasis, lists, blockquotes, links, images | marked | MarkdownExtra |
| Tables, fenced code, autolinks | marked (GFM) | MarkdownExtra |
| `~~strikethrough~~` | marked (GFM) | `gfmToBbcode` → `[s]` |
| Task lists `- [ ]` | marked (GFM) | `gfmToBbcode` → `[checklist]` |
| `==highlight==` | `markedExtended.ts` | `gfmToBbcode` → `[mark]` |
| `~subscript~` | `markedExtended.ts` | `gfmToBbcode` → `[sub]` |
| `^superscript^` | `markedExtended.ts` | `gfmToBbcode` → `[sup]` |
| Footnotes `[^1]` | `markedExtended.ts` | MarkdownExtra |
| Definition lists, heading IDs | *literal in the composer* | MarkdownExtra |

`[mark]`, `[sub]` and `[sup]` are core bbcode tags (`include/bbcode.php` 1425,
1409, 1406), which is the only reason those three can be supported at all: a
construct with no bbcode equivalent has nowhere to go at save time.

## Footnotes in the composer

MarkdownExtra expands footnotes on **save**, so until this was added a footnote
sat in the composer as literal `[^1]` text and only appeared once posted.
`markedExtended.ts` renders both halves in place:

- `[^1]` → a superscript label
- `[^1]: the note` → a set-apart aside, where it was typed

Deliberately *not* what MarkdownExtra does (collect the definitions at the
bottom and renumber them): a composer is not a page, and moving the author's
text under them while they are writing it is not a preview.

Both halves are emitted as `data-bb-raw` embeds — the same non-editable carrier
`markdownProtect` uses for bbcode — so `markdownTurndown`'s `bbraw` rule hands
the source back byte for byte when the surface re-serializes. Without that the
reference comes back as a bare `1` and the footnote dissolves as you type.

## Traps

**`__text__` is bold, not underline.** That is CommonMark (`__` and `**` are the
same tag) and MarkdownExtra agrees. Markdown has no underline in any flavour, so
the toolbar's underline button emits `[u]…[/u]` even in Markdown mode — the same
rule every construct Markdown cannot spell follows (colour, font, size, spoiler,
centre, lettered lists). Do not "fix" this with an extension: `markdown_to_bb()`
would still render `__x__` as bold everywhere else on the hub and over
federation.

**Strikethrough is consumed before subscript.** `~~x~~` and `~x~` share a
delimiter. Both converters run the `~~` rule first, so a surviving single `~`
can only be a subscript. Changing that order breaks strikethrough.

**The single-character marks reject whitespace and brackets.** Without the
whitespace rule, `a ~ b ~ c` and `2 ^ 3 = 8` in running prose become subscripts
and superscripts. Without the bracket rule, two adjacent footnote references
(`[^a][^b]`) pair their carets into a superscript and MarkdownExtra never sees
the footnotes.

**Every rule skips code spans.** The regexes carry a leading
`(```…```|`…`)` alternative whose match is returned untouched; the replacement
only runs for the second branch.

**Turndown drops what it has no rule for.** A construct rendered to an element
with no matching `markdownTurndown` rule vanishes the first time the WYSIWYG
surface re-serializes — which is on every blur. Adding a renderer without adding
the reverse rule is how `~~` used to disappear.

**`<mark>` comes back as `[mark]`, not `==`.** The highlight button emits a
*coloured* `<mark>`, which Markdown cannot spell, so it round-trips through
bbcode. A plain `==x==` follows it for consistency.

## Compatibility with classic Hubzilla

Nothing new is stored, so a Markdown post is an ordinary bbcode post
everywhere else. `[mark]`, `[sub]`, `[sup]`, `[s]`, `[checklist]`, `[table]` and
`[code]` all render in redbasic and federate normally.

The exception is **footnotes**: `html2bbcode` maps `<ol>` to `[list=1]`
(`html2bbcode.php:130`) but has no `<sup>` rule and drops element ids, and core
bbcode has no `[anchor]` tag to emit instead. So in classic a footnote reads as
a small link plus a numbered list at the end — all the text is there, the
jump-to-note anchors are not. Fixing that needs a core patch, not a theme
change.

## Checks

```bash
node --experimental-strip-types src/shared/editor/core/markedExtended.test.ts
node --experimental-strip-types src/shared/editor/core/markdown-roundtrip.test.ts
php packages/spa-core/php/Api/ContentTypes.test.php
```

The first two pin the composer dialect (including the `~~`/`~` and `[^a][^b]`
collisions); the third pins the same dialect on the save path. A construct added
to one side and not the other is exactly what they catch.

## Files

- `src/shared/editor/core/markedExtended.ts` — the extensions, registered on the shared `marked` singleton
- `src/shared/editor/core/sourceToHtml.ts` — Markdown → editor HTML (imports the above for its side effect)
- `src/shared/editor/core/markdownTurndown.ts` — editor HTML → Markdown
- `src/shared/editor/core/markdownProtect.ts` — bbcode lifting and the raw-embed encoding
- `packages/spa-core/php/Api/ContentTypes.php` — `toBbcode()`, `gfmToBbcode()`, and the remembered Markdown source
