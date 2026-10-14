# Jellyfin

Play music and video from your own [Jellyfin](https://jellyfin.org) media server, right inside your hub — and share what you're listening to or watching with your connections.

Jellyfin is an optional feature. Turn it on in **Settings → Integrations**; a **Jellyfin** item then appears in your navigation.

[IMAGE: Jellyfin page with the Music library and the player bar]

## Connecting a server

The first time you open Jellyfin, enter your server's address and your Jellyfin username and password, then click **Connect**.

- Your password is used once to sign in and is never stored. The sign-in is remembered in this browser only — sign in once on each device.
- Your browser talks to your Jellyfin server directly; your hub never sees your media. That means the device you're using must be able to reach the server. A server only reachable at home works while you're at home (or on your VPN).
- **Server on your home network?** Browsers protect your local network from websites, so connecting from your hub to a home-network address can be blocked. Chrome asks once: allow it. Firefox blocks sign-in silently ("Couldn't reach the server"); in `about:config` set `network.lna.blocking` to `false`, or ask whoever runs your network to let home devices use the server's public address.
- Use an **https://** address. Your hub is served over HTTPS, so browsers block a plain `http://` server (a warning appears under the address field if you type one).

### Remember on my hub

Tick **Remember on my hub** when signing in, and your other browsers — and your channel's clones on other hubs — can sign in to that server without your Jellyfin password. (Your Jellyfin server needs Quick Connect enabled, which it is by default.)

The first time, you choose a **vault passphrase**. Your sign-ins are encrypted with it in your browser before they reach your hub, so your hub — and its admin — can't read them. Each new browser asks for the passphrase once; after that it's remembered there.

- **Forget it?** It can't be recovered: **⋯ → Settings → Reset vault** deletes the saved sign-ins so you can choose a new passphrase.
- **⋯ → Forget on my hub** removes the current server; browsers already signed in stay signed in.
- **⋯ → Settings → Lock on this browser** makes this browser ask for the passphrase again.

One limit applies to any website: your hub serves the app itself, so you're trusting it not to be tampered with. The encryption keeps what's stored unreadable.

### More than one server

Open the **⋯** menu at the top right to switch between servers, or choose **Add a server**. The page title shows the name of the server you're browsing. **Sign out of …** in the same menu signs out of the current server only.

## Music and Video

The switcher at the top chooses between **Music** and **Video**.

- **Music**: Home (recently played, recently added, most played, playlists), Albums, Artists, Playlists and Favourites.
- **Video**: Home (continue watching, next up, recently added), one tab for each of your video libraries, and Favourites.

Albums, artists, and video library tabs have a **sort** control above the grid (name, date added, release date, rating and more); the arrow next to it flips the order.

Use the **search** button at the top to search your whole library.

## Listening

Click a track to play it. The **player bar** at the bottom of the page has play/pause, next, and volume; click the cover or the arrow to open the **full player**, with:

- shuffle, repeat (all or one), and a seek bar,
- **Queue** — drag tracks to reorder, × to remove, click to jump,
- **Lyrics** — when a track has them; synced lyrics follow the song. **A−/A+** change the text size,
- **Party** — listen together (see below).

Each track's **⋯** menu has **Play next**, **Add to queue**, **Add to playlist** (or create one), **Instant mix**, and **Play on** another of your devices. The ♥ adds it to your Jellyfin favourites.

Your queue and position are remembered: after a reload, press play to carry on where you were.

**Keyboard** (on the Jellyfin page): Space play/pause, ← → seek 10 seconds, Shift+← → previous/next, M mute.

A small player also appears in the right sidebar on other pages, and your phone's lock screen and headphone buttons control playback.

## Watching

Click a film to see its details and **Play** (or **Resume**); open a show to pick a season and episode. Under the video:

- **Next** moves to the next episode,
- **Subtitles** and **Audio** choose tracks (subtitles marked *burned in* restart the stream briefly),
- **Start over**, **Watch together**, the **camera** (screenshot) and **scissors** (clip) buttons.

Videos your browser can't play directly are converted by your server on the fly — the note at the end of the row says which is happening.

### Screenshots and clips

- **Screenshot** captures the current frame, with any subtitle on screen. Download it, or **Share** it in a post.
- **Clip** opens a strip: set the start and end with the playhead buttons or type times like `1:02:03.4`, preview, then **Create clip**. Clips are up to one minute. The first clip downloads the video tools (about 30 MB, once).

Shared screenshots and clips are uploaded to your hub, so everyone can see them without access to your Jellyfin.

## Sharing what you're playing

**Share** (on the player, albums, playlists, films and episodes) opens a new post with the cover and a line like "🎵 Listening to …" — edit it and choose who sees it before posting. Nothing is ever posted without you.

## Your other devices

The **devices** button at the top lists what your other Jellyfin apps (phone, TV…) are playing. From there you can pause or skip them, **Play here** to continue in this browser, or share what's on. Your Jellyfin apps can control this browser too.

## Watch and listen together

Start a party with **Watch together** under a video or **Listen together** in the full player. Everyone in the party sees and hears the same thing at the same moment: play, pause, seeking and skipping act for everybody.

- **Invite** sends a private message to the connections you choose, with a **Join the party** link.
- **Chat** shows that message thread beside the party.
- **Leave** ends your part; the party continues for the others.

Everyone in a party needs an account on the same Jellyfin server, and must be able to reach it (for a home server: be on your network or VPN). People using the official Jellyfin apps can join the same party.

## Settings

**⋯ → Settings**:

- **Show what I'm listening to on my profile** — off by default. Choose who sees a "Listening now" card on your channel and profile pages: only you, your connections, or everyone. It disappears about 20 minutes after you stop playing.
- **Daily recap reminder** — once a day, at the time you pick, a notification offers today's listening recap as a draft post.
- **Share today's / this week's recap** — make a recap post now.
