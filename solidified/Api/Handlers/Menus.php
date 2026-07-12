<?php
namespace Theme\Solidified\Api\Handlers;

use Theme\Solidified\Api\Auth;
use Theme\Solidified\Api\Response;

require_once 'include/menu.php';
require_once 'include/security.php';

/**
 * Hubzilla menus (menu / menu_item tables — same data as the Menus app and
 * Comanche webpage menus) exposed to the SPA menu widgets and the webpages
 * menu manager.
 *
 *   GET  /api/menus                          → own menus (owner)
 *   GET  /api/menus/:id                      → one menu + raw flat items (owner, for the editor)
 *   GET  /api/menus/:nick/:name              → resolved nested item tree (observer-permission filtered)
 *
 *   POST /api/menus/create                   { name, desc? }
 *   POST /api/menus/:id/edit                 { name, desc? }
 *   POST /api/menus/:id/delete
 *   POST /api/menus/:id/items/create         { label, link, order?, zid?, newwin?, scope?, contact_allow?, group_allow?, contact_deny?, group_deny? }
 *   POST /api/menus/:id/items/:iid/edit      { label, link, order?, zid?, newwin?, scope?, contact_allow?, group_allow?, contact_deny?, group_deny? }
 *   POST /api/menus/:id/items/:iid/delete
 *
 * Nesting convention: Hubzilla menu items are flat, so an item whose link is
 * "menu:<other-menu-name>" is expanded into a submenu holding that menu's
 * items (recursively, depth-capped, cycle-guarded). Menu items carry a
 * per-item ACL (same allow_cid/allow_gid/deny_cid/deny_gid columns the stock
 * Menus app uses) — resolution goes through permissions_sql() for the
 * observer.
 *
 * Item ACL: scope is "public" (default), "connections" (the channel's
 * default post ACL), or "custom" (explicit contact_allow/group_allow/
 * contact_deny/group_deny arrays of xchan hashes / group ids). Omitting
 * `scope` on an edit leaves the item's existing ACL untouched.
 */
class Menus
{
    /** Top level + two nested submenu levels. */
    private const MAX_DEPTH = 3;

    public function get(): void
    {
        $argc = count(\App::$argv);

        if ($argc >= 4) {
            $this->getTree(\App::$argv[2], \App::$argv[3]);
        }

        $uid = Auth::requireLocalGet();

        if ($argc === 3) {
            $this->getRaw($uid, intval(\App::$argv[2]));
        }

        // Bookmark/system menus are managed by Hubzilla itself — only
        // plain (webpage-style) menus are offered for widgets and editing.
        $menus = q(
            "SELECT m.menu_id, m.menu_name, m.menu_desc, m.menu_created, m.menu_edited,
                    (SELECT COUNT(*) FROM menu_item mi WHERE mi.mitem_menu_id = m.menu_id) AS item_count
             FROM menu m
             WHERE m.menu_channel_id = %d AND m.menu_flags = 0
             ORDER BY m.menu_name ASC",
            intval($uid)
        );

        Response::send([
            'menus' => array_map(fn($m) => [
                'id'         => intval($m['menu_id']),
                'name'       => $m['menu_name'],
                'desc'       => $m['menu_desc'],
                'created'    => $m['menu_created'],
                'edited'     => $m['menu_edited'],
                'item_count' => intval($m['item_count']),
            ], $menus ?: []),
        ]);
    }

    // ── GET /api/menus/:id — raw flat items for the CRUD editor ────────────
    private function getRaw(int $uid, int $menu_id): never
    {
        $menu = q(
            "SELECT * FROM menu WHERE menu_id = %d AND menu_channel_id = %d AND menu_flags = 0 LIMIT 1",
            intval($menu_id),
            intval($uid)
        );
        if (!$menu)
            Response::error(404, 'Menu not found');

        $items = q(
            "SELECT * FROM menu_item WHERE mitem_menu_id = %d AND mitem_channel_id = %d
             ORDER BY mitem_order ASC, mitem_desc ASC",
            intval($menu_id),
            intval($uid)
        );

        Response::send([
            'menu'  => [
                'id'   => intval($menu[0]['menu_id']),
                'name' => $menu[0]['menu_name'],
                'desc' => $menu[0]['menu_desc'],
            ],
            'items' => array_map(fn($it) => [
                'id'         => intval($it['mitem_id']),
                'label'      => $it['mitem_desc'],
                'link'       => $it['mitem_link'],
                'order'      => intval($it['mitem_order']),
                'zid'        => (bool)(intval($it['mitem_flags']) & MENU_ITEM_ZID),
                'newwin'     => (bool)(intval($it['mitem_flags']) & MENU_ITEM_NEWWIN),
                'locked'     => (bool)($it['allow_cid'] || $it['allow_gid'] || $it['deny_cid'] || $it['deny_gid']),
                'allow_cid'  => expand_acl($it['allow_cid']),
                'allow_gid'  => expand_acl($it['allow_gid']),
                'deny_cid'   => expand_acl($it['deny_cid']),
                'deny_gid'   => expand_acl($it['deny_gid']),
            ], $items ?: []),
        ]);
    }

