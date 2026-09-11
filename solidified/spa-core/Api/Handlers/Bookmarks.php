<?php
/**
 * Utsukta\SpaCore\Api\Handlers\Bookmarks
 *
 * Hubzilla stores bookmarks as `menu` + `menu_item` rows — there is no bookmark
 * table. A bookmark folder is a menu carrying MENU_BOOKMARK; MENU_SYSTEM on top
 * of that means "saved from somebody else's post", which is the split core's
 * /bookmarks renders as "Bookmarks" vs "My Connections Bookmarks".
 *
 * Every write goes through include/menu.php's helpers and ends in
 * menu_sync_packet(), because bookmarks are clone-synced data: a raw
 * DELETE/UPDATE leaves the user's other hubs holding a stale menu forever.
 *
 * Routes:
 *   GET    /spa/bookmarks                  → folders + items (own and connections')
 *   GET    /spa/bookmarks/chat             → chatroom bookmarks only
 *   POST   /spa/bookmarks                  → add one { url, title, menu_id?, menu_name?, ischat?, private? }
 *   POST   /spa/bookmarks/item             → save links out of a post { item, urls?, menu_id?, menu_name? }
 *   POST   /spa/bookmarks/folder           → create or rename a folder { menu_id?, name, desc? }
 *   POST   /spa/bookmarks/reorder          → { menu_id, ids: [mitem_id, …] }
 *   POST   /spa/bookmarks/:id              → edit or move an item { title?, url?, menu_id? }
 *   DELETE /spa/bookmarks/:id              → remove an item
 *   DELETE /spa/bookmarks/folder/:menu_id  → remove a folder and its items
 */

namespace Utsukta\SpaCore\Api\Handlers;

use App;
use Zotlabs\Lib\Apps;
use Utsukta\SpaCore\Api\Auth;
use Utsukta\SpaCore\Api\Response;
use Utsukta\SpaCore\Api\ContentTypes;
use Utsukta\SpaCore\Api\Concerns\ResolvesAcl;

class Bookmarks
{
    use ResolvesAcl;   // parseHashList() — ACL columns must survive an edit

    public function get(): void
    {
        $uid     = Auth::requireLocal();
        $subpath = App::$argv[2] ?? '';

        // Chatroom bookmarks are created by the chat module, which core does not
        // gate on the Bookmarks app either (they ride /rbmark) — so this stays open.
        if ($subpath === 'chat') {
            $this->getChatBookmarks($uid);
        } else {
            $this->requireApp($uid);
            $this->getAllBookmarks($uid);
        }
    }

    public function post(): void
    {
        $uid     = Auth::requireLocalJson();
        $data    = Auth::$parsedBody;
        $subpath = App::$argv[2] ?? '';

        switch (true) {
            // Plain "bookmark this URL" is core's /rbmark, which has no app guard.
            // The chat module's "bookmark this room" depends on that.
            case $subpath === '':
                $this->createBookmark($uid, $data);

            case $subpath === 'item':
                $this->requireApp($uid);
                $this->saveFromItem($uid, $data);

            case $subpath === 'folder':
                $this->requireApp($uid);
                $this->saveFolder($uid, $data);

            case $subpath === 'reorder':
                $this->requireApp($uid);
                $this->reorder($uid, $data);

            case ctype_digit($subpath):
                $this->requireApp($uid);
                $this->editItem($uid, intval($subpath), $data);

            default:
                Response::error(404, 'Unknown bookmarks route');
        }
    }

    public function delete(): void
    {
        $uid     = Auth::requireLocalJson();
        $subpath = App::$argv[2] ?? '';

        $this->requireApp($uid);

        if ($subpath === 'folder') {
            $this->deleteFolder($uid, intval(App::$argv[3] ?? 0));
        }

        $this->deleteItem($uid, intval($subpath));
    }

    // ── Reads ──────────────────────────────────────────────────────────────────

