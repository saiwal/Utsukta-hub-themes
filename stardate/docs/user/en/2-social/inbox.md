# Inbox — Your Messages, Offline

Inbox is a mail-style reader for everything addressed to you: all messages, direct messages, starred posts, notices, and every folder you have filed messages into. It is the one place in Solidified that deliberately keeps a **full local copy** of your messages, so you can read them with no connection at all.

If Inbox is missing from your navigation, turn it on in **Settings → Integrations**, alongside Tools and Games.

## The section list

Sections run down the left on desktop, and appear as a list you tap into on mobile — the same arrangement as Settings.

- **Messages** — everything, newest first.
- **Direct** — private conversations only.
- **Starred** — posts you starred.
- **Notices** — notifications.
- **Your folders** — one section per folder you have filed messages into, below the divider.

Messages are grouped by age (Just now, Today, Yesterday, This week, Older), and unread ones are highlighted with a count of unread replies. Click any message to open the full thread. Scrolling to the bottom loads more.

To filter, use the **Inbox filters** panel in the right sidebar (the panel button on mobile). It works like the network's: *Show* flags (Unread, Mentions me, Following, Has attachment) each narrow the list, *Post type* matches any of Events and Polls, *From* is one connection, group or forum at a time, and *Narrow down* adds tag, language and dates. Its search box also takes typed operators — `from:alice`, `is:liked`, `in:"folder 1"`, `after:2026-01-01`. Filtering needs a connection — it runs on the server, not on your saved copy.

## Message Rules

**Rules** (at the bottom of the section list) shows everything that decides
which posts reach you and where they land, in three groups:

- **Everything I receive** — channel-wide rules that **keep** or **skip** an incoming post. They apply when a post arrives, so a change affects new posts only.
- **Per connection** — the same kind of rule set on a single connection (in its connection settings). If you see *Connection filtering is off*, these are ignored until you click **Turn on**.
- **File automatically** — auto-filing rules that move matching mail into one of your folders.

Click **Edit** or **Add rule** to open the rule builder. Choose **Match any of
these** or **all of these**, then **Add condition** for each test: body text, a
pattern, a hashtag, a mention, a category, a language, the sender's name or
address, or a date the rule runs **Only until**. **Edit as text** shows the same
rule as an expression, for advanced filters.

Auto-filing runs when the inbox loads and applies to mail that arrives from
then on. **Run on existing mail** files what you already have too — posts you
took out of a folder by hand may be filed again.

## What gets saved for offline

**Opening Inbox is what builds your offline copy.** While it is open, Solidified quietly downloads your messages in the background: first the list of every section and folder, then the messages themselves, starting with the ones you kept — starred and filed. Leave it open for a minute or two on a good connection before you expect to read offline.

Navigating away stops the download where it is; nothing continues in the background once you have left. Coming back picks up where it left off, and already-saved messages are never fetched twice.

**How much is kept:**

- **Starred and filed messages are kept indefinitely.** They are the ones you deliberately set aside, so they are never trimmed away to make room.
- Other sections keep the most recent 500 messages.
- A message is only ever removed once no section or folder still lists it.

Elsewhere in Solidified — HQ and your Network stream — nothing is downloaded ahead of time. Those simply remember what you already looked at: posts you scrolled past in your stream and threads you opened stay readable offline, at no extra cost, but they will not fetch anything in advance.

## Reading offline

When you lose connection, an **Offline** badge appears in the toolbar above the list and the message lists keep working from your saved copy.

What you can do offline:

- Browse every section and folder.
- Open and read saved messages.

What you cannot:

- **Search** — it runs on the server.
- **Load older messages** past what was saved. The list simply ends.
- **Comments** — a thread opens showing the message itself; replies appear only if that thread was opened before.
- **Post, comment, like, star or file.** Nothing is queued for later — those actions fail until you are back online.

The refresh button forces a fresh sync when you have a connection again.

## Storage

Your saved messages live in your browser's storage for this site, alongside cached images and the app itself. Clearing site data in your browser removes them; opening Inbox again rebuilds the copy.

If you have the app installed to your home screen, everything above applies exactly the same — Inbox is what makes it genuinely usable on a plane or an underground train.