    // ── GET /api/menus/:nick/:name — observer-filtered nested tree ─────────
    private function getTree(string $nick, string $name): never
    {
        $r = q(
            "SELECT channel_id FROM channel WHERE channel_address = '%s' LIMIT 1",
            dbesc($nick)
        );
        if (!$r)
            Response::error(404, 'Channel not found');
        $owner = intval($r[0]['channel_id']);

        $observer = \App::get_observer();
        $ob_hash  = $observer ? $observer['xchan_hash'] : '';

        if (!perm_is_allowed($owner, $ob_hash, 'view_pages'))
            Response::error(403, 'Permission denied');

        $menu = q(
            "SELECT menu_id, menu_name, menu_desc FROM menu
             WHERE menu_channel_id = %d AND menu_name = '%s' AND menu_flags = 0 LIMIT 1",
            intval($owner),
            dbesc($name)
        );
        if (!$menu)
            Response::error(404, 'Menu not found');

        $visited = [strtolower($name)];
        $items = $this->resolveItems($owner, $ob_hash, intval($menu[0]['menu_id']), 1, $visited);

        Response::send([
            'name'  => $menu[0]['menu_name'],
            'desc'  => $menu[0]['menu_desc'],
            'items' => $items,
        ]);
    }

    private function resolveItems(int $owner, string $ob_hash, int $menu_id, int $depth, array &$visited): array
    {
        $sql_options = permissions_sql($owner, $ob_hash);

        $rows = q(
            "SELECT * FROM menu_item WHERE mitem_menu_id = %d AND mitem_channel_id = %d
             $sql_options
             ORDER BY mitem_order ASC, mitem_desc ASC",
            intval($menu_id),
            intval($owner)
        );

        $out = [];
        foreach (($rows ?: []) as $it) {
            $flags = intval($it['mitem_flags']);
            $link  = trim($it['mitem_link']);

            if (str_starts_with(strtolower($link), 'menu:')) {
                $sub_name = trim(substr($link, 5));
                $sub_key  = strtolower($sub_name);
                if ($depth >= self::MAX_DEPTH || !$sub_name || in_array($sub_key, $visited, true))
                    continue;

                $sub = q(
                    "SELECT menu_id FROM menu
                     WHERE menu_channel_id = %d AND menu_name = '%s' AND menu_flags = 0 LIMIT 1",
                    intval($owner),
                    dbesc($sub_name)
                );
                if (!$sub)
                    continue;

                $visited[] = $sub_key;
                $children = $this->resolveItems($owner, $ob_hash, intval($sub[0]['menu_id']), $depth + 1, $visited);
                if ($children) {
                    $out[] = [
                        'label' => $it['mitem_desc'],
                        'items' => $children,
                    ];
                }
                continue;
            }

            $entry = [
                'label' => $it['mitem_desc'],
                'url'   => ($flags & MENU_ITEM_ZID) ? zid($link) : $link,
            ];
            if ($flags & MENU_ITEM_NEWWIN)
                $entry['newwin'] = true;
            $out[] = $entry;
        }

        return $out;
    }

    // ── POST dispatch ───────────────────────────────────────────────────────
    public function post(): void
    {
        $uid  = Auth::requireLocalJson();
        $body = Auth::$parsedBody;

        $arg2 = \App::$argv[2] ?? '';
        $arg3 = \App::$argv[3] ?? '';

        if ($arg2 === 'create') {
            $this->createMenu($uid, $body);
        }

        $menu_id = intval($arg2);
        if (!$menu_id)
            Response::error(400, 'Invalid menu id');

        $menu = q(
            "SELECT * FROM menu WHERE menu_id = %d AND menu_channel_id = %d AND menu_flags = 0 LIMIT 1",
            intval($menu_id),
            intval($uid)
        );
        if (!$menu)
            Response::error(404, 'Menu not found');

        match ($arg3) {
            'edit'   => $this->editMenu($uid, $menu[0], $body),
            'delete' => $this->deleteMenu($uid, $menu_id),
            'items'  => $this->postItem($uid, $menu_id, $body),
            default  => Response::error(400, 'Unknown action'),
        };
    }

    private function createMenu(int $uid, array $body): never
    {
        $name = trim((string)($body['name'] ?? ''));
        if (!$name)
            Response::error(400, 'Menu name required');

        $menu_id = menu_create([
            'menu_name'       => $name,
            'menu_desc'       => trim((string)($body['desc'] ?? '')),
            'menu_flags'      => 0,
            'menu_channel_id' => $uid,
        ]);
        if (!$menu_id)
            Response::error(400, 'Unable to create menu (duplicate name?)');

        menu_sync_packet($uid, get_observer_hash(), $menu_id);
        Response::send(['id' => intval($menu_id)]);
    }

    private function editMenu(int $uid, array $menu, array $body): never
    {
        $name = trim((string)($body['name'] ?? ''));
        if (!$name)
            Response::error(400, 'Menu name required');

        $r = menu_edit([
            'menu_id'         => intval($menu['menu_id']),
            'menu_name'       => $name,
            'menu_desc'       => trim((string)($body['desc'] ?? '')),
            'menu_flags'      => 0,
            'menu_channel_id' => $uid,
        ]);
        if (!$r)
            Response::error(400, 'Unable to update menu (duplicate name?)');

        menu_sync_packet($uid, get_observer_hash(), intval($menu['menu_id']));
        Response::send(['success' => true]);
    }

