# Widgets

<!-- widgets -->

The right sidebar (and a few other regions, like above content, header, footer) show **widgets** - small panels like Notifications, Connections, Categories, or Upcoming Events. Which widgets appear depends on the page you're on, but you can customize your own pages: add, remove, reorder, and configure widgets to suit you.

[IMAGE: Right sidebar with several widgets stacked]

## Entering Edit Mode

On any page you own, look for the **pencil icon** in the right sidebar header and click it. Each widget now shows a small toolbar with move, remove, and (for some widgets) settings controls, plus an **Add widget** button at the bottom of the list.

[IMAGE: Right sidebar in edit mode with per-widget toolbars and an Add widget button]

Only the owner of a page can edit its widgets - visitors and other users always see the arrangement as you left it.

## Adding a Widget

Click **Add widget** to see every widget available on the current page. Click one to drop it in at the end of the list. Eligible widgets are shown when you click onn **Add widget**.

[IMAGE: Add widget picker list]

## Removing a Widget

Click the **✕** on a widget's toolbar to take it off the page. It's not deleted - it just goes back into the Add widget list if you want it again later.

## Reordering Widgets

Use the **↑ / ↓** arrows on a widget's toolbar to move it up or down in the stack. The topmost widget renders first.

## Widgets with Multiple Instances

A few widgets can be added more than once on the same page, each with its own settings. For example, the Cart module's item card widget can be added several times, each showing a different product. When a widget supports this, it stays in the Add widget list even after you've added it once - click it again to add another copy.

Widgets that support multiple instances show a **gear icon** in their toolbar. Click it to open that instance's settings (e.g. which item to show) without affecting any other copy of the same widget.

[IMAGE: Widget toolbar with gear icon open showing a settings form]

## Pinned Widgets

A small number of widgets - like Notifications - are **pinned**: they appear on every page automatically and don't show a toolbar, since they can't be removed or reordered. Pinned widgets aren't included in the Add widget list.

## Resetting to Defaults

If you've customized a page, a **Reset to defaults** button appears below the widget list. Click it to discard your changes for that page and go back to the standard widget selection.

## Saving & Syncing

Every change (add, remove, reorder, or configure) saves immediately - there's no separate save button. Your layout is stored on the server, so it follows you across devices, and if you're viewing your own channel, visitors see the same arrangement you've set up.

<!-- widget_overview -->

## Widget overview

