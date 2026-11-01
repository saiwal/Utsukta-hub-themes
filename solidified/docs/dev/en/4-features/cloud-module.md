# Cloud module

The Files/Cloud module (`src/modules/files/`, module id `cloud`) lists a channel's
attach storage. Two things about it are unlike any other module, and both come from
the same fact: `/cloud` is *also* a real WebDAV endpoint served by classic core, so
the SPA shares a URL space with SabreDAV.

| Piece | File |
|---|---|
| Module + widget registration | `src/modules/files/index.ts` |
| Routed view (returns `null`) | `src/modules/files/views/FilesView.tsx` |
| Breadcrumb, listing, toolbar, modals | `src/modules/files/widgets/FilesContentWidget.tsx` |
| API + URL/path helpers | `src/modules/files/api.ts` |
| Backend | `packages/spa-core/php/Api/Handlers/Files.php` |
| DAV/share link builder | `src/shared/lib/shareLinks.ts` → `davPath()` |
| CSP workaround | `src/php/theme_init.php` |

## Folder navigation: the URL is the source of truth

The file list works in terms of **folder hashes** (`attach.hash`) — `listFolder(nick, hash)`
hits `/spa/files/:nick/folder/:hash`. URLs, however, are paths:
`davPath()` builds `/cloud/sk/tmp/folder%202` for share links, post attachments and the
Info panel's BBCode, and that is the same URL WebDAV clients use.

So the module keeps a `navStack` of `FolderFrame`s (`{ hash, displayPath, label }`, root
first) and reconciles it with `location.pathname`:

```ts
cloudPathSegments("/cloud/sk/tmp/folder%202", "sk")  // → ["tmp", "folder 2"]
cloudPath("sk", "tmp/folder 2")                      // → "/cloud/sk/tmp/folder%202"
resolveFolderPath("sk", ["tmp", "folder 2"])         // → FolderFrame[]  (root excluded)
```

`resolveFolderPath()` walks root → leaf calling `listFolder()` once per level, because
only a listing can turn a folder *name* into its hash. Depth+1 requests, and it stops at
the first segment that isn't a folder — so a URL naming a *file* opens the folder that
contains it rather than dead-ending on an empty list.

In `FilesContentWidget`:

- an effect on `location.pathname` resolves and replaces the stack, but returns early when
  the URL path already equals `current().displayPath` — that is how it tells an incoming
  link apart from a navigation it performed itself, and avoids re-resolving hashes it
  already has;
- `navigateInto()` / `navigateTo()` push the new stack *and* `navigate(cloudPath(...))`, so
  the address bar, the back button and copy-paste all follow the folder;
- the `files-folder` query keys on `current().hash`, so the listing follows for free.

**Historical note.** Before this, the stack was seeded from `?folder=<hash>&path=<display_path>`
and the wildcard route segment was ignored — every path-shaped link landed on the root.
Its one producer was `ChannelActivities.php`, which now links the containing folder by path
like everything else. If you find `?folder=` anywhere, it is dead.

## Linking into a folder from elsewhere

Use the path form. From TypeScript: `cloudPath(nick, displayPath)` (or `davPath()` when you
want the file itself). From PHP, rawurlencode per segment — a folder name may contain `/`-
unsafe characters and spaces:

```php
$url = z_root() . '/cloud/' . $nick;
if ($dir)
    $url .= '/' . implode('/', array_map('rawurlencode', explode('/', $dir)));
```

Note `dirname($display_path)` for "the containing folder" — core's
`rtrim($display_path, $filename)` is a charlist trim and eats trailing characters off the
folder name.

## The SabreDAV CSP, and why `theme_init.php` removes it

A *real* HTTP GET to `/cloud/...` (shared link, bookmark, hard reload, new tab — anything
that isn't client-side routing) is dispatched by classic `Zotlabs\Module\Cloud`, which
starts a full SabreDAV server. `Sabre\DAV\Browser\Plugin::httpGet()` sets, on every browser
GET it sees:

```
Content-Security-Policy: default-src 'none'; img-src 'self'; style-src 'self'; font-src 'self';
```

There is no `script-src`, so `default-src 'none'` applies to scripts and blocks this theme's
own bundle: the page renders blank with "Loading failed for the module app-[hash].js" in the
console. Unrelated to Hubzilla's admin `system.content_security_policy` setting, which is
off by default and whose policy (`script-src 'self' 'unsafe-inline' 'unsafe-eval'`) never
blocked us anyway.

`src/php/theme_init.php` drops it:

```php
foreach (headers_list() as $h)
    if (stripos($h, 'content-security-policy:') === 0 && strpos($h, "default-src 'none'") !== false) {
        header_remove('Content-Security-Policy');
        break;
    }
```

Three things make that placement load-bearing:

- **Only `construct_page()` includes `theme_init.php`** (`boot.php`), so this runs on page
  renders and nothing else. A *file* GET under `/cloud` ends in `killme()` without
  constructing a page, so downloads keep SabreDAV's policy — which still matters there: it is
  what sandboxes an inline-served upload. Core forces `text/html`, `text/css` and
  `application/javascript` to `text/plain` + attachment (`Zotlabs/Storage/File.php`), but only
  for channels without the `code` permission, and `image/svg+xml` is not on that list at all.
  **Do not move this into `solidified_init()`** — `Zotlabs\Web\Router` calls that on *every*
  request, downloads included, which would re-open exactly that hole.
- It runs *before* the `header()` calls further down `construct_page()`, so an
  admin-enabled core CSP is written afterwards and survives.
- Only a policy containing `default-src 'none'` is matched, so nothing else is touched.

`header()` from `solidified_init()` would not work either way: `Zotlabs\Storage\Browser::generateDirectoryIndex()`
calls it *before* SabreDAV writes its headers, so SabreDAV would simply overwrite it.

### What is given up

The rendered page has no CSP unless the admin enabled core's. The listing HTML SabreDAV
generated (the part that reflects user-controlled filenames, and what its policy was written
to protect) is discarded — `src/php/default.php` never emits `$page['content']`, it emits the
SPA shell. What remains is defense-in-depth against a DOM XSS in the SPA itself, and that is
not cloud-specific: every other route of this theme renders far more untrusted content with
no CSP already. If you want one here anyway, replace the removal with a `header()` naming a
policy the SPA can run under — `script-src 'self' 'unsafe-inline'` (`default.php` carries two
inline scripts), plus `img-src`/`media-src https: data: blob:` and `frame-src https:` for
remote avatars, photos and oembeds.

## Tests

`node --experimental-strip-types src/modules/files/pathHelpers.test.ts` covers the pure URL
halves (`cloudPathSegments`, and its round trip through `cloudPath`). `resolveFolderPath()`
is a network walk and is not covered.
