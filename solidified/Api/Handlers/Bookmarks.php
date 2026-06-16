<?php
/**
 * Theme\Solidified\Api\Handlers\Bookmarks
 *
 * Routes:
 *   GET  /api/bookmarks        → all bookmark menus + items for local user
 *   GET  /api/bookmarks/chat   → chatroom bookmarks only
 *   POST /api/bookmarks        → add bookmark { url, title, ischat? }
 *   DELETE /api/bookmarks/:id  → remove a bookmark item by mitem_id
 */

namespace Theme\Solidified\Api\Handlers;

use App;
use Theme\Solidified\Api\Auth;
use Theme\Solidified\Api\Response;

class Bookmarks
{
    // Hubzilla boot.php constants (0x0002, 0x0004)
    private const MENU_BOOKMARK      = 2;
    private const MENU_ITEM_CHATROOM = 4;

    public function get(): void
    {
        $uid     = Auth::requireLocal();
        $subpath = \App::$argv[2] ?? '';

        if ($subpath === 'chat') {
            $this->getChatBookmarks($uid);
        } else {
            $this->getAllBookmarks($uid);
        }
    }

    public function post(): void
    {
        $uid  = Auth::requireLocalJson();
        $data = Auth::$parsedBody;

        $url    = trim($data['url']   ?? '');
        $title  = trim($data['title'] ?? '');
        $ischat = !empty($data['ischat']) ? 1 : 0;

        if (!$url || !$title)
            Response::error(400, 'url and title are required');

        require_once('include/bookmarks.php');

        $channel = App::get_channel();
        bookmark_add($channel, $channel, ['url' => $url, 'term' => $title], 0, ['ischat' => $ischat]);

        $r = q(
            "SELECT mitem_id FROM menu_item WHERE mitem_link = '%s' AND mitem_channel_id = %d ORDER BY mitem_id DESC LIMIT 1",
            dbesc($url),
            intval($uid)
        );

        Response::send([
            'success'  => true,
            'mitem_id' => $r ? intval($r[0]['mitem_id']) : null,
        ]);
    }

    public function delete(): void
    {
        $uid      = Auth::requireLocalJson();
        $mitem_id = intval(\App::$argv[2] ?? 0);

        if (!$mitem_id)
            Response::error(400, 'mitem_id required');

        q(
            "DELETE FROM menu_item WHERE mitem_id = %d AND mitem_channel_id = %d",
            intval($mitem_id),
            intval($uid)
        );

        Response::send(['success' => true]);
    }

    // ── Private ────────────────────────────────────────────────────────────────

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
            self::MENU_BOOKMARK,
            self::MENU_ITEM_CHATROOM
        );

        $items = [];
        foreach (($r ?: []) as $row) {
            $items[] = [
                'id'    => intval($row['mitem_id']),
                'url'   => $row['mitem_link'],
                'title' => $row['mitem_desc'],
            ];
        }

        Response::send(['bookmarks' => $items]);
    }

    private function getAllBookmarks(int $uid): void
    {
        $menus = q(
            "SELECT menu_id, menu_name
             FROM menu
             WHERE menu_channel_id = %d AND (menu_flags & %d)
             ORDER BY menu_name ASC",
            intval($uid),
            self::MENU_BOOKMARK
        );

        $result = [];
        foreach (($menus ?: []) as $menu) {
            $items = q(
                "SELECT mitem_id, mitem_link, mitem_desc, mitem_flags
                 FROM menu_item
                 WHERE mitem_menu_id = %d AND mitem_channel_id = %d
                 ORDER BY mitem_order ASC, mitem_desc ASC",
                intval($menu['menu_id']),
                intval($uid)
            );

            $item_list = [];
            foreach (($items ?: []) as $item) {
                $item_list[] = [
                    'id'      => intval($item['mitem_id']),
                    'url'     => $item['mitem_link'],
                    'title'   => $item['mitem_desc'],
                    'is_chat' => (bool)(intval($item['mitem_flags']) & self::MENU_ITEM_CHATROOM),
                ];
            }

            $result[] = [
                'id'    => intval($menu['menu_id']),
                'name'  => $menu['menu_name'],
                'items' => $item_list,
            ];
        }

        Response::send(['menus' => $result]);
    }
}
