<?php
namespace Theme\Solidified\Api\Handlers;

use Theme\Solidified\Api\Auth;
use Theme\Solidified\Api\Response;

require_once 'include/items.php';

class Webpages
{
    // GET /api/webpages/:nick               → list webpages  (owner only: write_pages)
    // GET /api/webpages/:nick?pagelink=…    → render page    (public: view_pages)
    // GET /api/webpages/:nick?mid=…         → render page    (public: view_pages)
    public function get(): void
    {
        $nick = \App::$argv[2] ?? '';
        if (!$nick) {
            Response::error(400, 'No channel specified');
        }

        $r = q(
            "SELECT channel_id FROM channel WHERE channel_address = '%s' LIMIT 1",
            dbesc($nick)
        );
        if (!$r) {
            Response::error(404, 'Channel not found');
        }
        $owner = intval($r[0]['channel_id']);

        $observer = \App::get_observer();
        $ob_hash  = $observer ? $observer['xchan_hash'] : '';
        $perms    = get_all_perms($owner, $ob_hash);

        // ── Public page fetch by pagelink ──────────────────────────────────────
        if (!empty($_GET['pagelink'])) {
            if (!$perms['view_pages']) {
                Response::error(403, 'Permission denied');
            }

            $pagelink      = urlencode($_GET['pagelink']);
            $lang_pagelink = urlencode(\App::$language . '/' . $_GET['pagelink']);
            $sql_extra     = item_permissions_sql($owner);

            $p = q(
                "SELECT item.* FROM item
                 LEFT JOIN iconfig ON iconfig.iid = item.id
                 WHERE item.uid = %d
                   AND iconfig.cat = 'system'
                   AND iconfig.k = 'WEBPAGE'
                   AND (iconfig.v = '%s' OR iconfig.v = '%s')
                   AND item_type = %d
                   AND item.item_delayed = 0
                   $sql_extra
                 ORDER BY iconfig.v DESC
                 LIMIT 1",
                $owner,
                dbesc($lang_pagelink),
                dbesc($pagelink),
                intval(ITEM_TYPE_WEBPAGE)
            );

            if (!$p) {
                Response::error(404, 'Page not found');
            }

            xchan_query($p, true);
            $p = fetch_post_tags($p, true);
            Response::send($this->formatDetail($p[0]));
        }

        // ── Public page fetch by mid ───────────────────────────────────────────
        if (!empty($_GET['mid'])) {
            if (!$perms['view_pages']) {
                Response::error(403, 'Permission denied');
            }

            $sql_extra = item_permissions_sql($owner);

            $p = q(
                "SELECT item.* FROM item
                 LEFT JOIN iconfig ON iconfig.iid = item.id
                 WHERE item.uid = %d
                   AND item.mid = '%s'
                   AND iconfig.cat = 'system'
                   AND iconfig.k = 'WEBPAGE'
                   AND item_type = %d
                   $sql_extra
                 LIMIT 1",
                $owner,
                dbesc($_GET['mid']),
                intval(ITEM_TYPE_WEBPAGE)
            );

            if (!$p) {
                Response::error(404, 'Page not found');
            }

            xchan_query($p, true);
            $p = fetch_post_tags($p, true);
            Response::send($this->formatDetail($p[0]));
        }

        // ── Page fetch by iid — owner only (for SPA editor) ──────────────────
        if (!empty($_GET['iid'])) {
            Auth::requireLocalGet();
            if (!$perms['write_pages']) {
                Response::error(403, 'Permission denied');
            }

            $p = q(
                "SELECT item.* FROM item
                 LEFT JOIN iconfig ON iconfig.iid = item.id
                 WHERE item.uid = %d
                   AND item.id = %d
                   AND iconfig.cat = 'system'
                   AND iconfig.k = 'WEBPAGE'
                   AND item_type = %d
                 LIMIT 1",
                $owner,
                intval($_GET['iid']),
                intval(ITEM_TYPE_WEBPAGE)
            );

            if (!$p) {
                Response::error(404, 'Page not found');
            }

            xchan_query($p, true);
            $p = fetch_post_tags($p, true);
            Response::send($this->formatDetail($p[0]));
        }

        // ── List webpages — owner only ─────────────────────────────────────────
        // Listing requires write_pages (you need edit/delete capability to use the list)
        Auth::requireLocalGet();

        if (!$perms['write_pages']) {
            Response::error(403, 'Permission denied');
        }

        $sql_extra = item_permissions_sql($owner);

        $rows = q(
            "SELECT iconfig.iid, iconfig.v AS pagelink,
                    item.mid, item.title, item.mimetype,
                    item.created, item.edited,
                    item.allow_cid, item.allow_gid, item.deny_cid, item.deny_gid
             FROM iconfig
             LEFT JOIN item ON iconfig.iid = item.id
             WHERE item.uid = %d
               AND iconfig.cat = 'system'
               AND iconfig.k = 'WEBPAGE'
               AND item_type = %d
               $sql_extra
             ORDER BY item.created DESC",
            $owner,
            intval(ITEM_TYPE_WEBPAGE)
        );

        $pages = [];
        foreach (($rows ?: []) as $row) {
            $pagelink   = urldecode(str_replace('%2f', '/', $row['pagelink']));
            $is_private = (
                strlen($row['allow_cid']) ||
                strlen($row['allow_gid']) ||
                strlen($row['deny_cid'])  ||
                strlen($row['deny_gid'])
            );
            $pages[] = [
                'iid'        => intval($row['iid']),
                'mid'        => $row['mid'],
                'title'      => $row['title'],
                'pagelink'   => $pagelink,
                'mimetype'   => $row['mimetype'],
                'created'    => $row['created'],
                'edited'     => $row['edited'],
                'is_private' => (bool) $is_private,
                'view_url'   => z_root() . '/page/' . $nick . '/' . $pagelink,
                'edit_url'   => z_root() . '/editwebpage/' . $nick . '/' . intval($row['iid']),
            ];
        }

        Response::send($pages, ['channel' => $nick, 'count' => count($pages)]);
    }

    // POST /api/webpages
    // Body (JSON): { "action": "delete", "iid": 123 }
    public function post(): void
    {
        $uid  = Auth::requireLocalJson();
        $body = \Theme\Solidified\Api\Auth::$parsedBody;

        if (($body['action'] ?? '') === 'delete') {
            $iid = intval($body['iid'] ?? 0);
            if (!$iid) {
                Response::error(400, 'No item id');
            }

            $r = q(
                'SELECT id FROM item WHERE id = %d AND uid = %d LIMIT 1',
                $iid,
                $uid
            );
            if (!$r) {
                Response::error(404, 'Not found or permission denied');
            }

            require_once 'include/items.php';
            drop_item($iid, false);

            Response::send(['status' => 'ok']);
        }

        Response::error(400, 'Unknown action');
    }

    private function formatDetail(array $item): array
    {
        // Extract the WEBPAGE pagelink from iconfig (attached by fetch_post_tags / xchan_query)
        $pagelink = '';
        if (!empty($item['iconfig']) && is_array($item['iconfig'])) {
            foreach ($item['iconfig'] as $cfg) {
                if (($cfg['cat'] ?? '') === 'system' && ($cfg['k'] ?? '') === 'WEBPAGE') {
                    $pagelink = urldecode($cfg['v']);
                    break;
                }
            }
        }

        return [
            'mid'      => $item['mid'],
            'title'    => $item['title'],
            'body'     => $item['body'],
            'mimetype' => $item['mimetype'],
            'slug'     => $pagelink,
            'created'  => $item['created'],
            'edited'   => $item['edited'],
        ];
    }
}
