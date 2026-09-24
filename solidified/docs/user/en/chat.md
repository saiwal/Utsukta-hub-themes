# Chat

The Chat app (`/chat/<nick>`) provides real-time chatrooms attached to a channel. A channel can host multiple chatrooms, each with its own topic and access settings.

[IMAGE: Chat rooms list page]

## Chatroom List

The main Chat page shows all chatrooms available on this channel:
- Room name
- Description (if set)
- Number of current participants (if visible)

Click a room name to enter it.

[IMAGE: Chatroom list with room names and descriptions]

## Inside a Chatroom

Once inside a room, you see:
- **Message history** — scrollable log of recent messages
- **Message input** — at the bottom, type and send
- **Participant list** — who else is in the room (if shown)

[IMAGE: Chatroom view with message history, input field, and participant list]

## Sending a Message

Type in the message input at the bottom and press **Enter** (or click the send button) to post your message. Messages appear immediately for everyone in the room.

## New-Message Alerts

Each chat window has a bell button in its title bar that cycles through three modes for that room:
- **Sound** — a short chime when someone else posts
- **Push** — a browser notification (your browser asks for permission the first time)
- **Silent** — no alert (the default)

Alerts only fire while you aren't looking at the room — the tab is in the background, the browser isn't focused, or the window is minimised — and only while the chat window is open. Your choice is remembered per room, in this browser.

## Unread Rooms

A room with messages you haven't seen yet gets a small coloured dot next to its name — in the Chat page's room list, the Chatrooms widget and the Bookmarked Rooms widget. Opening the room clears it. Your own messages never count as unread.

The **Chat** item in the navigation also shows how many of *your own* rooms have unread messages. It checks about once a minute while the tab is visible, so a new message can take up to a minute to show.

Unread state is kept in your browser: reading a room on your phone won't clear the dot on your laptop. Rooms you had never opened before this feature arrived start out as read.

## Leaving a Room

Navigate away from the chatroom page to leave. You can return at any time and read recent history.

## Visiting Someone Else's Chat

Navigate to `/chat/<their-nick>` to see the public chatrooms on another channel. You can join and participate if you have permission.

> **Note:** The Chat app must be installed on the channel. The room owner can restrict access to specific connections or groups.

## Invitations and Rooms on Other Hubs

When the owner creates a room restricted to specific people, they can tick **Notify invited members** — each invitee gets a private post with a link to the room. The link logs you in on the owner's hub when you follow it, so the room recognises you even if your channel lives elsewhere.

To keep the room for later, click the bookmark icon next to the room link (**Bookmark this link**): it is saved straight into your **Bookmarked Rooms**. (On an older invite the saved bookmark is titled "Join here" — rename it from the Bookmarks page.) You can also open the room and choose **Bookmark this room** from its menu.

A chat window here can only join rooms on your own hub. A bookmarked room on another hub opens on that hub in a new tab, logged in as you — straight into the room, full-page, if that hub also runs this interface.

Bookmarked rooms on another hub get an unread dot too, and count toward the Chat badge, **if that hub also runs this interface**: bookmarking the room asks the owner's hub to tell yours when someone posts. It does so at most once every 5 minutes per room, and not while you're in the room. Clicking the bookmark clears the dot. Rooms on a hub running classic Hubzilla only never get a dot.

For such a room you can also get a **push notification**: click the bell on its row in the Bookmarked Rooms widget. The first time, your browser asks for notification permission. Push is off by default, set per room, and follows the same 5-minute limit. Tapping the notification opens the room on its hub, logged in as you.

## Bookmarked Rooms Widget

Lists chatrooms you've bookmarked, with a quick link to jump back into any of them. Rooms show an unread dot (on another hub, only if it also runs this interface); rooms on other hubs open there in a new tab. Only visible to you, not visitors.

## Room Card Widget

An opt-in showcase card that highlights one specific chatroom you choose — its name and who's currently inside, with a join link. Add it from the widget picker, then use its gear icon to pick the room; add several copies to feature more than one room.

## Pinned Rooms Widget

A small, always-on widget that follows you to every page, not just Chat. Pin a room to keep it here as a collapsible mini-chat panel — expand it to read recent messages and send new ones without leaving the page you're on. It's pinned, so it has no toolbar and can't be removed or reordered from the widget picker.
