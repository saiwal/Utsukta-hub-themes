# Jellyfin module

A music and video player for the user's own Jellyfin server(s): library
browsing, a persistent music player, a video player with HLS transcoding,
remote control in both directions, watch/listen parties (SyncPlay), sharing,
screenshots and clips, and an opt-in "Listening now" card on the profile.

Code: `src/modules/jellyfin/` (the module), `src/modules/jellyfin-profile/`
(the visitor-facing profile card), `packages/spa-core/php/Api/Handlers/JellyfinNowPlaying.php`.
It is also the reference implementation of [Design-guidelines](design-guidelines).

## The one architectural rule: the browser talks to Jellyfin

There is no `/spa` proxy. A hub-side fetch of a user-typed URL would need
`ValidatesRemoteHost`'s private-range block, which rules out exactly the
home-LAN servers most people run; and streaming through PHP-FPM would pin a
worker per listener. So every Jellyfin call — REST, `<audio>`/`<video>`
streams, images, the WebSocket — goes from the browser to the server. Jellyfin
answers CORS with `Access-Control-Allow-Origin: *`, and core's default CSP
leaves `connect-src`/`media-src` open.

What does touch the hub: sharing (covers, screenshots and clips uploaded via
`wall_attach`), posting, and the profile card (the owner's browser pushes the
current track to `/spa/jellyfin-nowplaying`). Visitors never contact Jellyfin.

## Files

| File | Role |
|---|---|
| `index.ts` | Module: `/jellyfin` route, nav item, `frontendFeature` toggle (off by default), header / content / sidebar mini-player widgets |
| `widgets/NowPlayingWidget.tsx` | The global sidebar widget — a 3 KB gate that imports nothing of the player and lazy-loads `MiniPlayer.tsx` only when Jellyfin is enabled. Keep it that way: global widgets mount on every page for every local user, so anything it imports statically is downloaded by people who never use Jellyfin |
| `api.ts` | Every Jellyfin endpoint, typed; device profile; URL builders |
| `store.ts` | Servers (sessions) + the music player: queue, `<audio>`, shuffle/repeat, resume, Media Session, party hooks |
| `remote.ts` | Other sessions: polling, one WebSocket per server, commands both ways, "Play here" |
| `syncplay.ts` | Watch/listen parties on SyncPlay |
| `settings.ts`, `presence.ts`, `recap.ts`, `share.tsx` | Profile audience + daily reminder; pushing now-playing; recap posts; Share button |
| `chrome/` | `section.ts` (Music \| Video in the URL), `player-ui.ts` (full-player open state) |
| `library/` | `views.tsx` (tabs, home shelves, detail pages, `Hero`, `TextTabs`), `common.tsx` (cards, shelves, track list), `sort.ts`, `TrackMenu.tsx`, `FavoriteButton.tsx`, `mix.ts` |
| `player/` | `PlayerBar.tsx` (sticky bar), `FullPlayer.tsx` (dialog), `Queue.tsx` |
| `video/` | `VideoPlayer.tsx`, `capture.ts` (screenshot + clip), `ClipStrip.tsx`, `CaptureDialog.tsx`, `state.ts` |
| `party/PartyBar.tsx` | Start/invite/chat/leave UI (`mode="start"` / `"panel"`) |
| `ui.tsx`, `anim.ts` | `Art`, skeletons, `iconBtn`/`primaryPill`/`ghostPill`, `fmtTime`/`fmtClock`/`parseClock`; motion presets tamed for reduced motion |

## Sessions and storage

`localStorage["hz-jellyfin"]` = `{ deviceId, active, servers: JellyfinSession[] }`
— one DeviceId per browser, one entry per server, keyed by Jellyfin's
`ServerId` (entries migrated from the old single-server shape are keyed by
URL; `addServer` replaces by id *or* URL and remaps queued tracks). Server
names are refreshed from `/System/Info/Public` on load. Tokens never leave the
browser.

`localStorage["hz-jellyfin-player"]` holds the queue, index, position, volume
and repeat for resume-after-reload. A restored queue loads nothing until the
user presses play (`resumeAt`). Tracks from signed-out servers are dropped.

## Hub vault — sign-ins for other browsers and clones

Opt-in ("Remember on my hub" at sign-in), **end-to-end encrypted**: the hub
and its admin store only ciphertext. Generic pieces, meant for reuse by later
integrations (PeerTube, Matrix…):

- **`spa-core/lib/vault.ts`** — encrypts in the browser. A vault passphrase
  (one per channel, chosen by the user) → PBKDF2-SHA256, 600 000 iterations →
  AES-256-GCM; each entry `{v, iv, ct}` with AAD `"<integration>/<key>"`, so
  an entry moved to another slot fails to open. Public metadata (KDF name and
  iterations, salt, an encrypted check value to recognise a wrong passphrase)
  lives in integration `_vault` key `meta` — recorded so the KDF can move to
  Argon2id later. The derived key is cached per browser in IndexedDB as a
  **non-extractable** `CryptoKey`, keyed by the channel hash (shared by
  clones): unlock once per browser. API: `vaultStatus()` (`new` / `locked` /
  `unlocked`), `unlockVault(passphrase)` (sets it on a new vault),
  `lockVault()`, `resetVault(integrations)` (a forgotten passphrase can't be
  recovered), `vaultList<T>()`, `vaultPut()`, `vaultDelete()`.