| Widget                | Modules                                        | Regions (slots)                            | Global | Multiple |
| --------------------- | ---------------------------------------------- | ------------------------------------------ | ------ | -------- |
| about site            | siteinfo*                                      | contentTop                                 | -      | -        |
| activity heatmap      | channel, profile, hq                           | right, gridTop                             | -      | -        |
| addons info           | siteinfo*                                      | contentTop                                 | -      | -        |
| admin info            | siteinfo*                                      | contentTop                                 | -      | -        |
| album strip           | channel, profile, photos                       | right                                      | -      | ✅       |
| albums                | photos*                                        | right                                      | -      | -        |
| archive (calendar)    | articles, channel, profile                     | right, footer                              | -      | -        |
| archive tree          | channel, profile                               | right                                      | -      | -        |
| article series        | articles                                       | right                                      | -      | -        |
| article teaser        | channel, profile, articles                     | right                                      | -      | ✅       |
| bookmarked rooms      | chat*                                          | right                                      | -      | -        |
| categories (list)     | articles, channel, profile                     | right                                      | -      | -        |
| categories (cloud)    | articles, channel                              | right, footer                              | -      | -        |
| chat room card        | channel, profile, chat                         | right                                      | -      | ✅       |
| clock                 | any                                            | right, gridTop                             | -      | ✅       |
| connections           | channel, profile                               | right                                      | -      | -        |
| contact card          | any                                            | right                                      | -      | -        |
| chronological feed    | channel                                        | contentTop                                 | -      | -        |
| direct message panel  | hq*                                            | gridTop                                    | -      | -        |
| drafts                | articles, hq, wiki, webpages, notepad, network | right                                      | -      | -        |
| embed                 | any                                            | right, gridTop, footer                     | -      | ✅       |
| event card            | channel, profile, cal                          | right                                      | -      | ✅       |
| federation            | siteinfo*                                      | contentTop                                 | -      | -        |
| filters               | network                                        | right                                      | -      | -        |
| guided tours          | help                                           | right                                      | -      | -        |
| html block            | any                                            | right, footer, gridTop, contentTop, header | -      | ✅       |
| links list            | any                                            | right, footer                              | -      | ✅       |
| menu_bar              | any                                            | header                                     | -      | ✅       |
| menu_tree             | any                                            | right, footer                              | -      | ✅       |
| navigation tree       | help*                                          | right                                      | -      | -        |
| newspaper feed        | channel                                        | contentTop                                 | -      | -        |
| notices panel         | hq                                             | gridTop                                    | -      | -        |
| notifications         | any                                            | right                                      | ✅     | -        |
| pinned chat rooms     | any                                            | right                                      | ✅     | -        |
| pomodoro              | any                                            | right, gridTop                             | -      | ✅       |
| popular posts         | channel, profile, articles                     | right                                      | -      | -        |
| popular articles      | channel, profile                               | right                                      | -      | -        |
| quick compose buttons | any                                            | gridTop                                    | -      | -        |
| quick composer        | any (hq*, network*)                            | gridTop                                    | -      | -        |
| quick note            | any (hq*)                                      | right                                      | -      | -        |
| quotes                | any                                            | right, gridTop, footer                     | -      | ✅       |
| recent posts          | hq*                                            | gridTop                                    | -      | -        |
| rss                   | any                                            | right                                      | -      | ✅       |
| saved search          | network                                        | right                                      | -      | -        |
| scheduled posts       | any (hq*)                                      | right                                      | -      | -        |
| scrapbook feed        | channel                                        | contentTop                                 | -      | -        |
| service_classes       | siteinfo*                                      | contentTop                                 | -      | -        |
| shop item card        | channel, profile, cart                         | right                                      | -      | ✅       |
| tags cloud            | articles, channel, notepad                     | right                                      | -      | -        |
| tags list             | articles, channel, notepad                     | right, footer                              | -      | -        |
| themes info           | siteinfo*                                      | contentTop                                 | -      | -        |
| timeline feed         | channel                                        | contentTop                                 | -      | -        |
| upcoming events       | hq*                                            | gridTop                                    | -      | -        |
| usage quotas          | hq                                             | gridTop                                    | -      | -        |
| weather               | any                                            | right, gridTop                             | -      | ✅       |
| wiki list             | any (wiki*)                                    | right                                      | -      | -        |

\* : _placed in this module by default_

## Widget Descriptions

<!-- about_site -->

### About site

Shows a brief description of the hub.
<!-- activity_heatmap -->

### Activity Heatmap

Displays a channels activity(posts made) as a heatmap. Activity heatmap is a visual chart that uses colour intensity to show where and when users concentrate their actions. Darker or warmer colours indicate higher activity; lighter or cooler colours show lower activity.
<!-- addons_info -->

### Addons Info

Displays a list of addons active on the hub.
<!-- admin_info -->

### Admin Info

Displays information about the administrator of the hub and how they can be contacted.
<!-- album_strip -->

### Photo Album Strip

Shows the latest thumbnails from one photo album you pick, in a small grid that links out to the full gallery. Add one per album you want to feature; place several to showcase more than one.
<!-- albums -->

### Photos

Browsable grid of a channel's recent photos and albums, with a lightbox for viewing full-size images and stepping through an album.
<!-- archive_calendar -->

### Archive (Calendar)

Alternate archive layout: a month calendar with a dot under each day that has a post. Prev/next month and prev/next year controls let you browse, and clicking a dotted day filters the stream to that single day.
<!-- archive_tree -->

### Archive

Collapsible year/month list of the channel's post history, with a post count next to each month. Selecting a month filters the stream to that period.
<!-- article_series -->

### Article Series

