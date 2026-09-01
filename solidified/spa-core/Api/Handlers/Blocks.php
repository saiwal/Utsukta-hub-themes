<?php
namespace Utsukta\SpaCore\Api\Handlers;

use Utsukta\SpaCore\Api\Auth;
use Utsukta\SpaCore\Api\Response;
use Utsukta\SpaCore\Api\ContentTypes;

require_once 'include/items.php';

class Blocks
{
    // GET /api/blocks/:nick            → list blocks   (owner only: write_pages)
    // GET /api/blocks/:nick?name=…    → fetch block    (ACL-gated: item_permissions_sql)
    // GET /api/blocks/:nick?iid=…     → fetch block    (owner only: write_pages, for SPA editor)
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

        // ── Fetch by name — ACL-gated, used by the HTML Block widget to render a preset ──
        if (!empty($_GET['name'])) {
            $sql_extra = item_permissions_sql($owner);

            $p = q(
                "SELECT item.* FROM item
                 LEFT JOIN iconfig ON iconfig.iid = item.id
                 WHERE item.uid = %d
                   AND iconfig.cat = 'system'
                   AND iconfig.k = 'BUILDBLOCK'
                   AND iconfig.v = '%s'
                   AND item_type = %d
                   AND item.item_delayed = 0
                   $sql_extra
                 LIMIT 1",
                $owner,
                dbesc($_GET['name']),
                intval(ITEM_TYPE_BLOCK)
            );

            if (!$p) {
                Response::error(404, 'Block not found');
            }

            xchan_query($p, true);
            $p = fetch_post_tags($p, true);
            Response::send($this->formatDetail($p[0]));
        }