    private function getChatBookmarks(int $uid): void
    {
        $r = q(
            "SELECT mi.mitem_id, mi.mitem_link, mi.mitem_desc
             FROM menu_item mi
             JOIN menu m ON m.menu_id = mi.mitem_menu_id
             WHERE mi.mitem_channel_id = %d
               AND (m.menu_flags  & %d)
               AND (mi.mitem_flags & %d)
             ORDER BY mi.mitem_desc ASC",
            intval($uid),
            MENU_BOOKMARK,
            MENU_ITEM_CHATROOM
        );

        $items = [];
        foreach (($r ?: []) as $row) {
            $items[] = [
                'id'    => intval($row['mitem_id']),
                'url'   => self::unescapeStored($row['mitem_link']),
                'title' => self::unescapeStored($row['mitem_desc']),
            ];
        }

        Response::send(['bookmarks' => $items]);
    }

    private function getAllBookmarks(int $uid): void
    {
        // Bitmask test, not core's menu_list() equality check (include/menu.php
        // compares menu_flags = N despite its docblock promising a mask) — so a
        // menu the user hand-flagged "Allow Bookmarks" in core's /menu UI still
        // shows up here.
        $menus = q(
            "SELECT menu_id, menu_name, menu_desc, menu_flags
             FROM menu
             WHERE menu_channel_id = %d AND (menu_flags & %d)
             ORDER BY menu_name ASC",
            intval($uid),
            MENU_BOOKMARK
        );

        $result = [];
        foreach (($menus ?: []) as $menu) {
            $items = q(
                "SELECT mitem_id, mitem_link, mitem_desc, mitem_flags, mitem_order,
                        allow_cid, allow_gid, deny_cid, deny_gid
                 FROM menu_item
                 WHERE mitem_menu_id = %d AND mitem_channel_id = %d
                 ORDER BY mitem_order ASC, mitem_desc ASC",
                intval($menu['menu_id']),
                intval($uid)
            );

            $item_list = [];
            foreach (($items ?: []) as $item) {
                $flags = intval($item['mitem_flags']);
                $isZid = (bool)($flags & MENU_ITEM_ZID);

                // Decoded before zid(), which would otherwise append its query
                // parameter to an href-escaped string.
                $link = self::unescapeStored($item['mitem_link']);

                $item_list[] = [
                    'id'      => intval($item['mitem_id']),
                    'url'     => $link,
                    // What menu_render() hands the browser: a zot link gets magic
                    // auth appended, so following it keeps you logged in.
                    'visit_url' => $isZid ? zid($link) : $link,
                    'title'   => self::unescapeStored($item['mitem_desc']),
                    'order'   => intval($item['mitem_order']),
                    'is_chat' => (bool)($flags & MENU_ITEM_CHATROOM),
                    'is_zid'  => $isZid,
                    'private' => (bool)($item['allow_cid'] || $item['allow_gid']
                                     || $item['deny_cid'] || $item['deny_gid']),
                ];
            }

            $result[] = [
                'id'     => intval($menu['menu_id']),
                'name'   => self::unescapeStored($menu['menu_name']),
                // bookmark_add() names a post-derived folder "<16 hash chars> Name",
                // which is unreadable; menu_desc is the "X's bookmarks" label core
                // actually renders.
                'label'  => self::unescapeStored($menu['menu_desc'] ?: $menu['menu_name']),
                'system' => (bool)(intval($menu['menu_flags']) & MENU_SYSTEM),
                'items'  => $item_list,
            ];
        }

        Response::send(['menus' => $result]);
    }

    // ── Creates ────────────────────────────────────────────────────────────────

