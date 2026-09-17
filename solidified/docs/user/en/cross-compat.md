# Coming from other php themes (redbasic, adminlte, etc.)

There are certain points you need to be aware of if you are a long time user of hubzilla and are transitioning your channel from a php based theme (Redbasic, Adminlte, etc.) to solidified. Certain features are cross compatible and will be carried in seamlessly, however certain features are not compatible and you must be aware of them.


## Posts you write here, read elsewhere

Everything you post is stored as BBCode, whatever you typed it in. If you write
in Markdown, it is converted when you publish. So a post written here reads
correctly in Redbasic, in any other theme on your hub, and on other networks —
there is no "solidified-only" post format.

A few specifics:

- **Bold, italic, underline, strikethrough, highlight, colour, size, font,
  quotes, code, spoilers, tables, lists and checklists** all carry over exactly.
- **Images and files** you paste or drag into the composer are uploaded to your
  cloud storage and inserted as links, so they load for everyone. (Some editors
  embed an image directly into the text instead — that does not render on the
  classic themes, and this composer never does it.)
- **Footnotes** written in Markdown carry over with one rough edge: the note
  text appears as a numbered list at the end of the post, but the little
  jump-to-note links do not work outside this theme. Hubzilla's BBCode has no
  way to mark a spot in a post to jump to, so there is nothing to convert them
  into. The text is never lost.

## Posts written elsewhere, read here

Posts composed in Redbasic or another theme are plain BBCode and display
normally here. A post you wrote in Markdown and then edit from a classic theme
loses the Markdown source — from then on it is edited as BBCode.
