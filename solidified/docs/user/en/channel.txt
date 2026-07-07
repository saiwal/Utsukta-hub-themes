# Channel Pages

A **channel** is a Hubzilla identity — your public-facing profile and wall. Every user has at least one channel. Channels can represent people, projects, communities, or anything else.

## Visiting a Channel

Navigate to `/channel/<nick>` (e.g. `/channel/alice`). You see:

- The channel's **profile header** (name, photo, description)
- A feed of that channel's **public posts**
- The channel's **tabs** (Photos, Articles, Wiki, etc.) if enabled

[IMAGE: Channel page with profile header and post feed]

## Profile Header

The profile header shows:
- **Cover photo** (banner image)
- **Avatar** (profile photo)
- **Display name** and channel address
- **Short description**
- **Follow / Connect button** (if you are not already connected)

[IMAGE: Profile header with avatar, cover, name, and connect button]

## Channel Tabs

At the top of a channel's content area you may see tabs for installed apps:

- **Wall** — the channel's posts
- **Photos** — photo albums
- **Articles** — published articles
- **Wiki** — collaborative wiki pages
- **Files** — shared files
- **Calendar** — events

Which tabs appear depends on which apps the channel owner has installed and made public.

[IMAGE: Channel tabs navigation bar]

## Viewing Your Own Channel

When you visit your own channel (`/channel/<your-nick>`), you can:
- See exactly what visitors see
- Access your channel-specific settings and tabs
- The post composer may appear if you have configured it on your wall

## Profile Detail

Click the profile header or a dedicated **About** link to see the full profile:
- Hometown
- Homepage
- Gender / birthday (if shared)
- About text
- Keywords / interests

[IMAGE: Full profile detail page]

## Following / Connecting

If you are logged in and visiting someone else's channel:
- Click **Follow** or **Connect** to send a connection request.
- Once accepted, their posts will appear in your Network stream.

Connections can be organised into **Privacy Groups** — see [connections.txt](./connections).

## Connections Widget

Shows a handful of the channel's connections as avatars, with a link to see the full list. Appears on both the Channel and Profile pages.

## Popular Posts Widget

A short list of the channel's most-liked or most-commented posts, so visitors can jump straight to the highlights.

## Categories Widget

Lists the categories used on this channel's posts. Click one to filter the post feed down to that category; click it again to clear the filter.

## Tags Widget

A tag cloud of hashtags used across this channel's posts — sized by how often each tag appears. Click a tag to filter the feed to it.

## Archive Widget

A collapsible year/month list of the channel's post history. Click a month to filter the feed to posts from that month.

## Pinned Post Widget

An opt-in showcase card that highlights one specific post you choose, with a link to view it. Not shown by default — add it from the widget picker, then use its gear icon to pick which post to feature. Add several copies to pin more than one post.

## Tag List Widget

An alternate, plain-list layout for the Tags widget, for pages where a cloud layout doesn't fit well. Opt-in only — not shown by default even where the regular Tags widget is.

## Category Cloud Widget

An alternate cloud layout for the Categories widget. Opt-in only, offered as a picker alternative to the default list layout.

## Archive Grid Widget

An alternate calendar-grid layout for the Archive widget, showing months as a grid instead of a collapsible list. Opt-in only.