    private function createBookmark(int $uid, array $data): never
    {
        $url   = trim($data['url']   ?? '');
        $title = trim($data['title'] ?? '');

        if (!$url || !$title)
            Response::error(400, 'url and title are required');

        require_once('include/bookmarks.php');

        $channel = App::get_channel();
        $opts    = [
            'ischat'    => !empty($data['ischat']) ? 1 : 0,
            'menu_id'   => intval($data['menu_id'] ?? 0),
            'menu_name' => trim($data['menu_name'] ?? ''),
        ];

        // $private is core's "ACL this to me only" switch (allow_cid = own hash).
        bookmark_add($channel, $channel, ['url' => $url, 'term' => $title],
            !empty($data['private']) ? 1 : 0, $opts);

        Response::send([
            'success'  => true,
            'mitem_id' => $this->findItemId($uid, $url, $opts),
        ]);
    }

    /**
     * Core's Bookmarks::init() — save the bookmarkable links out of one post —
     * plus the SPA's addition: any link in the body, not only the ones the
     * author marked with #^.
     */
    private function saveFromItem(int $uid, array $data): never
    {
        $ref = trim((string)($data['item'] ?? ''));
        if (!$ref)
            Response::error(400, 'item is required');

        $item = $this->loadOwnItem($uid, $ref);
        if (!$item)
            Response::error(404, 'Item not found');

        // The folder is named after whoever wrote the post, so a bookmark saved
        // from a connection lands in MENU_SYSTEM|MENU_BOOKMARK, not your own folder.
        $s = q("SELECT * FROM xchan WHERE xchan_hash = '%s' LIMIT 1",
            dbesc($item['author_xchan']));
        if (!$s)
            Response::error(500, 'Author lookup failed');

        $terms = get_terms_oftype($item['term'] ?? [], TERM_BOOKMARK);
        $byUrl = [];
        foreach ($terms as $t) {
            $byUrl[$t['url']] = ['url' => $t['url'], 'term' => $t['term']];
        }

        $requested = $data['urls'] ?? null;
        $rejected  = [];
        if (is_array($requested)) {
            $chosen = [];
            foreach ($requested as $entry) {
                $url = trim((string)($entry['url'] ?? ''));
                if (!$url) continue;

                if (isset($byUrl[$url])) {
                    // A link the author marked — core's `burl` filter case.
                    $chosen[] = $byUrl[$url];
                    continue;
                }
                // Otherwise the client picked a plain link out of the rendered
                // body. Only accept it if it really is in the stored body,
                // otherwise this endpoint would file arbitrary URLs under a
                // "saved from a post" label.
                if (!$this->bodyContainsUrl($item, $url)) {
                    $rejected[] = $url;
                    continue;
                }

                $chosen[] = [
                    'url'  => $url,
                    'term' => trim((string)($entry['title'] ?? '')) ?: $url,
                ];
            }
        } else {
            // No selection: save every marked link, exactly as core does.
            $chosen = array_values($byUrl);
        }

        if (!$chosen)
            Response::error(400, $rejected
                ? 'Those links are not in this post'
                : 'No bookmarkable links in this post');

        require_once('include/bookmarks.php');

        $channel = App::get_channel();
        $opts    = [
            'menu_id'   => intval($data['menu_id'] ?? 0),
            'menu_name' => trim($data['menu_name'] ?? ''),
        ];

        foreach ($chosen as $t) {
            bookmark_add($channel, $s[0], $t, $item['item_private'], $opts);
        }

        Response::send(['success' => true, 'count' => count($chosen)]);
    }

