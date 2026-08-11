# PHP API

`src/Api/` डायरेक्टरी एक PHP बैकएंड है जो Hubzilla थीम के अंदर `Theme\Solidified\Api` के रूप में रहता है। यह थीम के साथ डिप्लॉय होता है और `/spa/*` पर SPA का खुद का JSON API प्रदान करता है।

## Router

`Router::dispatch($method)` `App::$argv[1]` (URL का `/spa/` के बाद का सेगमेंट) पढ़ता है और उसे हैंडलर क्लास से मैप करता है:

```
URL: /spa/network      → Handlers\Network::get()
URL: /spa/item/abc123  → Handlers\Item::get()
URL: /spa/settings/display (POST) → Handlers\Settings::post()
```

अज्ञात रिसोर्स पर `404` और असमर्थित HTTP method पर `405` रिटर्न होता है। डिस्पैच से पहले पाथ सेगमेंट URL-decode होते हैं।

### रूट मैप (आंशिक)

| एंडपॉइंट | हैंडलर |
|----------|--------|
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

## Auth गार्ड

`Auth.php` दो गार्ड प्रदान करता है जो हैंडलर मेथड की शुरुआत में उपयोग होते हैं:

```php
Auth::requireLocalGet();
// ज़रूरी: ऑथेंटिकेटेड लोकल चैनल। न हो तो 401 रिटर्न।

Auth::requireLocalJson();
// ज़रूरी: auth + Content-Type: application/json + वैध CSRF टोकन।
// request body को Auth::$parsedBody में parse करता है।
```

ऑथेंटिकेशन की ज़रूरत वाले read एंडपॉइंट के लिए `requireLocalGet()` उपयोग करें। सभी write/mutating POST एंडपॉइंट के लिए `requireLocalJson()` उपयोग करें।

## Response हेल्पर

`Response.php` एक समान JSON एनवेलप प्रदान करता है:

```php
// सफलता
Response::send($data);
// → {"data": ...}

// अतिरिक्त मेटाडेटा के साथ सफलता
Response::send($data, ['key' => 'value']);
// → {"data": ..., "meta": {"key": "value"}}

// Paginated सूची
Response::paginate($items, $offset, $limit, $rootCount, $nouveau);
// → {"data": [...], "meta": {"offset":0, "limit":20, "count":15, "root_count":15, "has_more":false, "nouveau":false}}

// एरर
Response::error(404, 'Not found');
// → {"error": {"status": 404, "message": "Not found"}}
```

सभी मेथड `exit` कॉल करते हैं — रिस्पॉन्स भेजने के बाद कोई कोड नहीं चलता।

### Pagination मेटा फ़ील्ड

| फ़ील्ड | अर्थ |
|--------|------|
| `offset` | इस पेज का शुरुआती इंडेक्स |
| `limit` | अधिकतम अनुरोधित आइटम |
| `count` | वास्तव में लौटाए गए आइटम |
| `root_count` | क्वेरी से मेल खाने वाले कुल root आइटम |
| `has_more` | क्या और पेज उपलब्ध हैं |
| `nouveau` | क्या इस रिस्पॉन्स में नए (अनदेखे) आइटम हैं |

## Item फ़ॉर्मेट (`Concerns/FormatsItems.php`)

`FormatsItems` trait `formatItem(array $item, string $observer_xchan): array` प्रदान करता है जो raw DB rows को canonical item shape में normalise करता है।

मुख्य फ़ील्ड:

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

## नया हैंडलर लिखना

1. `src/Api/Handlers/MyResource.php` बनाएं:

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
        // ... $data बनाएं
        Response::send($data);
    }

    public function post(): void
    {
        Auth::requireLocalJson();
        $body = Auth::$parsedBody;
        // ... $body process करें
        Response::send(['ok' => true]);
    }
}
```

2. `Router.php` में रजिस्टर करें:

```php
private static array $map = [
    // ...
    'myresource' => Handlers\MyResource::class,
];
```

3. SPA से `/spa/myresource` पर कॉल करें।

## Item एंडपॉइंट संदर्भ

```
GET  /spa/item/:mid                 item + thread root
GET  /spa/item/:mid/comments        item के सभी comments
GET  /spa/item/:mid/comments/:n     सबसे हालिया N comments
GET  /spa/item/:mid/likes           किसने like किया
GET  /spa/item/:mid/dislikes        किसने dislike किया
GET  /spa/item/:mid/repeats         किसने repeat किया

POST /spa/item                      नया top-level पोस्ट बनाएं
POST /spa/item/:mid/comment         comment पोस्ट करें
POST /spa/item/:mid/like            like toggle करें
POST /spa/item/:mid/dislike         dislike toggle करें
POST /spa/item/:mid/repeat          repeat/reshare toggle करें
POST /spa/item/:mid/accept          RSVP accept (exclusive)
POST /spa/item/:mid/reject          RSVP reject (exclusive)
POST /spa/item/:mid/tentativeaccept RSVP tentative (exclusive)
POST /spa/item/:mid/star            starred flag toggle करें
POST /spa/item/:mid/edit            body/title edit करें
POST /spa/item/:mid/delete          delete (federated)
POST /spa/item/:mid/reshare         optional text के साथ reshare
```

`:mid` एक पूरा zot6 URL, short UUID, या `b64.`-prefixed base64-encoded mid हो सकता है।

## Notes एंडपॉइंट

Personal notes `ITEM_TYPE_CUSTOM` (type 9) items के रूप में store होते हैं। ये कभी federate नहीं होते और किसी भी stream में नहीं दिखते।

```
GET  /spa/notes            authenticated user के notes की सूची (paginated)
POST /spa/notes            नया note बनाएं
POST /spa/item/:mid/edit   मौजूदा note edit करें (regular items के साथ shared)
POST /spa/item/:mid/delete note delete करें
```

### GET /spa/notes

Query params: `start` (default 0), `limit` (default 20, max 50)।

Paginated envelope लौटाता है। प्रत्येक item:

```
id, mid, uuid, body, created, edited, mimetype
```

केवल `verb = 'Create'` और `item_type = ITEM_TYPE_CUSTOM` वाले items लौटाए जाते हैं — यह legacy `/item` endpoint के किसी भी companion `Add` activity को filter करता है।

### POST /spa/notes

Body (JSON): `{ "body": "...", "mimetype": "text/bbcode" }`

`item_store()` के ज़रिए सीधे note बनाता है (legacy `/item` endpoint bypass होता है, इसलिए कोई companion `Add` activity नहीं बनती)। `{ "data": { "mid": "..." } }` लौटाता है।

Note `item_private = 1`, `item_wall = 1`, `item_origin = 1` के साथ store होता है और कोई ACL entries नहीं होतीं — केवल owner को दिखता है।
