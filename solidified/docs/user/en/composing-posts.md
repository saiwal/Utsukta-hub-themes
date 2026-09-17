# Composing Posts

You can write a post from your HQ dashboard or from your channel wall. The composer works the same way in both places.

[IMAGE: Full post composer with toolbar, text area, and privacy picker]

## Writing Your Post

Click the text area to open the full composer. Type your post in the text area. Posts support **BBCode** formatting — the toolbar inserts tags for you, or you can type them manually.

### Formatting Toolbar

| Button | What it does | BBCode |
|--------|-------------|--------|
| **B** | Bold | `[b]text[/b]` |
| *I* | Italic | `[i]text[/i]` |
| U | Underline | `[u]text[/u]` |
| S | Strikethrough | `[s]text[/s]` |
| `< >` | Code (inline or block) | `[code]…[/code]` |
| 🔗 | Insert link | `[url=…]label[/url]` |
| 🖼 | Insert image URL | `[img]…[/img]` |
| Spoiler | Collapsible spoiler block | `[spoiler]…[/spoiler]` |

Select text first, then click a button to wrap the selection. If nothing is selected, a placeholder is inserted.

[IMAGE: Composer toolbar with button labels]

## Writing in Markdown

If you turn on **Markdown** in Settings → Features → Editor, the composer accepts
Markdown as well as BBCode. Your post is converted to BBCode when you publish it,
so everyone sees it correctly — including people on the classic Hubzilla themes
and on other networks.

### Basic syntax

| You type | You get |
|---|---|
| `**bold**` or `__bold__` | **bold** |
| `*italic*` or `_italic_` | *italic* |
| `# Heading` | a heading |
| `> quoted` | a quote |
| `- item` | a bullet list |
| `1. item` | a numbered list |
| `` `code` `` or ```` ```block``` ```` | code |
| `[label](https://example.com)` | a link |
| `![alt](https://example.com/x.png)` | an image |

### Extended syntax

| You type | You get |
|---|---|
| `~~struck~~` | struck-through text |
| `- [ ] task` / `- [x] done` | a checklist |
| `==highlight==` | highlighted text |
| `H~2~O` | subscript |
| `X^2^` | superscript |
| `see [^1]` and `[^1]: the note` | a footnote |
| a table written with `\|` pipes | a table |

Footnotes show up in the composer where you typed them — the reference as a
small superscript, the note itself set apart just below. That is on purpose: the
composer shows your text as you wrote it rather than rearranging it while you
are still writing.

### Two things Markdown cannot do

**There is no underline in Markdown.** `__text__` means **bold**, exactly like
`**text**` — that is standard Markdown everywhere, not a quirk of this theme.
For underline, use the toolbar's **U** button, which inserts `[u]…[/u]`.

The same goes for text colour, highlight colour, font, size, spoilers and
centred text: the toolbar inserts BBCode for those, and you can mix BBCode and
Markdown freely in the same post.

## Mentioning Someone

Type `@` followed by a name to mention a connection. A popup appears with matching names — select one to insert a proper mention link.

[IMAGE: Mention popup showing contact suggestions]

## Privacy Controls

The **privacy picker** (below the text area) controls who can see the post:

| Setting | Who sees it |
|---------|------------|
| **Connections** | Everyone you are connected to |
| **Public** | Anyone, including visitors and the public stream |
| **Custom** | Choose specific connections or privacy groups |
| **Myself** | Private — only you |

Click the privacy picker to change the setting. For Custom, a selector opens where you can add or remove specific people and groups.

[IMAGE: Privacy picker dropdown with options]

## Submitting

Click **Post** (or press the keyboard shortcut configured in Display Settings) to publish. A draft is auto-saved as you type so you won't lose work.

## Drafts

If you close the composer without posting, the content is saved as a draft. Access drafts from the **Drafts widget** on HQ. Click a draft to continue editing.

---

## Reacting to Posts

Every post in the stream has action buttons at the bottom:

[IMAGE: Post action bar with reaction buttons]

| Action | Description |
|--------|-------------|
| 👍 Like | Express approval. Click again to unlike. |
| 👎 Dislike | Express disapproval. Click again to remove. |
| 🔁 Repeat | Reshare the post to your followers. Click again to un-repeat. |
| 💬 Comment | Open the comment thread and write a reply. |
| ⭐ Star | Bookmark the post. Starred posts appear in the Starred filter. |
| 🗑 Delete | Delete the post (only available on your own posts). |

Like and Dislike counts are shown next to each button. Click the count to see a list of who reacted.

## Commenting

Click 💬 to expand the comment thread under a post. Type your reply in the comment input at the bottom of the thread and press the send button.

[IMAGE: Comment thread expanded with reply input]

## Editing a Post

On your own posts, a **⋯ menu** or **edit button** lets you modify the body or title. The edit is federated — connections will see the updated version.

## Resharing

Click **Repeat** to reshare a post. You can optionally add your own comment above the reshared content.

## RSVP (Events)

For event posts, instead of Like/Dislike you see:
- **Accept** — attending
- **Decline** — not attending
- **Maybe** — tentative

These are mutually exclusive — selecting one clears the others.

[IMAGE: Event post with RSVP buttons]