- **`Handlers/Vault.php`** — `GET/POST /spa/vault/<integration>`, owner only
  (CSRF on writes); stores the opaque map in pconfig `spa_vault/<integration>`,
  additionally sealed with the channel key pair (keeps even the ciphertext out
  of logs — core logs whole sync packets at `LOGGER_DATA` — and dumps), synced
  to clones by the router's shutdown hook. ≤ 8 KB per entry, ≤ 32 per
  integration. Refuses to seal without a public key (core's `aes_encapsulate`
  would log the plaintext). Check: `npm run test:php -- Vault`.

The limit every web app has: the hub serves this JavaScript, so a malicious
admin could ship code that captures the passphrase as it's typed. The vault
stops an admin *reading what's stored*, not tampering with the app.

Jellyfin's use (`store.ts`): one entry per server, in a slot named by
`SHA-256("jellyfin:" + ServerId)` (the hub doesn't learn which server), holding
an **anchor** — a separate Jellyfin session ("Hubzilla (stored)", made with the
password at sign-in) never used for playback. `syncFromHub()` (once per page
load, from the mini-player) gives a browser lacking a stored server **its own**
token via Quick Connect approved by the anchor (`quickConnectNewDevice()`), so
each browser stays its own Jellyfin session (remote control and SyncPlay need
that); the browser does it, so the hub still never contacts Jellyfin. Locked
vault → `vaultLocked()` and `VaultUnlock.tsx` offers the passphrase. A
device-only sign-out lands in `hubSkip` so the next sync doesn't undo it;
"Forget on my hub" revokes the anchor and deletes the entry.

## Music player (`store.ts`)

A single module-level `<audio>` (created outside any component, so playback
survives navigation). Each queue entry is a `QItem` — the Jellyfin item plus
`srv` (which server streams it) and `qid` (a per-entry id; the same track can
be queued twice). Shuffle keeps the original order in `unshuffled` and the
current track first. Playback is reported to Jellyfin
(`/Sessions/Playing{,/Progress,/Stopped}`).

**Party hooks**: while a listening party is active, `toggle`, `seek`, `next`,
`prev`, `playAt`, `playList`, `enqueue`, `playNext`, `removeFromQueue`,
`reorderQueue`, `toggleShuffle`, `cycleRepeat`, `pause`/`resume` and the
`ended` handler route to `PartyHooks` (set by `syncplay.ts`) instead of acting.
The sync engine acts through `partyLoad` / `partySetQueue` / `partyMedia`.

## Remote (`remote.ts`)

- **Polling** `GET /Sessions` every 10 s while the tab is visible, refcounted
  by `useRemotePolling()` (the global mini-player and the header). Own sessions
  only — admins see everyone's — never this browser's DeviceId.
- **WebSocket** `/socket` per server: posts `/Sessions/Capabilities/Full`
  (`Audio`+`Video`, `SupportsMediaControl`), which makes this browser a
  controllable session and a "play on" target. Inbound `Playstate`, `Play`
  and `GeneralCommand` messages drive the store. `SessionsStart` pushes reach
  **admins only**; for them polling switches off. `UserDataChanged` triggers an
  early poll. Reconnects with backoff; `onSocketMessage()` lets other modules
  (SyncPlay) subscribe, `"__open"` included.

## Video (`video/VideoPlayer.tsx`)

`POST /Items/{id}/PlaybackInfo` with `browserDeviceProfile()` — probed with
`MediaSource.isTypeSupported` (HEVC, AV1, VP9, AC-3/E-AC-3) — decides direct
play vs an HLS transcode (h264/aac, `ts` segments). HLS plays natively on
Safari and through **hls.js/light** elsewhere (lazy chunk, runtime-cached, not
precached). Text subtitles are `<track>`s (WebVTT only — see quirks); image
subtitles and non-default audio tracks re-request PlaybackInfo with
`SubtitleStreamIndex`/`AudioStreamIndex` and `forceTranscode`, resuming at the
current position. Progress is reported with PlaySessionId/MediaSourceId; the
transcoder is freed with `DELETE /Videos/ActiveEncodings` on teardown. Next
episode: `/Shows/{id}/Episodes?StartItemId=…&Limit=2`.

## Parties (`syncplay.ts`)

Jellyfin SyncPlay. The server owns the group's queue and state; a client only
*requests* (`Pause`, `Unpause`, `Seek`, `NextItem`, `SetNewQueue`, `Queue`,
`MovePlaylistItem`, …) and acts on what is broadcast back:

- `PlayQueue` update → load the item paused at `StartPositionTicks`, then
  `POST /SyncPlay/Ready` with its `PlaylistItemId`. A new queue, or someone
  joining, auto-plays once everyone is ready.
- `Unpause` carries `When` (~1 s ahead, server time) → start then. The clock
  offset comes from `GET /GetUtcTime` (best of 5 samples by RTT), refreshed
  every 30 s along with `/SyncPlay/Ping`.
- `Seek` → group goes `Waiting` until every client reports Ready again.
- Drift while playing: ±5% `playbackRate` above 0.12 s, a seek above 1 s.
- Video items navigate to `?watch=` (via `setPartyNavigator`, set by the
  always-mounted mini-player); the player registers with `attachVideo()`, and
  its native-control events become requests (`partyIsActing()` tells our own
  play/pause/seek apart).
- Rejoin after reload from `sessionStorage["hz-jellyfin-party"]` on socket open.
- Invites are DMs (`DMComposer` with `initialTitle`/`initialBody`); the subject
  carries `#<partyCode>` so an invitee's browser can find the thread (by title
  among recent DMs) for the chat panel (`DmThread`). The join link is
  **relative** (`/jellyfin?party=…&srv=…&jf=…`) so it opens on the reader's own
  hub; core's bbcode blanks relative URLs, so it only works in the SPA.

