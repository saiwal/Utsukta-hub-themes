# PHP API

The `src/Api/` directory is a PHP backend that lives inside the Hubzilla theme as `Theme\Solidified\Api`. It is deployed alongside the theme and provides the SPA's own JSON API at `/spa/*`.

## Router

`Router::dispatch($method)` reads `App::$argv[1]` (the URL segment after `/spa/`) and maps it to a handler class:

```
URL: /spa/network      → Handlers\Network::get()
URL: /spa/item/abc123  → Handlers\Item::get()
URL: /spa/settings/display (POST) → Handlers\Settings::post()
```

Unknown resources return `404`. Unsupported HTTP methods return `405`. Path segments are URL-decoded before dispatch.

### Route Map (partial)

| Endpoint | Handler |
|----------|---------|
| `pconfig` | `Handlers\Pconfig` |
| `csrf` | `Handlers\Csrf` |
| `nav` | `Handlers\Nav` |
| `network` | `Handlers\Network` |
| `channel` | `Handlers\Channel` |
| `item` | `Handlers\Item` |
| `photos` | `Handlers\Photos` |
| `files` | `Handlers\Files` |
| `articles` | `Handlers\Articles` |
| `webpages` | `Handlers\Webpages` |
| `notes` | `Handlers\Notes` |
| `wiki` | `Handlers\Wiki` |
| `chat` | `Handlers\Chat` |
| `cal` | `Handlers\Cal` |
| `settings` | `Handlers\Settings` |
| `profile` | `Handlers\Profile` |
| `admin` | `Handlers\Admin` |
| `search` | `Handlers\Search` |
| `pubstream` | `Handlers\Pubstream` |
| `display` | `Handlers\Display` |

## Auth Guards

`Auth.php` provides two guards used at the top of handler methods:

```php
Auth::requireLocalGet();
// Requires: authenticated local channel. Returns 401 if not.

Auth::requireLocalJson();
// Requires: auth + Content-Type: application/json + valid CSRF token.
// Parses request body into Auth::$parsedBody.
```

Use `requireLocalGet()` for read endpoints that need authentication. Use `requireLocalJson()` for all write/mutating POST endpoints.

## Response Helpers

`Response.php` provides a consistent JSON envelope:

```php
// Success
Response::send($data);
// → {"data": ...}

// Success with extra metadata
Response::send($data, ['key' => 'value']);
// → {"data": ..., "meta": {"key": "value"}}

// Paginated list
Response::paginate($items, $offset, $limit, $rootCount, $nouveau);
// → {"data": [...], "meta": {"offset":0, "limit":20, "count":15, "root_count":15, "has_more":false, "nouveau":false}}

// Error
Response::error(404, 'Not found');
// → {"error": {"status": 404, "message": "Not found"}}
```

All methods call `exit` — no further code runs after a response is sent.

### Pagination Meta Fields

| Field | Meaning |
|-------|---------|
| `offset` | Starting index of this page |
| `limit` | Maximum items requested |
| `count` | Items actually returned |
| `root_count` | Total root items matching the query |
| `has_more` | Whether more pages are available |
| `nouveau` | Whether this response contains new (unseen) items |

## Item Format (`Concerns/FormatsItems.php`)

The `FormatsItems` trait provides `formatItem(array $item, string $observer_xchan): array` which normalises raw DB rows into the canonical item shape used by all stream endpoints.

Key fields returned:

```
uuid, mid, parent_mid, thr_parent, message_top
created, edited, body, title
verb, obj_type
like_count, dislike_count, announce_count, comment_count
item_private, item_thread_top, item_unseen, iid, profile_uid
flags[]
author{ xchan, name, url, photo }
owner{ xchan, name, url, photo }
permalink
viewer_liked, viewer_disliked, viewer_repeated
viewer_attending, viewer_declining, viewer_maybe
viewer_following
attach[]
```

## Writing a New Handler

1. Create `src/Api/Handlers/MyResource.php`:

```php
<?php
namespace Theme\Solidified\Api\Handlers;

use Theme\Solidified\Api\Auth;
use Theme\Solidified\Api\Response;

class MyResource
{
    public function get(): void
    {
        Auth::requireLocalGet();
        // ... build $data
        Response::send($data);
    }

    public function post(): void
    {
        Auth::requireLocalJson();
        $body = Auth::$parsedBody;
        // ... process $body
        Response::send(['ok' => true]);
    }
}
```

2. Register it in `Router.php`:

```php
private static array $map = [
    // ...
    'myresource' => Handlers\MyResource::class,
];
```

3. Call it from the SPA at `/spa/myresource`.

## Item Endpoints Reference

```
GET  /spa/item/:mid                 item + thread root
GET  /spa/item/:mid/comments        all comments for item
GET  /spa/item/:mid/comments/:n     most recent N comments
GET  /spa/item/:mid/likes           who liked
GET  /spa/item/:mid/dislikes        who disliked
GET  /spa/item/:mid/repeats         who repeated

POST /spa/item                      create top-level post
POST /spa/item/:mid/comment         post a comment
POST /spa/item/:mid/like            toggle like
POST /spa/item/:mid/dislike         toggle dislike
POST /spa/item/:mid/repeat          toggle repeat/reshare
POST /spa/item/:mid/accept          RSVP accept (exclusive)
POST /spa/item/:mid/reject          RSVP reject (exclusive)
POST /spa/item/:mid/tentativeaccept RSVP tentative (exclusive)
POST /spa/item/:mid/star            toggle starred flag
POST /spa/item/:mid/edit            edit body/title
POST /spa/item/:mid/delete          delete (federated)
POST /spa/item/:mid/reshare         reshare with optional text
```

`:mid` may be a full zot6 URL, a short UUID, or a `b64.`-prefixed base64-encoded mid.

## Notes Endpoint

Personal notes are stored as `ITEM_TYPE_CUSTOM` (type 9) items. They never federate and never appear in any stream.

```
GET  /spa/notes            list the authenticated user's notes (paginated)
POST /spa/notes            create a new note
POST /spa/item/:mid/edit   edit an existing note (shared with regular items)
POST /spa/item/:mid/delete delete a note
```

### GET /spa/notes

Query params: `start` (default 0), `limit` (default 20, max 50).

Returns a paginated envelope. Each item:

```
id, mid, uuid, body, created, edited, mimetype
```

Only items with `verb = 'Create'` and `item_type = ITEM_TYPE_CUSTOM` are returned, which filters out any companion `Add` activities Hubzilla may have created via the legacy `/item` endpoint.

### POST /spa/notes

Body (JSON): `{ "body": "...", "mimetype": "text/bbcode" }`

Creates a note directly via `item_store()` (bypasses the legacy `/item` endpoint, so no companion `Add` activity is generated). Returns `{ "data": { "mid": "..." } }`.

The note is stored with `item_private = 1`, `item_wall = 1`, `item_origin = 1`, and no ACL entries — visible only to the owner.
