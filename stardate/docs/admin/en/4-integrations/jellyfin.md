# Jellyfin

The SPA's Jellyfin player is a per-user feature: each user turns it on in **Settings → Integrations** and signs in to their own Jellyfin server. There is no hub-wide setting, and nothing to install on the hub beyond the theme.

## How it reaches the media server

Users' browsers talk to Jellyfin **directly**. The hub never proxies or fetches from a Jellyfin server — so there is no outbound traffic from the hub, no server-side request forgery surface, and media never passes through PHP. Jellyfin tokens stay in the user's browser (localStorage).

Consequences worth knowing when supporting users:

- The user's device must be able to reach their Jellyfin server. A home-only server works only on that network or over a VPN.
- The server must be served over **HTTPS** with a certificate the browser trusts — the hub is HTTPS, and browsers block `http://` requests from it.
- Jellyfin sends permissive CORS headers by default; no Jellyfin configuration is needed for browsing and playback.
- **Local Network Access.** When the Jellyfin address resolves to a private IP (a home server, or *split DNS* that gives LAN clients the internal address), browsers treat the public hub reaching it as a local-network request. Chrome asks once to allow "devices on your local network"; it must be allowed. Firefox (`network.lna.*`, on by default) lets plain image/stream requests through but **cancels CORS preflights** — sign-in and the devices list fail with "CORS request did not succeed. Status code: (null)" while the server itself answers fine. The `Access-Control-Allow-Private-Network` header does not help Firefox. Workarounds: give LAN clients the public address too (drop the split-DNS override for that host), reach the server over a VPN with a public-resolving name, or the user sets `network.lna.blocking` to `false`.

## Content Security Policy

If **content_security_policy** is enabled in the site configuration, the policy the hub sends must allow the users' Jellyfin hosts for `connect-src`, `media-src` and `img-src` (and `wss:` for live updates). Hubzilla builds the header through the `content_security_policy` hook; with the default (CSP off) nothing is needed.

## What the hub stores

| Data | Where | Why |
|---|---|---|
| Profile "Listening now" audience | pconfig `spa` / `jf_np_audience` (`off`, `self`, `connections`, `public`) | Off by default. Set by the user in the player's settings. |
| Current track for that card | pconfig `spa` / `jf_np_state` (title, artist, album, a small cover thumbnail as a data URL, timestamp) | Pushed by the user's browser while playing; treated as stale after 20 minutes; deleted when the audience is set to off. |
| Shared covers and screenshots | the user's photo albums (Wall Photos) | Uploaded through `wall_attach` when the user shares. |
| "Remember on my hub" sign-ins (opt-in) | pconfig `spa_vault` / `jellyfin` and `_vault` | **End-to-end encrypted** in the user's browser with a passphrase only the user knows (PBKDF2 + AES-GCM), then additionally sealed with the channel key pair. Admins and the hub can't read it; a forgotten passphrase can't be recovered. Synced to clones like other pconfig. |
| Shared clips | the user's cloud files | Uploaded through `wall_attach` when the user shares a clip (MP4, at most one minute). |

The endpoint is `GET/POST /spa/jellyfin-nowplaying` (handler `JellyfinNowPlaying`). Visitors' reads are checked against the owner's chosen audience; writes require the owner's session and CSRF token.

## Watch / listen parties

Parties use Jellyfin's built-in **SyncPlay**. On the Jellyfin side:

- Each participant needs an account on the same Jellyfin server. Hubzilla does not create Jellyfin accounts.
- The account's SyncPlay permission must allow creating and joining groups (Jellyfin's default for new users).

Invites are ordinary private messages; party chat happens in that message thread.

## Browser assets

The video player loads `hls.js` (light build, ~115 KB gzipped) only when a video needs converting, and clipping loads the theme's bundled ffmpeg WebAssembly (about 30 MB, fetched once per browser from the theme's `assets/ffmpeg/`). Neither is in the offline precache.

## Turning it off

Users switch it off in **Settings → Integrations**: the player, its live updates and its pushes to the hub stop. A "Listening now" card that was showing goes away once its stored track is stale (about 20 minutes); setting the audience to off removes it at once.
