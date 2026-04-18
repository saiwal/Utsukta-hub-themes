<?php
namespace Zotlabs\Module;

class Webpages_api extends \Zotlabs\Web\Controller
{
    function get()
    {
        if (($_GET['format'] ?? '') !== 'json')
            return;

        require_once ('include/items.php');

        if (!local_channel()) {
            json_return_and_die(['error' => 'Permission denied']);
        }

        $nick = argv(1);
        if (!$nick) {
            json_return_and_die(['error' => 'No channel specified']);
        }

        $r = q("SELECT channel_id FROM channel WHERE channel_address = '%s' LIMIT 1", dbesc($nick));
        if (!$r) {
            json_return_and_die(['error' => 'Channel not found']);
        }
        $owner = intval($r[0]['channel_id']);

        $ob_hash = get_observer_hash();
        $perms = get_all_perms($owner, $ob_hash);
        if (!$perms['write_pages']) {
            json_return_and_die(['error' => 'Permission denied']);
        }

        // Single page fetch: ?format=json&mid=xxx  (returns body for the viewer)
        if (!empty($_GET['mid'])) {
            $mid = $_GET['mid'];
            $sql_extra = item_permissions_sql($owner);
            $p = q("SELECT item.* FROM item
                LEFT JOIN iconfig ON iconfig.iid = item.id
                WHERE item.uid = %d
                  AND item.mid = '%s'
                  AND iconfig.cat = 'system'
                  AND iconfig.k = 'WEBPAGE'
                  AND item_type = %d
                  $sql_extra
                LIMIT 1",
                $owner,
                dbesc($mid),
                intval(ITEM_TYPE_WEBPAGE));
            if (!$p) {
                json_return_and_die(['error' => 'Not found']);
            }
            xchan_query($p, true);
            $p = fetch_post_tags($p, true);
            $item = $p[0];
            /* $arr['replace'] = true; */
            json_return_and_die([
                'mid' => $item['mid'],
                'title' => $item['title'],
                'body' => $item['body'],
                'mimetype' => $item['mimetype'],
                'created' => $item['created'],
                'edited' => $item['edited'],
            ]);
        }

        // List all webpages for this channel
        $sql_extra = item_permissions_sql($owner);
        $rows = q("SELECT iconfig.iid, iconfig.v AS pagelink,
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
            intval(ITEM_TYPE_WEBPAGE));

        $pages = [];
        foreach (($rows ?: []) as $row) {
            $pagelink = str_replace('%2f', '/', $row['pagelink']);
            $is_private = (strlen($row['allow_cid']) ||
                strlen($row['allow_gid']) ||
                strlen($row['deny_cid']) ||
                strlen($row['deny_gid']));
            $pages[] = [
                'iid' => intval($row['iid']),
                'mid' => $row['mid'],
                'title' => $row['title'],
                'pagelink' => urldecode($pagelink),
                'mimetype' => $row['mimetype'],
                'created' => $row['created'],
                'edited' => $row['edited'],
                'is_private' => (bool) $is_private,
                'view_url' => z_root() . '/page/' . $nick . '/' . $pagelink,
                'edit_url' => z_root() . '/editwebpage/' . $nick . '/' . intval($row['iid']),
            ];
        }

        /* $arr['replace'] = true; */
        json_return_and_die(['pages' => $pages, 'channel' => $nick]);
    }

    function post()
    {
        // Called by hooking into webpages module post, or via a dedicated DELETE route.
        // Wire this to: json_return_and_die on ?format=json&action=delete&iid=NNN (POST)
        if (($_GET['format'] ?? '') !== 'json')
            return;
        if (($_POST['action'] ?? '') !== 'delete')
            return;
        if (!local_channel()) {
            json_return_and_die(['error' => 'Permission denied']);
        }

        $iid = intval($_POST['iid'] ?? 0);
        if (!$iid) {
            json_return_and_die(['error' => 'No item id']);
        }

        // Verify ownership
        $r = q('SELECT id FROM item WHERE id = %d AND uid = %d LIMIT 1',
            $iid, local_channel());
        if (!$r) {
            json_return_and_die(['error' => 'Not found or permission denied']);
        }

        // Use Hubzilla's drop_item which handles federation/tombstoning
        require_once ('include/items.php');
        drop_item($iid, false);

        /* $arr['replace'] = true; */
        json_return_and_die(['status' => 'ok']);
    }
}