    private function deleteMenu(int $uid, int $menu_id): never
    {
        menu_sync_packet($uid, get_observer_hash(), $menu_id, true);
        menu_delete_id($menu_id, $uid);
        Response::send(['success' => true]);
    }

    // ── POST /api/menus/:id/items/… ─────────────────────────────────────────
    private function postItem(int $uid, int $menu_id, array $body): never
    {
        $arg4 = \App::$argv[4] ?? '';
        $arg5 = \App::$argv[5] ?? '';

        if ($arg4 === 'create') {
            $arr = $this->itemFields($body);
            $arr += $this->resolveAcl($uid, (string)($body['scope'] ?? 'public'), $body);
            if (!menu_add_item($menu_id, $uid, $arr))
                Response::error(400, 'Unable to add menu item');
            menu_sync_packet($uid, get_observer_hash(), $menu_id);
            Response::send(['success' => true]);
        }

        $mitem_id = intval($arg4);
        $item = q(
            "SELECT * FROM menu_item WHERE mitem_id = %d AND mitem_menu_id = %d AND mitem_channel_id = %d LIMIT 1",
            intval($mitem_id),
            intval($menu_id),
            intval($uid)
        );
        if (!$item)
            Response::error(404, 'Menu item not found');

        if ($arg5 === 'delete') {
            menu_del_item($menu_id, $uid, $mitem_id);
            menu_sync_packet($uid, get_observer_hash(), $menu_id);
            Response::send(['success' => true]);
        }

        if ($arg5 !== 'edit')
            Response::error(400, 'Unknown action');

        $arr = $this->itemFields($body);
        $arr['mitem_id'] = $mitem_id;
        // menu_edit_item() rebuilds the ACL from the request — a request that
        // doesn't touch ACL must feed the stored ACL back in expanded form,
        // or editing would silently make the item public.
        $arr += array_key_exists('scope', $body)
            ? $this->resolveAcl($uid, (string)$body['scope'], $body)
            : [
                'contact_allow' => expand_acl($item[0]['allow_cid']),
                'group_allow'   => expand_acl($item[0]['allow_gid']),
                'contact_deny'  => expand_acl($item[0]['deny_cid']),
                'group_deny'    => expand_acl($item[0]['deny_gid']),
            ];

        if (!menu_edit_item($menu_id, $uid, $arr))
            Response::error(400, 'Unable to update menu item');
        menu_sync_packet($uid, get_observer_hash(), $menu_id);
        Response::send(['success' => true]);
    }

    /** @return array mitem_* fields validated from the request body */
    private function itemFields(array $body): array
    {
        $label = trim((string)($body['label'] ?? ''));
        $link  = trim((string)($body['link'] ?? ''));
        if (!$label)
            Response::error(400, 'Item label required');
        if (!$link)
            Response::error(400, 'Item link required');

        $flags = 0;
        if (!empty($body['zid']))
            $flags |= MENU_ITEM_ZID;
        if (!empty($body['newwin']))
            $flags |= MENU_ITEM_NEWWIN;

        return [
            'mitem_desc'  => $label,
            'mitem_link'  => $link,
            'mitem_order' => intval($body['order'] ?? 0),
            'mitem_flags' => $flags,
        ];
    }

    /**
     * @return array contact_allow/group_allow/contact_deny/group_deny fields
     *   (plain arrays of xchan hashes / group ids) for menu_add_item() /
     *   menu_edit_item(), which build the AccessList internally.
     */
    private function resolveAcl(int $uid, string $scope, array $body): array
    {
        if ($scope === 'custom') {
            return [
                'contact_allow' => is_array($body['contact_allow'] ?? null) ? $body['contact_allow'] : [],
                'group_allow'   => is_array($body['group_allow']   ?? null) ? $body['group_allow']   : [],
                'contact_deny'  => is_array($body['contact_deny']  ?? null) ? $body['contact_deny']  : [],
                'group_deny'    => is_array($body['group_deny']    ?? null) ? $body['group_deny']    : [],
            ];
        }

        if ($scope === 'connections') {
            $channel = q(
                "SELECT channel_allow_cid, channel_allow_gid, channel_deny_cid, channel_deny_gid
                 FROM channel WHERE channel_id = %d LIMIT 1",
                intval($uid)
            );
            if ($channel) {
                return [
                    'contact_allow' => expand_acl($channel[0]['channel_allow_cid']),
                    'group_allow'   => expand_acl($channel[0]['channel_allow_gid']),
                    'contact_deny'  => expand_acl($channel[0]['channel_deny_cid']),
                    'group_deny'    => expand_acl($channel[0]['channel_deny_gid']),
                ];
            }
        }

        // 'public' (default/fallback): fully open, no ACL.
        return ['contact_allow' => [], 'group_allow' => [], 'contact_deny' => [], 'group_deny' => []];
    }
}