Lists the channel's article series with an entry count for each. Unlike category or tag filters, clicking a series takes you to its own dedicated series page rather than filtering the current list, since a series is an ordered sequence rather than a facet.
<!-- article_teaser -->

### Article Teaser

A single-article preview card showing the title, a short excerpt, and a read-more link. Add one per article you want to highlight.
<!-- bookmarked_rooms -->

### Bookmarked Rooms

Lists the chatrooms you've bookmarked for quick access, with a way to jump straight into a room or remove a bookmark.
<!-- card_deck -->

### Card Deck

Lists the channel's card decks with a count for each, drawn as a small stack. Clicking a deck opens its own ordered page; the shuffle button jumps straight to a random card from it, which is the quicker way in when the order doesn't matter.
<!-- card_showcase -->

### Card Showcase

A single card, rendered as the real thing rather than a text preview — it flips to its back on hover, or on tap where there's no pointer. Add one per card you want to highlight.
<!-- categories_list -->

### Categories (list)

Row-style list of the channel's post categories with post counts, as an alternative layout to the Categories Cloud. Clicking a category filters the stream to it.
<!-- categories_cloud -->

### Category Cloud

Tag-cloud style layout of the channel's post categories, with pill size scaled by how many posts are in each. Clicking a category filters the stream to it.
<!-- chat_room_card -->

### Chatroom Card

A single-room showcase card showing the room name and who's currently inside, with a link to join. Add one per room you want to spotlight.
<!-- clock_card -->

### Clock Card Widget

Shows the current time (and optionally date) for a timezone you choose. Add one per timezone you want to track.
<!-- connections -->

### Connections

Shows a grid of the channel's connections (with photos and names) and a link to view the full connections list.
<!-- contact_card -->

### Contact Card

A mini profile summary of the current page's channel — photo, name, location, and a connect button — reusing the same data shown in the full profile header.
<!-- chronological_feed -->

### Chronological Feed

The channel's default post stream, ordered by date, with a switcher to toggle between list, feed, and masonry layouts. This is the standard feed widget shown on a channel page unless the Newspaper, Timeline, or Scrapbook layout is used instead.
<!-- direct_message_panel -->

### Direct Message Panel

Shows the private (direct) message conversations in your dashboard message feed, separate from the general activity feed and site notices.
<!-- drafts -->

### Drafts

Lists saved drafts across post, article, webpage, wiki, and note composers in one place, letting you resume or discard any of them.
<!-- embed -->

### Embed Widget

Embeds an external http(s) page in a sandboxed iframe of a height you set, so the embedded content can't script or navigate the parent page.
<!-- event_card -->

### Event Card

A single-event showcase card with date, time, and location for one upcoming calendar event. Add one per event you want to highlight.
<!-- federation -->

### Federation

Shows which federation protocol(s) power the hub and links to the underlying project, so visitors can see how the hub connects to the wider network.
<!-- filters -->

### Stream Filters

Sidebar filter panel for the network stream — narrow posts by connection, group, conversation type, tag, date range, and more, with a clear-all option.
<!-- guided_tours -->

### Guided Tours

Lists the interactive, step-by-step tours available in the app. Click **Start** next to a tour to launch it — the app navigates to the right page if needed, then walks you through it one spotlighted element at a time with Back/Next controls. Click the **✕** on a step, or click outside it, to stop the tour early.
<!-- html_block -->

### HTML Block Widget

Shows a block of raw HTML you write yourself. Useful for embedding a badge, custom links, or anything else the built-in widgets don't cover.
<!-- link_list -->

### Link List Widget

A short, hand-curated list of links with labels - handy for a "resources" or "elsewhere" box in your sidebar.
<!-- menu_bar -->

### Menu Bar

Horizontal navigation bar built from one of your saved menus, meant for the top-of-page slot. Submenu items open as click-to-open dropdowns on desktop and collapse into a hamburger menu on narrow screens.
<!-- menu_tree -->

### Menu List

Vertical, multi-level menu card built from one of your saved menus, meant for the sidebar. Submenu items expand as an indented accordion.
<!-- navigation_tree -->

### Navigation

