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

        // ── Page fetch by iid — write access required (SPA editor) ───────────
        if (!empty($_GET['iid'])) {
            Auth::requireLoggedIn();
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

        // ── List webpages — write access required ──────────────────────────────
        // Listing requires write_pages (you need edit/delete capability to use the list)
        Auth::requireLoggedIn();

        if (!$perms['write_pages']) {
            Response::error(403, 'Permission denied');
        }

        $sql_extra = item_permissions_sql($owner);

        $rows = q(
            "SELECT iconfig.iid, iconfig.v AS pagelink,
                    item.mid, item.title, item.mimetype,
                    item.created, item.edited,
                    item.allow_cid, item.allow_gid, item.deny_cid, item.deny_gid,
                    item.item_private, item.public_policy
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
                strlen($row['deny_gid'])  ||
                intval($row['item_private'])
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
    // Body (JSON): { "action": "create", "nick": "…", title, summary, body, mimetype, pagetitle, scope, allow_cid[], allow_gid[], deny_cid[], deny_gid[] }
    // Body (JSON): { "action": "update", "nick": "…", uuid, title, summary, body, mimetype, pagetitle, scope, allow_cid[], allow_gid[], deny_cid[], deny_gid[] }
    // Body (JSON): { "action": "delete", "nick": "…", "iid": 123 }
    public function post(): void
    {
        $obs_hash = Auth::requireLoggedInJson();
        $body     = \Theme\Solidified\Api\Auth::$parsedBody;

        $nick = trim($body['nick'] ?? '');
        if (!$nick) {
            Response::error(400, 'nick required');
        }
        $owner = channelx_by_nick($nick);
        if (!$owner) {
            Response::error(404, 'Channel not found');
        }
        $uid = intval($owner['channel_id']);

        // Any observer (local or remote) with write_pages ACL on this channel
        // may create/update/delete its webpages — not just the owner.
        if (!perm_is_allowed($uid, $obs_hash, 'write_pages')) {
            Response::error(403, 'Permission denied');
        }

        if (($body['action'] ?? '') === 'create') {
            $this->createWebpage($owner, $obs_hash, $body);
            return;
        }

        if (($body['action'] ?? '') === 'update') {
            $this->updateWebpage($uid, $body);
            return;
        }

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
            return;
        }

        Response::error(400, 'Unknown action');
    }

    private function createWebpage(array $owner, string $obs_hash, array $body): void
    {
        require_once 'include/items.php';

        $uid = intval($owner['channel_id']);

        $title     = trim($body['title']     ?? '');
        $summary   = trim($body['summary']   ?? '');
        $content   = trim($body['body']      ?? '');
        $mimetype  = $body['mimetype']        ?? 'text/bbcode';
        $pagetitle = trim($body['pagetitle'] ?? '');
        $scope     = $body['scope']           ?? 'public';

        if (!$content) {
            Response::error(400, 'body is required');
        }

        [$allow_cid, $allow_gid, $deny_cid, $deny_gid, $item_private, $public_policy] =
            $this->resolveWebpageAcl($scope, $body);

        $uuid = item_message_id();
        $mid  = z_root() . '/item/' . $uuid;
        $now  = datetime_convert();

        $datarray = [
            'aid'             => $owner['channel_account_id'],
            'uid'             => $uid,
            'uuid'            => $uuid,
            'mid'             => $mid,
            'parent_mid'      => $mid,
            'thr_parent'      => $mid,
            'owner_xchan'     => $owner['channel_hash'],
            'author_xchan'    => $obs_hash,
            'created'         => $now,
            'edited'          => $now,
            'commented'       => $now,
            'received'        => $now,
            'changed'         => $now,
            'verb'            => 'Create',
            'obj_type'        => 'Note',
            'item_type'       => ITEM_TYPE_WEBPAGE,
            'mimetype'        => $mimetype,
            'title'           => $title,
            'summary'         => $summary,
            'body'            => $content,
            'allow_cid'       => $allow_cid,
            'allow_gid'       => $allow_gid,
            'deny_cid'        => $deny_cid,
            'deny_gid'        => $deny_gid,
            'item_wall'       => 1,
            'item_origin'     => 1,
            'item_thread_top' => 1,
            'item_unseen'     => 0,
            'item_private'    => $item_private,
            'public_policy'   => $public_policy,
            'plink'           => $mid,
        ];

        // Register the WEBPAGE slug in iconfig (read by the page router and listing)
        \Zotlabs\Lib\IConfig::Set($datarray, 'system', 'WEBPAGE',
            ($pagetitle ?: basename($mid)), true);

        $post = item_store($datarray);

        if (!$post['success']) {
            Response::error(500, 'Failed to create webpage');
        }

        $this->assignLayoutTemplate($uid, intval($post['item_id']), $body['layout_template'] ?? null);

        \Zotlabs\Daemon\Master::Summon(['Notifier', 'wall-new', $post['item_id']]);

        Response::send([
            'iid'  => $post['item_id'],
            'uuid' => $uuid,
            'mid'  => $mid,
        ]);
    }

    // Assigns (or clears) the layout template a webpage uses for its right
    // sidebar. Silently ignores an id that isn't one of the owner's current
    // templates — same "stale ids are dropped, not errored" tolerance used
    // for stored widget ids elsewhere, since templates can be deleted out
    // from under a page.
    private function assignLayoutTemplate(int $uid, int $iid, $templateId): void
    {
        if (!is_string($templateId) || $templateId === '') {
            \Zotlabs\Lib\IConfig::Delete($iid, 'spa', 'layout_template');
            return;
        }

        $raw = get_pconfig($uid, 'spa', 'widget_templates', '');
        $decoded = $raw ? json_decode($raw, true) : null;
        $templates = is_array($decoded['templates'] ?? null) ? $decoded['templates'] : [];

        if (isset($templates[$templateId])) {
            \Zotlabs\Lib\IConfig::Set($iid, 'spa', 'layout_template', $templateId);
        } else {
            \Zotlabs\Lib\IConfig::Delete($iid, 'spa', 'layout_template');
        }
    }

    private function updateWebpage(int $uid, array $body): void
    {
        require_once 'include/items.php';

        $uuid      = trim($body['uuid']      ?? '');
        $content   = trim($body['body']      ?? '');
        $title     = trim($body['title']     ?? '');
        $summary   = trim($body['summary']   ?? '');
        $mimetype  = $body['mimetype']        ?? 'text/bbcode';
        $pagetitle = trim($body['pagetitle'] ?? '');
        $scope     = $body['scope']           ?? null;

        if (!$uuid) {
            Response::error(400, 'uuid is required');
        }
        if (!$content) {
            Response::error(400, 'body is required');
        }

        $item = q(
            "SELECT * FROM item WHERE uuid = '%s' AND uid = %d AND item_deleted = 0 LIMIT 1",
            dbesc($uuid), $uid
        );
        if (!$item) {
            Response::error(404, 'Webpage not found or permission denied');
        }

        $iid = intval($item[0]['id']);
        $now = datetime_convert();

        if ($scope !== null) {
            [$allow_cid, $allow_gid, $deny_cid, $deny_gid, $item_private, $public_policy] =
                $this->resolveWebpageAcl($scope, $body);

            q("UPDATE item
               SET body = '%s', title = '%s', summary = '%s', mimetype = '%s',
                   allow_cid = '%s', allow_gid = '%s', deny_cid = '%s', deny_gid = '%s',
                   item_private = %d, public_policy = '%s',
                   edited = '%s', changed = '%s'
               WHERE id = %d AND uid = %d",
                dbesc($content), dbesc($title), dbesc($summary), dbesc($mimetype),
                dbesc($allow_cid), dbesc($allow_gid), dbesc($deny_cid), dbesc($deny_gid),
                $item_private, dbesc($public_policy),
                dbesc($now), dbesc($now), $iid, $uid);
        } else {
            q("UPDATE item
               SET body = '%s', title = '%s', summary = '%s', mimetype = '%s',
                   edited = '%s', changed = '%s'
               WHERE id = %d AND uid = %d",
                dbesc($content), dbesc($title), dbesc($summary), dbesc($mimetype),
                dbesc($now), dbesc($now), $iid, $uid);
        }

        if ($pagetitle) {
            q("UPDATE iconfig SET v = '%s' WHERE iid = %d AND cat = 'system' AND k = 'WEBPAGE'",
                dbesc($pagetitle), $iid);
        }

        if (array_key_exists('layout_template', $body)) {
            $this->assignLayoutTemplate($uid, $iid, $body['layout_template']);
        }

        \Zotlabs\Daemon\Master::Summon(['Notifier', 'edit_post', $iid]);

        Response::send(['success' => true]);
    }

    // Returns [allow_cid, allow_gid, deny_cid, deny_gid, item_private, public_policy]
    private function resolveWebpageAcl(string $scope, array $body): array
    {
        if ($scope === 'connections') {
            // item_private=1 + public_policy='contacts' is the mechanism
            // item_permissions_sql() checks this via scopes_sql()
            return ['', '', '', '', 1, 'contacts'];
        }

        if ($scope === 'custom') {
            $allow_cid = '';
            $allow_gid = '';
            $deny_cid  = '';
            $deny_gid  = '';

            foreach ((array) ($body['allow_cid'] ?? []) as $h) {
                $allow_cid .= '<' . $h . '>';
            }
            foreach ((array) ($body['allow_gid'] ?? []) as $g) {
                $allow_gid .= '<' . $g . '>';
            }
            foreach ((array) ($body['deny_cid'] ?? []) as $h) {
                $deny_cid .= '<' . $h . '>';
            }
            foreach ((array) ($body['deny_gid'] ?? []) as $g) {
                $deny_gid .= '<' . $g . '>';
            }

            $item_private = ($allow_cid || $allow_gid) ? 1 : 0;
            return [$allow_cid, $allow_gid, $deny_cid, $deny_gid, $item_private, ''];
        }

        // public — no ACL restrictions
        return ['', '', '', '', 0, ''];
    }

    private function formatDetail(array $item): array
    {
        // Extract the WEBPAGE pagelink and assigned layout template from
        // iconfig (attached by fetch_post_tags / xchan_query)
        $pagelink = '';
        $layout_template = null;
        if (!empty($item['iconfig']) && is_array($item['iconfig'])) {
            foreach ($item['iconfig'] as $cfg) {
                if (($cfg['cat'] ?? '') === 'system' && ($cfg['k'] ?? '') === 'WEBPAGE') {
                    $pagelink = urldecode($cfg['v']);
                } elseif (($cfg['cat'] ?? '') === 'spa' && ($cfg['k'] ?? '') === 'layout_template') {
                    $layout_template = $cfg['v'];
                }
            }
        }

        return [
            'uuid'            => $item['uuid'],
            'mid'             => $item['mid'],
            'title'           => $item['title'],
            'summary'         => $item['summary'] ?? '',
            'body'            => $item['body'],
            'mimetype'        => $item['mimetype'],
            'slug'            => $pagelink,
            'created'         => $item['created'],
            'edited'          => $item['edited'],
            'item_private'    => intval($item['item_private']),
            'public_policy'   => $item['public_policy'] ?? '',
            'allow_cid'       => self::parseHashList($item['allow_cid'] ?? ''),
            'allow_gid'       => self::parseHashList($item['allow_gid'] ?? ''),
            'deny_cid'        => self::parseHashList($item['deny_cid']  ?? ''),
            'deny_gid'        => self::parseHashList($item['deny_gid']  ?? ''),
            'layout_template' => $layout_template,
        ];
    }

    // Hubzilla stores ACL as "<hash1><hash2>..." — extract the bare hashes.
    private static function parseHashList(string $str): array
    {
        if (!$str) return [];
        preg_match_all('/<([^>]+)>/', $str, $m);
        return $m[1] ?? [];
    }
}
