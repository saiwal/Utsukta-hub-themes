# Chat apps (Matrix, Delta Chat)

Contacts can be reached on Matrix or Delta Chat in one click. The SPA resolves
a contact's addresses and links out to the app they already use, or, for
Matrix, opens the built-in client in Messenger (below):

| App        | Accepted values                                              | Link                          |
|------------|--------------------------------------------------------------|-------------------------------|
| Matrix     | `@user:server[:port]`, `matrix:u/user:server`, a matrix.to link | `https://matrix.to/#/@user:server` |
| Delta Chat | an `https://i.delta.chat/#…` invite link, or an email address | the invite link, or `mailto:` |

Prefer the Delta Chat invite link: it carries the key, so the first message is
encrypted. A plain email address opens whatever handles `mailto:`, which is
Delta Chat only if the user has set it as the mail handler.

Every link is `https:` or `mailto:`, so none of the bbcode/paste scheme
filters needed widening.

## Where addresses come from

`Api/ChatAddresses.php::forXchan()` merges four sources, in this order (the
first one to name a type+value wins, which is the `source` the UI sees):

| source        | Stored in                                                   | Who sees it |
|---------------|-------------------------------------------------------------|-------------|
| `private`     | your per-connection vCard, `abconfig(uid, xchan, system, vcard)` IMPP. This is core's Connedit vCard, so classic `/connedit` shows the same entries | you |
| `addressbook` | IMPP of a CardDAV card in *your* address books whose EMAIL/IMPP equals the contact's `xchan_addr`, or whose URL equals its `xchan_url` | you |
| `profile`     | a local channel's own list, pconfig `spa/chat_ids`          | whoever has `view_profile` |
| `actor`       | `PropertyValue` fields of a remote ActivityPub actor whose name looks like Matrix/Delta Chat/chat/Element, or whose value is a Matrix ID / matrix.to / i.delta.chat link. Cached 1h in `Zotlabs\Lib\Cache` (`spa:chatids:<hash>`), misses included | everyone |

`private` and `addressbook` entries show a lock in the menu.

Writing private entries replaces only the vCard's Matrix/Delta Chat IMPP. They
are stored with `TYPE=matrix` / `TYPE=deltachat`, and every other IMPP (XMPP,
…) and property core keeps there survives.

Every value is untrusted: actor fields are federated, vCards are imported, and
users type the rest. `normalize()` validates each one and drops whatever doesn't
pass. `packages/spa-core/src/lib/chat-links.ts` mirrors it for the editors and
the address book; keep the two in step.

## Publishing your own

Your addresses (Profile editor → "Chat addresses") also go out as
ActivityPub actor fields through the `encode_person` hook (`hooks/chatids.php`,
registered by `SpaTheme::HOOKS`), but only while your profile is public
(`view_profile` for an anonymous observer). Mastodon shows them as profile
fields. Another spa-core hub picks them up as the `actor` source.

The hook registers on theme enable, so **an existing hub must re-enable the
theme** before addresses appear on its actors.

## API

```
GET  /spa/chatids?xchan=<hash>                      → [{type, value, href, source}]
GET  /spa/chatids?self=1                            → your published list
POST /spa/chatids { self: [{type, value}] }         → set your published list
POST /spa/chatids { xchan, items: [{type, value}] } → set your private entries (connections only)
```

Visitors get the `profile` and `actor` sources only.

## UI

`src/shared/views/ChatApps.tsx`:
- `ChatAppsButton`: one link when there is a single address, a menu when there are several, nothing when there are none. Used in the author popover, the profile header and `/chanview`.
- `ChatMenuItems`: entries for an existing ⋯ menu, used in the connections list. Menu items mount only when the menu opens, so the list fetches nothing up front.
- `ChatAddressFields`: the editor, in the profile editor (own) and the connection editor (private).

The address book list links IMPP entries directly.

## Checks

- `node --experimental-strip-types packages/spa-core/src/lib/chat-links.test.ts`
- `npm run test:php -- ChatAddresses`

## Built-in Matrix client (Messenger → Matrix tab)

Your own Messenger (`/messenger/<you>`) has a **Matrix** tab next to DMs and
Chatrooms, once the **Matrix** app is switched on in Settings → Integrations.
That app is `modules/matrix/index.ts`: a route-less module that declares only
`frontendFeature` (`defaultEnabled: false`, like Jellyfin). The tab and the in-app
links from chat buttons both check `matrixEnabled()` in `matrix-session.ts`, which
never imports the SDK. Switching it off hides the tab. A client already running
keeps syncing until reload, and the stored login is kept so switching back on is
instant. The contact chat buttons (matrix.to links) don't depend on the app. It runs entirely in the browser using `matrix-js-sdk` (pinned
exactly) and its Rust crypto (wasm). The hub never sees the password, the
access token or any messages.

| File | Role |
|------|------|
| `messenger/matrix-session.ts` | stored login (`localStorage` `hz-matrix:<uid>`, per hub channel), with no SDK import, so `ChatAppsButton` can check `hasMatrixSession()` from any page |
| `messenger/matrix/client.ts` | the only SDK importer: login (password via `.well-known` discovery, or SSO), `resume()`, `logout()`, `recover()`, `openDirect()`. A `tick` signal bumps on every room/timeline/decryption event |
| `messenger/matrix/MatrixParts.tsx` | the list column (login, invites/DMs/rooms, recovery) and the pane router, lazy-loaded by `MessengerView` |
| `messenger/matrix/Timeline.tsx` | one room: messages, older pages, composer, read receipts, invite accept/decline |
| `messenger/matrix/MatrixMedia.tsx` + `media.ts` | attachments and avatars: mxc → object URL |
| `messenger/matrix/media-crypto.ts` | attachment decryption + the blob-type allowlist (pure; `media-crypto.test.ts`) |
| `messenger/matrix/Verification.tsx` | emoji self-verification |