        // ── Fetch by iid — write access required (SPA editor) ─────────────────
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
                   AND iconfig.k = 'BUILDBLOCK'
                   AND item_type = %d
                 LIMIT 1",
                $owner,
                intval($_GET['iid']),
                intval(ITEM_TYPE_BLOCK)
            );

            if (!$p) {
                Response::error(404, 'Block not found');
            }

            xchan_query($p, true);
            $p = fetch_post_tags($p, true);
            Response::send($this->formatDetail($p[0]));
        }

        // ── List blocks — write access required ────────────────────────────────
        Auth::requireLoggedIn();

        if (!$perms['write_pages']) {
            Response::error(403, 'Permission denied');
        }

        $sql_extra = item_permissions_sql($owner);

        $rows = q(
            "SELECT iconfig.iid, iconfig.v AS name,
                    item.mid, item.title, item.mimetype,
                    item.created, item.edited,
                    item.allow_cid, item.allow_gid, item.deny_cid, item.deny_gid,
                    item.item_private, item.public_policy
             FROM iconfig
             LEFT JOIN item ON iconfig.iid = item.id
             WHERE item.uid = %d
               AND iconfig.cat = 'system'
               AND iconfig.k = 'BUILDBLOCK'
               AND item_type = %d
               $sql_extra
             ORDER BY item.created DESC",
            $owner,
            intval(ITEM_TYPE_BLOCK)
        );

        $blocks = [];
        foreach (($rows ?: []) as $row) {
            $is_private = (
                strlen($row['allow_cid']) ||
                strlen($row['allow_gid']) ||
                strlen($row['deny_cid'])  ||
                strlen($row['deny_gid'])  ||
                intval($row['item_private'])
            );
            $blocks[] = [
                'iid'        => intval($row['iid']),
                'mid'        => $row['mid'],
                'title'      => $row['title'],
                'name'       => $row['name'],
                'mimetype'   => $row['mimetype'],
                'created'    => $row['created'],
                'edited'     => $row['edited'],
                'is_private' => (bool) $is_private,
            ];
        }

        Response::send($blocks, ['channel' => $nick, 'count' => count($blocks)]);
    }

    // POST /api/blocks
    // Body (JSON): { "action": "create", "nick": "…", title, body, mimetype, name, scope, allow_cid[], allow_gid[], deny_cid[], deny_gid[] }
    // Body (JSON): { "action": "update", "nick": "…", uuid, title, body, mimetype, name, scope, allow_cid[], allow_gid[], deny_cid[], deny_gid[] }
    // Body (JSON): { "action": "delete", "nick": "…", "iid": 123 }
    public function post(): void
    {
        $obs_hash = Auth::requireLoggedInJson();
        $body     = \Utsukta\SpaCore\Api\Auth::$parsedBody;

        $nick = trim($body['nick'] ?? '');
        if (!$nick) {
            Response::error(400, 'nick required');
        }
        $owner = channelx_by_nick($nick);
        if (!$owner) {
            Response::error(404, 'Channel not found');
        }
        $uid = intval($owner['channel_id']);

        if (!perm_is_allowed($uid, $obs_hash, 'write_pages')) {
            Response::error(403, 'Permission denied');
        }

        if (($body['action'] ?? '') === 'create') {
            $this->createBlock($owner, $obs_hash, $body);
            return;
        }

        if (($body['action'] ?? '') === 'update') {
            $this->updateBlock($uid, $body, $owner['channel_hash']);
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

    private function createBlock(array $owner, string $obs_hash, array $body): void
    {
        require_once 'include/items.php';

        $uid = intval($owner['channel_id']);

        // ponytail: no service-class quota check here — EnforcesServiceClass's
        // two modes (webpage count / item_wall+item_normal count) don't
        // represent a block quota (item_normal() excludes item_type=1), so a
        // borrowed check would silently never trigger. Add a real
        // ITEM_TYPE_BLOCK-counting mode to that trait if block quotas matter.
        $title    = trim($body['title'] ?? '');
        $content  = trim($body['body']  ?? '');
        $mimetype = ContentTypes::validate($body['mimetype'] ?? null);
        $name     = trim($body['name']  ?? '');
        $scope    = $body['scope']       ?? 'public';

        if (!$content) {
            Response::error(400, 'body is required');
        }

        [$allow_cid, $allow_gid, $deny_cid, $deny_gid, $item_private, $public_policy] =
            $this->resolveBlockAcl($scope, $body, $owner['channel_hash']);

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
            'item_type'       => ITEM_TYPE_BLOCK,
            'mimetype'        => $mimetype,
            'title'           => $title,
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

        // Register the BUILDBLOCK name in iconfig (read by the widget lookup and listing)
        \Zotlabs\Lib\IConfig::Set($datarray, 'system', 'BUILDBLOCK',
            ($name ?: basename($mid)), true);

        $post = item_store($datarray);

        if (!$post['success']) {
            Response::error(500, 'Failed to create block');
        }

        Response::send([
            'iid'  => $post['item_id'],
            'uuid' => $uuid,
            'mid'  => $mid,
        ]);
    }

    private function updateBlock(int $uid, array $body, string $ownerHash): void
    {
        require_once 'include/items.php';

        $uuid     = trim($body['uuid']  ?? '');
        $content  = trim($body['body']  ?? '');
        $title    = trim($body['title'] ?? '');
        $mimetype = ContentTypes::validate($body['mimetype'] ?? null);
        $name     = trim($body['name']  ?? '');
        $scope    = $body['scope']       ?? null;

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
            Response::error(404, 'Block not found or permission denied');
        }

        $iid = intval($item[0]['id']);
        $now = datetime_convert();

        // Same z_input_filter() sanitization Webpages.php applies before a
        // direct-to-row update — see that class for the full rationale.
        require_once('include/text.php');
        $content = z_input_filter($content, $mimetype, channel_codeallowed($uid));

        if ($scope !== null) {
            [$allow_cid, $allow_gid, $deny_cid, $deny_gid, $item_private, $public_policy] =
                $this->resolveBlockAcl($scope, $body, $ownerHash);

            q("UPDATE item
               SET body = '%s', title = '%s', mimetype = '%s',
                   allow_cid = '%s', allow_gid = '%s', deny_cid = '%s', deny_gid = '%s',
                   item_private = %d, public_policy = '%s',
                   edited = '%s', changed = '%s'
               WHERE id = %d AND uid = %d",
                dbesc($content), dbesc($title), dbesc($mimetype),
                dbesc($allow_cid), dbesc($allow_gid), dbesc($deny_cid), dbesc($deny_gid),
                $item_private, dbesc($public_policy),
                dbesc($now), dbesc($now), $iid, $uid);
        } else {
            q("UPDATE item
               SET body = '%s', title = '%s', mimetype = '%s',
                   edited = '%s', changed = '%s'
               WHERE id = %d AND uid = %d",
                dbesc($content), dbesc($title), dbesc($mimetype),
                dbesc($now), dbesc($now), $iid, $uid);
        }

        if ($name) {
            q("UPDATE iconfig SET v = '%s' WHERE iid = %d AND cat = 'system' AND k = 'BUILDBLOCK'",
                dbesc($name), $iid);
        }

        Response::send(['success' => true]);
    }

    // Returns [allow_cid, allow_gid, deny_cid, deny_gid, item_private, public_policy]
    // Identical to Webpages.php::resolveWebpageAcl() — kept as its own copy
    // rather than a shared trait since it's small and self-contained.
    private function resolveBlockAcl(string $scope, array $body, string $ownerHash): array
    {
        if ($scope === 'connections') {
            return ['', '', '', '', 1, 'contacts'];
        }

        if ($scope === 'private') {
            return ['<' . $ownerHash . '>', '', '', '', 1, ''];
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
        $name = '';
        if (!empty($item['iconfig']) && is_array($item['iconfig'])) {
            foreach ($item['iconfig'] as $cfg) {
                if (($cfg['cat'] ?? '') === 'system' && ($cfg['k'] ?? '') === 'BUILDBLOCK') {
                    $name = $cfg['v'];
                    break;
                }
            }
        }

        return [
            'uuid'          => $item['uuid'],
            'mid'           => $item['mid'],
            'title'         => $item['title'],
            'body'          => ContentTypes::decode($item['body'], $item['mimetype'] ?? ''),
            'mimetype'      => $item['mimetype'],
            'name'          => $name,
            'created'       => $item['created'],
            'edited'        => $item['edited'],
            'item_private'  => intval($item['item_private']),
            'public_policy' => $item['public_policy'] ?? '',
            'allow_cid'     => self::parseHashList($item['allow_cid'] ?? ''),
            'allow_gid'     => self::parseHashList($item['allow_gid'] ?? ''),
            'deny_cid'      => self::parseHashList($item['deny_cid']  ?? ''),
            'deny_gid'      => self::parseHashList($item['deny_gid']  ?? ''),
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