Collapsible table-of-contents tree for browsing documentation sections and pages, highlighting your current location as you move through the docs.
<!-- newspaper_feed -->

### Newspaper Feed

Displays the channel's posts grouped into category sections, each showing its latest few posts — like a newspaper front page laid out by topic instead of a single chronological list.
<!-- notices_panel -->

### Notices Panel

Shows system and site notifications in your dashboard message feed, kept separate from direct messages and the general activity feed.
<!-- notifications -->

### Notifications

Live feed of your notifications (mentions, likes, comments, connection requests, and more), with a badge count and quick access to mark items read.
<!-- pinned_chatrooms -->

### Pinned Chat

Keeps one or more chatrooms open and pinned in an accordion panel on the page, so you can follow and post to them without leaving what you're doing.
<!-- pomodoro -->

### Pomodoro Widget

A simple work/break timer for the page owner. Since it's a personal productivity tool rather than page content, it's never shown to visitors.
<!-- popular_posts -->

### Popular Posts

Lists the channel's posts with the most engagement (likes and comments), as a way for visitors to find its most-discussed content.
<!-- popular_articles -->

### Popular Articles

Lists the channel's articles with the most engagement, surfacing the most-read or most-discussed writing.
<!-- popular_cards -->

### Popular Cards

Lists the channel's cards with the most engagement, surfacing the ones people have reacted to or discussed most.
<!-- quick_compose_btns -->

### Quick Compose

A row of quick-launch buttons for starting a new post, direct message, webpage, wiki page, or article — each button only appears if its corresponding app is installed.
<!-- quick_composer -->

### Post Composer

An always-available post composer box for the dashboard, letting you publish a new post without opening a separate compose screen.
<!-- quick_note -->

### Quick Note

A small always-on note field for jotting something down quickly without opening the full Notepad app.
<!-- quotes -->

### Quote of the Day

Displays a quote of the day, the same for everyone on a given date, with a shuffle button to see a random one instead.
<!-- recet_posts -->

### Recent Posts

Shows a channel's most recent posts pulled in from a remote connection, giving visitors a quick look at their latest activity without leaving the page.
<!-- rss -->

### RSS Feed Widget

Displays recent items from an RSS or Atom feed you configure. Add several copies to follow more than one feed.
<!-- saved_searches -->

### Saved Searches

Lists network stream searches you've saved, so you can re-run a filtered search with one click and remove ones you no longer need.
<!-- scheduled_posts -->

### Scheduled Posts

Shows posts you've queued for delayed publishing, with their scheduled publish time. The widget stays hidden if nothing is currently scheduled.
<!-- scrapbook_feed -->

### Scrapbook Feed

Displays the channel's posts as an image-led masonry scrapbook, pulling out the first photo from each post for a visual, Pinterest-style layout.
<!-- service_classes -->

### Service Classes

Displays the hub's available service classes and their storage, channel, and connection limits.
<!-- shop_item_card -->

### Shop Item Card

A showcase card for a single item from the channel's shop, with an add-to-cart button. Add one per item you want to feature.
<!-- tags_cloud -->

### Tags

Tag-cloud layout of the channel's post tags, with pill size scaled by how often each tag is used. Clicking a tag filters the stream to it.
<!-- tags_list -->

### Tags (list)

Row-style list of the channel's post tags with counts and mini usage bars, as an alternative layout to the Tags cloud.
<!-- timeline_feed -->

### Timeline Feed

Displays the channel's posts as a vertical timeline, grouping and ordering them chronologically down the page.
<!-- upcoming_events -->

### Upcoming Events

Lists your events for the next 30 days, with quick access to view details or create a new event.
<!-- usage_quotas -->

### Usage & Quotas

Shows your account's resource usage at a glance — storage, channels, connections, and other service-class limits — with a link to the full Account settings page for more detail.
<!-- weather -->

### Weather

Shows current conditions for a location you set, including temperature, wind, and a condition icon, in your choice of Celsius or Fahrenheit.
<!-- wiki_list -->

### Wiki List

Lists the channel's wikis that you have permission to view, linking into each one. Hides itself entirely if you can't see any of the channel's wikis.