URLs live under `/messenger/<nick>/matrix`: `/<roomId>`, `/dm/<mxid>` (open
an existing DM or create an encrypted one, then replace the URL with the room's),
`/new`, and `/login` (the SSO return leg). When this browser is signed in, a
contact's Matrix chat button links to `/dm/<mxid>` instead of matrix.to. The
new-DM picker suggests connections from `GET /spa/chatids?type=matrix`, which
uses only the cheap sources: private vCards and local channels' published lists.

Notes on the implementation:
- **Lazy.** The SDK chunk and the 7.8 MB `matrix_sdk_crypto_wasm_bg.wasm` load only when the tab opens. `HEAVY_TOOLS` in `build-sw.mjs` keeps `messenger/matrix/`, `matrix-js-sdk` and the shared `rust-crypto` chunk out of the precache; that shared chunk's manifest key has no package path, hence its own term.
- **Keeps running.** Once started, sync continues across SPA navigation until reload or sign-out, so returning to the tab is instant.
- **Encryption.** Crypto and sync stores are IndexedDB databases prefixed `hz-matrix-<uid>-<mxid>`. New DMs are created encrypted. The "Unlock encrypted history" dialog takes a recovery key or passphrase: it cross-signs this device and restores the key backup. A banner prompts for it while the device isn't cross-signed.
- **Session verification.** `Verification.tsx` does emoji (SAS) self-verification. It is started from the tab (⋯ menu, or the unverified banner) with `requestOwnUserVerification()`, or arrives as `CryptoEvent.VerificationRequestReceived` from another of your sessions. QR is never advertised, so Element falls back to emoji. Once verified, the other device sends the backup key (`KeyBackupDecryptionKeyCached`) and the backup is restored with no recovery key needed. Incoming requests only show while the Matrix tab is mounted.
- **Media.** Downloads are authenticated (Matrix 1.11, bearer token) when the server advertises v1.11, and fall back to legacy unauthenticated URLs otherwise. Encrypted rooms send `file` instead of `url`; it is checked against `hashes.sha256` and then AES-CTR decrypted with WebCrypto, because the SDK doesn't do it. Object URLs are `blob:`, which runs in the hub's origin, so `safeType()` keeps a declared type only for raster images, audio and common video types: SVG, HTML and everything else become `application/octet-stream`. Images show a preview (a server thumbnail when unencrypted, else the sender's `thumbnail_file`, else the file itself) and open full size on click. Videos load only on click. Audio loads inline. Files are always downloaded, never opened. Object URLs are never revoked (see the `ponytail:` note).
- **Sending attachments.** Use the paperclip, paste, or drop onto the timeline. Files go one at a time with a progress bar, after checking the server's `m.upload.size`. In an encrypted room the file is encrypted in the browser first (`encryptAttachment()`: AES-256-CTR, random IV high half, SHA-256 of the ciphertext, all unpadded base64). The ciphertext is uploaded as `application/octet-stream` with no filename, and the message carries `file` instead of `url`. Images send their `w`/`h` so viewers can reserve space.
- **Replies and reactions.** Each message has React and Reply actions: on hover/focus at desktop width, always visible on touch widths.
  - Replies send `m.relates_to.m.in_reply_to` with no legacy fallback. Incoming replies show a quote of the target, which scrolls to it on click when it's loaded. The old `> <@user> …` plain-text fallback is stripped, and `<mx-reply>` is already dropped from HTML.
  - Reactions come from the SDK's relation aggregation (`room.relations…getSortedAnnotationsByKey()`). They show as chips with counts, your own highlighted, and clicking a chip toggles it: send `m.reaction` or redact yours.
  - The picker is a fixed set of Unicode emoji, because the hub's `EmojiPicker` inserts image shortcodes, which are not valid reaction keys.
- **Spaces.** A rail like Element's: Home (every room), then each joined space. A space shows its rooms through subspaces, following `m.space.child` events that have a `via`. The choice is remembered per browser (`localStorage` `hz-matrix-space`). DMs with space members are *not* pulled into a space the way Element does. All/Unreads/People/Rooms chips filter the list.
- **Untrusted HTML.** `formatted_body` is sanitised to the Matrix spec's HTML subset, without `<img>`, and links are forced to open in a new tab.
- **CSP.** Core's opt-in CSP sets no `connect-src`, and its `script-src` has `'unsafe-eval'`, which permits WebAssembly, so no hook change was needed.
- **Hub logout.** Logging out of the hub does not sign out of Matrix. The session is keyed by channel id, so another channel never sees it.

Not built yet:
- thumbnails for sent images/videos (viewers fall back to the full file), and inline `<img>` inside formatted bodies
- threads, editing/deleting your own messages, and edit history (edits show their newest text)
- verifying *other people* (only your own sessions so far)
- setting up key backup from here
- push notifications, and an unread badge outside the tab

## Not done

Delta Chat can't run in a browser, because it needs IMAP/SMTP connections, so it
stays links-only. XMPP/Signal would be one more type in `normalize()` and
`normalizeChat()`.