    /**
     * A stored menu string, turned back into what the author actually wrote.
     *
     * menu_add_item() / menu_create() run *both* the link and the description
     * through escape_tags(), so '&' is stored as '&amp;'. That is right for core,
     * whose usermenu.tpl drops each value straight into HTML — an href for the
     * link, bbcode() output for the description — where the browser decodes it
     * again. But this API hands the values to JSON, and nothing downstream of that
     * does any HTML decoding: a link with two query parameters navigated nowhere,
     * and a title containing '&' read as a literal '&amp;'.
     *
     * Safe for the title because every SPA render site interpolates it as a text
     * node (BookmarksContentWidget, BookmarkedRoomsWidget) and never as innerHTML,
     * so the framework re-escapes it on the way into the DOM.
     *
     * Decoded on read rather than stored decoded, so core's own /bookmarks page,
     * its menu export, clone sync and every already-saved row keep working
     * unchanged. Same shape as FormatsItems' ContentTypes::decode() on bodies.
     */
    private static function unescapeStored(?string $value): string
    {
        return html_entity_decode((string) $value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /**
     * Does this URL actually occur in the post?
     *
     * The comparison cannot be a plain strpos against the stored body, because
     * the two sides are escaped to different depths. The client reads the URL out
     * of a rendered anchor, where `&` is `&amp;` — and it decodes it, so it sends
     * a bare `&`. The stored body may hold either form: 7% of the bodies on this
     * hub keep `&amp;` inside a URL, and a markdown body is escaped once more
     * again (which is what ContentTypes::decode undoes, mirroring the read path
     * the client was served from). Any link with two query parameters — a YouTube
     * timestamp, a UTM tag — landed on the wrong side of that and was rejected.
     *
     * Normalising entities can't conjure a URL the author never wrote, so this
     * still proves containment; it just stops proving it only for simple URLs.
     */
    private function bodyContainsUrl(array $item, string $url): bool
    {
        $norm = static function (string $s): string {
            // Repeated: the escaping depth differs per mimetype, and '&amp;amp;'
            // does occur in the wild. Bounded rather than while(), so a body full
            // of '&amp;amp;amp;…' can't spin here.
            for ($i = 0; $i < 3; $i++) {
                $next = html_entity_decode($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                if ($next === $s) break;
                $s = $next;
            }
            return $s;
        };

        $body = ContentTypes::decode($item['body'] ?? '', $item['mimetype'] ?? '');

        return str_contains($norm($body), $norm($url));
    }

    private function saveFolder(int $uid, array $data): never
    {
        require_once('include/menu.php');

        $name = trim($data['name'] ?? '');
        if (!$name)
            Response::error(400, 'name is required');

        $desc    = trim($data['desc'] ?? '') ?: $name;
        $menu_id = intval($data['menu_id'] ?? 0);

        // Both helpers return false when the channel already has a menu of that
        // name — menu names are the channel-wide key, shared with nav menus.
        if ($menu_id) {
            // Rename. requireBookmarkMenu() is what stops this reaching a nav menu.
            $menu = $this->requireBookmarkMenu($uid, $menu_id);
            $ok = menu_edit([
                'menu_id'         => $menu_id,
                'menu_channel_id' => $uid,
                'menu_name'       => $name,
                'menu_desc'       => $desc,
                // Preserve MENU_SYSTEM so a connections' folder stays in its section.
                'menu_flags'      => intval($menu['menu_flags']),
            ]);
            if ($ok === false)
                Response::error(409, 'A menu with that name already exists');
        } else {
            $menu_id = menu_create([
                'menu_channel_id' => $uid,
                'menu_name'       => $name,
                'menu_desc'       => $desc,
                'menu_flags'      => MENU_BOOKMARK,
            ]);
            if (!$menu_id)
                Response::error(409, 'A menu with that name already exists');
        }

        menu_sync_packet($uid, get_observer_hash(), $menu_id);

        Response::send(['success' => true, 'menu_id' => $menu_id]);
    }

    // ── Updates ────────────────────────────────────────────────────────────────

    private function editItem(int $uid, int $mitem_id, array $data): never
    {
        require_once('include/menu.php');

        $item = $this->requireBookmarkItem($uid, $mitem_id);
        $from = intval($item['mitem_menu_id']);
        $to   = intval($data['menu_id'] ?? 0) ?: $from;

        if ($to !== $from)
            $this->requireBookmarkMenu($uid, $to);

        $stored = self::unescapeStored($item['mitem_link']);
        $url   = array_key_exists('url',   $data) ? trim((string)$data['url'])   : $stored;
        $title = array_key_exists('title', $data) ? trim((string)$data['title'])
                                                  : self::unescapeStored($item['mitem_desc']);

        if (!$url || !$title)
            Response::error(400, 'url and title cannot be empty');

        // MENU_ITEM_ZID is derived from the URL, so re-derive it when the URL
        // changes; everything else (notably MENU_ITEM_CHATROOM) is preserved.
        // Against the decoded form: the client only ever sends decoded URLs, so
        // comparing to the raw column would report a change on every '&'.
        $flags = intval($item['mitem_flags']);
        if ($url !== $stored) {
            $flags = is_matrix_url($url) ? ($flags | MENU_ITEM_ZID) : ($flags & ~MENU_ITEM_ZID);
        }

        menu_edit_item($from, $uid, [
            'mitem_id'      => $mitem_id,
            'mitem_link'    => $url,
            'mitem_desc'    => $title,
            'mitem_flags'   => $flags,
            'mitem_order'   => array_key_exists('order', $data)
                ? intval($data['order']) : intval($item['mitem_order']),
            // menu_edit_item() feeds this array to AccessList::set_from_array(),
            // which defaults every missing key to [] — so omitting these would
            // silently make a private bookmark public.
            'contact_allow' => self::parseHashList($item['allow_cid']),
            'group_allow'   => self::parseHashList($item['allow_gid']),
            'contact_deny'  => self::parseHashList($item['deny_cid']),
            'group_deny'    => self::parseHashList($item['deny_gid']),
        ]);

        // A move is an update of mitem_menu_id; menu_edit_item() keys on the old
        // menu, so it cannot do this itself.
        if ($to !== $from) {
            q("UPDATE menu_item SET mitem_menu_id = %d WHERE mitem_id = %d AND mitem_channel_id = %d",
                intval($to), intval($mitem_id), intval($uid));
            menu_sync_packet($uid, get_observer_hash(), $to);
        }
        menu_sync_packet($uid, get_observer_hash(), $from);

        Response::send(['success' => true]);
    }

    private function reorder(int $uid, array $data): never
    {
        require_once('include/menu.php');

        $menu_id = intval($data['menu_id'] ?? 0);
        $ids     = $data['ids'] ?? [];

        if (!$menu_id || !is_array($ids))
            Response::error(400, 'menu_id and ids are required');

        $this->requireBookmarkMenu($uid, $menu_id);

        foreach (array_values($ids) as $i => $id) {
            $item = $this->requireBookmarkItem($uid, intval($id));
            if (intval($item['mitem_menu_id']) !== $menu_id) continue;

            menu_edit_item($menu_id, $uid, [
                'mitem_id'      => intval($id),
                'mitem_link'    => $item['mitem_link'],
                'mitem_desc'    => $item['mitem_desc'],
                'mitem_flags'   => intval($item['mitem_flags']),
                'mitem_order'   => $i,
                'contact_allow' => self::parseHashList($item['allow_cid']),
                'group_allow'   => self::parseHashList($item['allow_gid']),
                'contact_deny'  => self::parseHashList($item['deny_cid']),
                'group_deny'    => self::parseHashList($item['deny_gid']),
            ]);
        }

        menu_sync_packet($uid, get_observer_hash(), $menu_id);

        Response::send(['success' => true]);
    }

    // ── Deletes ────────────────────────────────────────────────────────────────

    private function deleteItem(int $uid, int $mitem_id): never
    {
        require_once('include/menu.php');

        if (!$mitem_id)
            Response::error(400, 'mitem_id required');

        $item    = $this->requireBookmarkItem($uid, $mitem_id);
        $menu_id = intval($item['mitem_menu_id']);

        menu_del_item($menu_id, $uid, $mitem_id);
        menu_sync_packet($uid, get_observer_hash(), $menu_id);

        Response::send(['success' => true]);
    }

    private function deleteFolder(int $uid, int $menu_id): never
    {
        require_once('include/menu.php');

        if (!$menu_id)
            Response::error(400, 'menu_id required');

        $this->requireBookmarkMenu($uid, $menu_id);

        // Sync before the row is gone, so clones can still resolve the menu.
        menu_sync_packet($uid, get_observer_hash(), $menu_id, true);
        menu_delete_id($menu_id, $uid);

        Response::send(['success' => true]);
    }

    // ── Guards / lookups ───────────────────────────────────────────────────────

    /**
     * Must be the exact .apd name — system_app_installed() hashes it, so a
     * lowercase slug silently reports "not installed".
     */
    private function requireApp(int $uid): void
    {
        if (!Apps::system_app_installed($uid, 'Bookmarks'))
            Response::error(403, 'Bookmarks app is not installed');
    }

    /** A menu of this channel that is actually a bookmark folder. */
    private function requireBookmarkMenu(int $uid, int $menu_id): array
    {
        $r = q("SELECT * FROM menu WHERE menu_id = %d AND menu_channel_id = %d AND (menu_flags & %d) LIMIT 1",
            intval($menu_id), intval($uid), MENU_BOOKMARK);

        if (!$r)
            Response::error(404, 'Bookmark folder not found');

        return $r[0];
    }

    /**
     * An item of this channel sitting in a bookmark folder. The menu join is the
     * point: without it any mitem_id of the channel's — a nav menu entry
     * included — would be editable and deletable through these routes.
     */
    private function requireBookmarkItem(int $uid, int $mitem_id): array
    {
        $r = q("SELECT mi.* FROM menu_item mi
                JOIN menu m ON m.menu_id = mi.mitem_menu_id
                WHERE mi.mitem_id = %d AND mi.mitem_channel_id = %d AND (m.menu_flags & %d)
                LIMIT 1",
            intval($mitem_id), intval($uid), MENU_BOOKMARK);

        if (!$r)
            Response::error(404, 'Bookmark not found');

        return $r[0];
    }

    /** The viewer's own copy of a post, by uuid or mid, with its terms hydrated. */
    private function loadOwnItem(int $uid, string $ref): ?array
    {
        if (str_starts_with($ref, 'b64.'))
            $ref = unpack_link_id($ref);

        $col = (str_contains($ref, '/') || str_contains($ref, ':')) ? 'mid' : 'uuid';

        // Pass the uid so item_normal() takes its is_owner branch — you can
        // bookmark out of your own moderated/delayed posts, as core allows.
        $r = q("SELECT * FROM item WHERE $col = '%s' AND uid = %d " . item_normal($uid) . " LIMIT 1",
            dbesc($ref), intval($uid));

        if (!$r) return null;

        $r = fetch_post_tags($r);
        return $r[0];
    }

    /**
     * bookmark_add() returns menu_add_item()'s result, not an id, so read the row
     * back. Scoped to the target menu — the same URL legitimately lives in more
     * than one folder.
     */
    private function findItemId(int $uid, string $url, array $opts): ?int
    {
        $sql = "SELECT mitem_id FROM menu_item mi
                JOIN menu m ON m.menu_id = mi.mitem_menu_id
                WHERE mi.mitem_link = '%s' AND mi.mitem_channel_id = %d AND (m.menu_flags & %d)";
        $args = [dbesc(escape_tags($url)), intval($uid), MENU_BOOKMARK];

        if ($opts['menu_id']) {
            $sql .= " AND mi.mitem_menu_id = %d";
            $args[] = intval($opts['menu_id']);
        } elseif ($opts['menu_name']) {
            $sql .= " AND m.menu_name = '%s'";
            $args[] = dbesc(escape_tags($opts['menu_name']));
        }

        $r = q($sql . " ORDER BY mi.mitem_id DESC LIMIT 1", ...$args);

        return $r ? intval($r[0]['mitem_id']) : null;
    }
}