## Screenshots and clips (`video/capture.ts`)

- Screenshot: `drawImage(video)` (CORS-clean: `crossorigin="anonymous"` + Jellyfin's
  ACAO), plus the active text cue drawn in the theme's font.
- Clip (≤ 60 s): force an HLS transcode with `AllowVideoStreamCopy=false`,
  `MaxWidth=1280`, a separate `DeviceId` (`…-clip`); fetch only the 3 s
  segments covering the clip (requesting segment *N* makes the server start
  transcoding there), concatenate (MPEG-TS concatenates), and cut with the
  shared ffmpeg worker (`src/shared/lib/ffmpeg-client.ts`) using **output-side**
  seeking (`-i in.ts -ss … -t …`), re-encoding to h264/aac MP4.
  - Stream copy is off because copied video can only be cut on the source's
    keyframes (often ~10 s apart): the "6 s" segment really began at 10 s and
    the clip came out audio-only.
  - Output seeking because input-side `-ss` on the joined MPEG-TS (absolute
    `-copyts` timestamps) landed a second late. Verified frame-exact against
    the source.

## Sharing and the profile card

`share.tsx`: the cover is fetched from Jellyfin and re-hosted on the hub via
`wall_attach` (cached per album per page load) — a Jellyfin URL would leak the
server address and fail for anyone off its network. Text is plain (works in
both bbcode and markdown composers).

`presence.ts` (mounted by the mini-player) pushes `{title, artist, album,
cover}` — a ~96 px data-URL thumbnail, kept in pconfig rather than as a photo
— on track change and pause (debounced) plus a 5-minute heartbeat; music
only. `JellyfinNowPlaying.php` stores it (`spa/jf_np_state`), gates reads by
`spa/jf_np_audience` (`off`/`self`/`connections`/`public`; connections =
approved, unblocked abook), treats > 20 min as stale, and accepts covers only
as small base64 `data:image/(jpeg|png|webp)` (no remote URLs, no SVG).
`jellyfin-profile` is its own module because `frontendFeature` is a *viewer*
opt-in: under `jellyfin`, visitors who hadn't enabled Jellyfin would never see
the card.

Check: `npm run test:php -- JellyfinNowPlaying` (sanitising + every audience
against a real connection).

## Jellyfin API quirks (verified 10.10 and 12.x)

- Put tokens in URLs as **`ApiKey`**; 12.x answers `401` to `api_key`.
  Server-built URLs (TranscodingUrl, subtitle DeliveryUrl) still embed
  `api_key` — always pass them through `withApiKey()`.
- `GET /Sessions?controllableByUserId=` returns `[]` for non-admins; use the
  unfiltered list and filter by `UserId`.
- WebSocket `SessionsStart` pushes are admin-only.
- A session is controllable only while it holds a `/socket` connection *and*
  has posted capabilities.
- Declare only **`vtt`** as an External subtitle format; listing srt/ass makes
  the server hand them over unconverted, and `<track>` shows nothing.
- `/Items` search never matches `MusicArtist` — use `/Artists?searchTerm=`.
- "Continue watching" needs runtime > 5 minutes (server default).
- Transcodes use `-copyts`: segment timestamps are absolute, not zero-based.

## Adding to it

- New Jellyfin calls go in `api.ts` through `jf()` / `itemsQuery()`;
  query keys start `["jellyfin", s.server, s.userId, …]` so a server switch
  never shows another server's cache.
- Player actions that must work in a party: add the hook to `PartyHooks` and
  route it in `syncplay.ts`.
- Labels: the `jellyfin` locale namespace (`en`/`de`/`hi`).
