<?php

namespace Theme\Solidified\Api\Handlers;

use Theme\Solidified\Api\Auth;
use Theme\Solidified\Api\Response;

class Connections
{
    // GET /api/connections
    // GET /api/connections?address=<xchan_addr>       — exact single-connection lookup
    // GET /api/connections/permcats                   — list available permission roles
    // GET /api/connections/permcats?name=<role>        — a single role's permission grid
    // GET /api/connections/:id/perms                  — bidirectional perms + incl/excl filters
    // GET /api/connections/:id/groups                 — privacy group IDs for a connection
    // GET /api/connections/:id/profile                — assigned profile id for a connection
    public function get(): void
    {
        $uid = Auth::requireLocalGet();

        $sub = \App::$argv[2] ?? '';

        if ($sub === 'permcats') {
            $name = trim($_GET['name'] ?? '');
            if ($name !== '') {
                $this->getPermcatDetail($uid, $name);
            }
            $this->getPermcats($uid);
        }

        if (is_numeric($sub) && intval($sub) > 0) {
            $sub_action = \App::$argv[3] ?? '';
            if ($sub_action === 'perms') {
                $this->getPerms($uid, intval($sub));
            } elseif ($sub_action === 'groups') {
                $this->getConnectionGroups($uid, intval($sub));
            } elseif ($sub_action === 'profile') {
                $this->getConnectionProfile($uid, intval($sub));
            }
        }

        // Exact address lookup — used by AuthorPopover connection check
        $address = trim($_GET['address'] ?? '');
        if ($address !== '') {
            $rows = q(
                "SELECT abook.abook_id, abook.abook_created, abook.abook_pending,
                        abook.abook_blocked, abook.abook_ignored, abook.abook_hidden,
                        abook.abook_archived, abook.abook_not_here, abook.abook_closeness,
                        abook.abook_role, abook.abook_profile,
                        xchan.xchan_hash, xchan.xchan_name, xchan.xchan_addr,
                        xchan.xchan_url, xchan.xchan_photo_m, xchan.xchan_network,
                        xchan.xchan_pubforum
                 FROM abook
                 LEFT JOIN xchan ON abook.abook_xchan = xchan.xchan_hash
                 WHERE abook.abook_channel = %d
                   AND abook.abook_self   = 0
                   AND xchan.xchan_deleted = 0
                   AND xchan.xchan_orphan  = 0
                   AND xchan.xchan_addr    = '%s'
                 LIMIT 1",
                intval($uid),
                dbesc($address)
            );
            Response::send($rows ? $this->formatRow($rows[0]) : null);
        }

        // List with optional filter / search / order / pagination
        $filter     = $_GET['filter']  ?? 'active';
        $search     = trim($_GET['search'] ?? '');
        $order_key  = $_GET['order']   ?? 'name';
        $limit      = max(1, min(200, intval($_GET['limit']  ?? 50)));
        $offset     = max(0, intval($_GET['start'] ?? 0));

        $sql_filter = $this->filterClause($filter);

        $sql_search = '';
        if ($search !== '') {
            $sql_search = " AND (xchan.xchan_name LIKE '%"
                . protect_sprintf(dbesc($search)) . "%'"
                . " OR xchan.xchan_addr LIKE '%"
                . protect_sprintf(dbesc($search)) . "%') ";
        }

        $sql_order = match ($order_key) {
            'name_desc'      => 'xchan.xchan_name DESC',
            'connected'      => 'abook.abook_created ASC',
            'connected_desc' => 'abook.abook_created DESC',
            'recent'         => 'xchan.xchan_updated DESC',
            default          => 'xchan.xchan_name ASC',
        };

        $base_where = "WHERE abook.abook_channel = %d
                         AND abook.abook_self    = 0
                         AND xchan.xchan_deleted = 0
                         AND xchan.xchan_orphan  = 0
                         $sql_filter $sql_search";

        $count = q(
            "SELECT COUNT(abook.abook_id) AS total
             FROM abook
             LEFT JOIN xchan ON abook.abook_xchan = xchan.xchan_hash
             $base_where",
            intval($uid)
        );
        $total = intval($count[0]['total'] ?? 0);

        $rows = q(
            "SELECT abook.abook_id, abook.abook_created, abook.abook_pending,
                    abook.abook_blocked, abook.abook_ignored, abook.abook_hidden,
                    abook.abook_archived, abook.abook_not_here, abook.abook_closeness,
                    abook.abook_role, abook.abook_profile,
                    xchan.xchan_hash, xchan.xchan_name, xchan.xchan_addr,
                    xchan.xchan_url, xchan.xchan_photo_m, xchan.xchan_network,
                    xchan.xchan_pubforum, xchan.xchan_updated
             FROM abook
             LEFT JOIN xchan ON abook.abook_xchan = xchan.xchan_hash
             $base_where
             ORDER BY $sql_order
             LIMIT %d OFFSET %d",
            intval($uid),
            $limit,
            $offset
        );

        $connections = array_map([$this, 'formatRow'], $rows ?: []);

        Response::send($connections, [
            'total'  => $total,
            'limit'  => $limit,
            'offset' => $offset,
            'filter' => $filter,
            'order'  => $order_key,
        ]);
    }

    // POST /api/connections/permcats      — create a custom permission role, or
    //                                        update an existing custom role's permission grid
    //                                        (body: { name, perms?: string[] })
    // POST /api/connections/:id/approve  — approve a pending connection
    // POST /api/connections/:id          — update role / closeness
    public function post(): void
    {
        $uid = Auth::requireLocalJson();
        $sub = \App::$argv[2] ?? '';

        if ($sub === 'permcats') {
            $this->createPermcat($uid);
        }

        $abook_id = intval($sub);
        $action   = \App::$argv[3] ?? '';

        if (!$abook_id) Response::error(400, 'abook_id required');

        // Verify the abook belongs to this channel
        $abook = q(
            "SELECT * FROM abook WHERE abook_id = %d AND abook_channel = %d LIMIT 1",
            intval($abook_id),
            intval($uid)
        );
        if (!$abook) Response::error(404, 'Connection not found');
        $abook = $abook[0];

        if ($action === 'approve') {
            $this->approve($uid, $abook_id, $abook);
        } else {
            $this->update($uid, $abook_id, $abook);
        }
    }

    // DELETE /api/connections/permcats?name=<role>  — delete a custom permission role
    // DELETE /api/connections/:id                   — remove a connection
    public function delete(): void
    {
        $sub = \App::$argv[2] ?? '';

        if ($sub === 'permcats') {
            $uid  = Auth::requireLocalJson();
            // Use query string, not argv[3]: escape_tags() mangles & → &amp; in path segments
            $name = trim($_GET['name'] ?? '');
            $this->deletePermcat($uid, $name);
        }

        $uid      = Auth::requireLocal();
        $abook_id = intval($sub);
        if (!$abook_id) Response::error(400, 'abook_id required');

        require_once 'include/connections.php';
        $ok = contact_remove($uid, $abook_id);
        if (!$ok) Response::error(404, 'Connection not found or could not be removed');

        Response::send(['deleted' => true]);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function approve(int $uid, int $abook_id, array $abook): never
    {
        if (!intval($abook['abook_pending'])) {
            Response::error(409, 'Connection is not pending');
        }

        $channel = channelx_by_n($uid);
        if (!$channel) Response::error(500, 'Channel not found');

        // Assign default permissions role
        $role = $abook['abook_role'] ?? '';
        if ($role) {
            require_once 'include/channel.php';
            \Zotlabs\Lib\Permcat::assign($channel, $role, [$abook['abook_xchan']]);
        }

        q(
            "UPDATE abook SET abook_pending = 0 WHERE abook_id = %d AND abook_channel = %d",
            intval($abook_id),
            intval($uid)
        );

        // Summon notifiers for the accept + permission handshake
        \Zotlabs\Daemon\Master::Summon(['Notifier', 'permission_accept', $abook_id]);
        \Zotlabs\Daemon\Master::Summon(['Notifier', 'permission_create', $abook_id]);

        Response::send(['approved' => true]);
    }

    private function update(int $uid, int $abook_id, array $abook): never
    {
        $body       = Auth::$parsedBody;
        $role       = array_key_exists('role',       $body) ? trim((string) $body['role'])   : null;
        $closeness  = array_key_exists('closeness',  $body) ? intval($body['closeness'])     : null;
        $blocked    = array_key_exists('blocked',    $body) ? (bool) $body['blocked']        : null;
        $ignored    = array_key_exists('ignored',    $body) ? (bool) $body['ignored']        : null;
        $archived   = array_key_exists('archived',   $body) ? (bool) $body['archived']       : null;
        $hidden     = array_key_exists('hidden',     $body) ? (bool) $body['hidden']         : null;
        $incl       = array_key_exists('incl',       $body) ? trim((string) $body['incl'])   : null;
        $excl       = array_key_exists('excl',       $body) ? trim((string) $body['excl'])   : null;
        // null means "leave unchanged"; 0 means "clear to default profile"
        $profile_id = array_key_exists('profile_id', $body) ? ($body['profile_id'] !== null ? intval($body['profile_id']) : 0) : null;

        $channel = channelx_by_n($uid);
        if (!$channel) Response::error(500, 'Channel not found');

        if ($role !== null) {
            require_once 'include/channel.php';
            \Zotlabs\Lib\Permcat::assign($channel, $role, [$abook['abook_xchan']]);
            q(
                "UPDATE abook SET abook_role = '%s' WHERE abook_id = %d AND abook_channel = %d",
                dbesc($role),
                intval($abook_id),
                intval($uid)
            );
        }

        if ($closeness !== null) {
            $closeness = max(0, min(99, $closeness));
            q(
                "UPDATE abook SET abook_closeness = %d WHERE abook_id = %d AND abook_channel = %d",
                intval($closeness),
                intval($abook_id),
                intval($uid)
            );
        }

        // Direct flag updates (blocked, ignored, archived, hidden, filters)
        $flag_sets = [];
        if ($blocked    !== null) $flag_sets[] = sprintf("abook_blocked = %d",  intval($blocked));
        if ($ignored    !== null) $flag_sets[] = sprintf("abook_ignored = %d",  intval($ignored));
        if ($archived   !== null) $flag_sets[] = sprintf("abook_archived = %d", intval($archived));
        if ($hidden     !== null) $flag_sets[] = sprintf("abook_hidden = %d",   intval($hidden));
        if ($incl       !== null) $flag_sets[] = sprintf("abook_incl = '%s'",   dbesc($incl));
        if ($excl       !== null) $flag_sets[] = sprintf("abook_excl = '%s'",   dbesc($excl));
        if ($profile_id !== null) $flag_sets[] = sprintf("abook_profile = %d",  intval($profile_id));
        if ($flag_sets) {
            q(
                "UPDATE abook SET " . implode(', ', $flag_sets) . " WHERE abook_id = %d AND abook_channel = %d",
                intval($abook_id),
                intval($uid)
            );
        }

        $was_pending = intval($abook['abook_pending']);
        if ($was_pending) {
            q(
                "UPDATE abook SET abook_pending = 0 WHERE abook_id = %d AND abook_channel = %d",
                intval($abook_id),
                intval($uid)
            );
            \Zotlabs\Daemon\Master::Summon(['Notifier', 'permission_accept', $abook_id]);
        }
        \Zotlabs\Daemon\Master::Summon([
            'Notifier',
            $was_pending ? 'permission_create' : 'permission_update',
            $abook_id,
        ]);

        Response::send(['updated' => true]);
    }

    private function getPermcats(int $uid): never
    {
        require_once 'include/channel.php';
        $pcat   = new \Zotlabs\Lib\Permcat($uid);
        $list   = $pcat->listing();
        $result = array_map(fn($pc) => [
            'name'   => $pc['name'],
            'label'  => $pc['localname'],
            'system' => (bool) intval($pc['system']),
        ], $list);
        Response::send($result);
    }

    private function createPermcat(int $uid): never
    {
        $body = Auth::$parsedBody;
        $name = notags(trim($body['name'] ?? ''));

        if (!$name) Response::error(400, 'Role name is required');
        if (strtolower($name) === 'default') Response::error(400, 'That name is reserved');

        require_once 'include/channel.php';
        $pcat     = new \Zotlabs\Lib\Permcat($uid);
        $existing = $pcat->fetch($name);
        $is_update = !isset($existing['error']);

        if ($is_update && intval($existing['system'] ?? 0)) {
            Response::error(403, 'Cannot modify a built-in role');
        }

        if (array_key_exists('perms', $body) && is_array($body['perms'])) {
            // Explicit permission grid — either seeding a new custom role
            // or overwriting an existing one's permissions.
            $all_perms = \Zotlabs\Access\Permissions::Perms();
            $permarr   = array_values(array_intersect(array_keys($all_perms), $body['perms']));
        } elseif ($is_update) {
            Response::error(400, 'Permissions are required to update a role');
        } else {
            $default = $pcat->fetch('default');
            $filled  = $default['raw_perms'] ?? \Zotlabs\Access\Permissions::FilledPerms([]);
            $permarr = array_keys(array_filter($filled));
        }

        \Zotlabs\Lib\Permcat::update($uid, $name, $permarr);

        // If we just changed an existing role's permissions, push the change
        // out to every contact currently holding that role.
        if ($is_update) {
            $channel = channelx_by_n($uid);
            if ($channel) {
                $affected = q(
                    "SELECT abook_xchan FROM abook
                     WHERE abook_channel = %d AND abook_role = '%s' AND abook_self = 0 AND abook_pending = 0",
                    intval($uid),
                    dbesc($name)
                );
                if ($affected) {
                    \Zotlabs\Lib\Permcat::assign($channel, $name, array_column($affected, 'abook_xchan'));
                }
            }
        }

        $saved = (new \Zotlabs\Lib\Permcat($uid))->fetch($name);

        Response::send([
            'name'   => $saved['name']      ?? $name,
            'label'  => $saved['localname'] ?? $name,
            'system' => (bool) intval($saved['system'] ?? 0),
        ]);
    }

    private function getPermcatDetail(int $uid, string $name): never
    {
        require_once 'include/channel.php';
        require_once 'include/permissions.php';

        $pcat = new \Zotlabs\Lib\Permcat($uid);
        $pc   = $pcat->fetch($name);
        if (isset($pc['error'])) Response::error(404, 'Role not found');

        $global_perms = \Zotlabs\Access\Permissions::Perms();
        $raw_perms    = $pc['raw_perms'] ?? [];

        $perms = [];
        foreach ($global_perms as $key => $label) {
            $limit = \Zotlabs\Access\PermissionLimits::Get($uid, $key);
            $perms[] = [
                'key'       => $key,
                'label'     => $label,
                'value'     => (bool) ($raw_perms[$key] ?? false),
                // true when the channel's privacy settings force this permission
                // open/closed regardless of what the role grants
                'inherited' => !($limit & PERMS_SPECIFIC),
            ];
        }

        Response::send([
            'name'   => $pc['name'],
            'label'  => $pc['localname'],
            'system' => (bool) intval($pc['system'] ?? 0),
            'perms'  => $perms,
        ]);
    }

    private function deletePermcat(int $uid, string $name): never
    {
        if (!$name) Response::error(400, 'Role name is required');
        if ($name === 'default') Response::error(400, 'Cannot delete the default role');

        require_once 'include/channel.php';
        $pcat = new \Zotlabs\Lib\Permcat($uid);
        $pc   = $pcat->fetch($name);
        if (isset($pc['error'])) Response::error(404, 'Role not found');
        if (intval($pc['system'] ?? 0)) Response::error(403, 'Cannot delete a built-in role');

        $exact_name = $pc['name'];

        // Reassign contacts that had this role to 'default' before deleting
        $affected = q(
            "SELECT abook_xchan FROM abook WHERE abook_channel = %d AND abook_role = '%s'",
            intval($uid),
            dbesc($exact_name)
        );
        if ($affected) {
            $channel = channelx_by_n($uid);
            if ($channel) {
                \Zotlabs\Lib\Permcat::assign($channel, 'default', array_column($affected, 'abook_xchan'));
            }
            q(
                "UPDATE abook SET abook_role = 'default' WHERE abook_channel = %d AND abook_role = '%s'",
                intval($uid),
                dbesc($exact_name)
            );
        }

        \Zotlabs\Lib\Permcat::delete($uid, $exact_name);
        Response::send(['deleted' => true, 'name' => $exact_name]);
    }

    private function getPerms(int $uid, int $abook_id): never
    {
        $row = q(
            "SELECT abook_xchan, abook_incl, abook_excl
             FROM abook WHERE abook_id = %d AND abook_channel = %d LIMIT 1",
            intval($abook_id),
            intval($uid)
        );
        if (!$row) Response::error(404, 'Connection not found');
        $abook = $row[0];

        require_once 'include/permissions.php';
        $global_perms = \Zotlabs\Access\Permissions::Perms();
        $my_perms     = get_all_perms($uid, $abook['abook_xchan'], false);

        $theirs_raw = q(
            "SELECT k, v FROM abconfig WHERE chan = %d AND xchan = '%s' AND cat = 'their_perms'",
            intval($uid),
            dbesc($abook['abook_xchan'])
        );
        $their_perms = [];
        if ($theirs_raw) {
            foreach ($theirs_raw as $t) {
                $their_perms[$t['k']] = intval($t['v']);
            }
        }

        $perms = [];
        foreach ($global_perms as $key => $label) {
            $perms[] = [
                'key'   => $key,
                'label' => $label,
                'their' => (bool) ($their_perms[$key] ?? false),
                'my'    => (bool) ($my_perms[$key]    ?? false),
            ];
        }

        Response::send([
            'incl'  => $abook['abook_incl'] ?? '',
            'excl'  => $abook['abook_excl'] ?? '',
            'perms' => $perms,
        ]);
    }

    private function getConnectionGroups(int $uid, int $abook_id): never
    {
        $row = q(
            "SELECT abook_xchan FROM abook WHERE abook_id = %d AND abook_channel = %d LIMIT 1",
            intval($abook_id),
            intval($uid)
        );
        if (!$row) Response::error(404, 'Connection not found');

        $xchan_hash = $row[0]['abook_xchan'];

        $rows = q(
            "SELECT pgrp.id FROM pgrp
             INNER JOIN pgrp_member ON pgrp_member.gid = pgrp.id
             WHERE pgrp.uid = %d AND pgrp.deleted = 0 AND pgrp_member.xchan = '%s'",
            intval($uid),
            dbesc($xchan_hash)
        );

        Response::send(array_map(fn($g) => intval($g['id']), $rows ?? []));
    }

    private function getConnectionProfile(int $uid, int $abook_id): never
    {
        $row = q(
            "SELECT abook_profile FROM abook WHERE abook_id = %d AND abook_channel = %d LIMIT 1",
            intval($abook_id),
            intval($uid)
        );
        if (!$row) Response::error(404, 'Connection not found');

        $profile_id = intval($row[0]['abook_profile'] ?? 0);
        Response::send(['profile_id' => $profile_id > 0 ? $profile_id : null]);
    }

    private function filterClause(string $filter): string
    {
        return match ($filter) {
            'active'  => 'AND abook.abook_blocked = 0 AND abook.abook_ignored = 0
                          AND abook.abook_hidden = 0 AND abook.abook_archived = 0
                          AND abook.abook_not_here = 0',
            'recent'  => 'AND abook.abook_blocked = 0 AND abook.abook_ignored = 0
                          AND abook.abook_hidden = 0 AND abook.abook_archived = 0
                          AND abook.abook_not_here = 0
                          AND xchan.xchan_updated > UTC_TIMESTAMP() - INTERVAL 7 DAY',
            'pending' => 'AND abook.abook_pending = 1 AND abook.abook_ignored = 0',
            'blocked' => 'AND abook.abook_blocked = 1',
            'ignored' => 'AND abook.abook_ignored = 1',
            'hidden'  => 'AND abook.abook_hidden = 1',
            'archived'=> 'AND (abook.abook_archived = 1 OR abook.abook_not_here = 1)',
            default   => '',  // 'all' or unknown
        };
    }

    private function formatRow(array $row): array
    {
        $status = array_values(array_filter([
            intval($row['abook_pending'])  ? 'pending'  : null,
            intval($row['abook_blocked'])  ? 'blocked'  : null,
            intval($row['abook_ignored'])  ? 'ignored'  : null,
            intval($row['abook_hidden'])   ? 'hidden'   : null,
            intval($row['abook_archived']) ? 'archived' : null,
            intval($row['abook_not_here']) ? 'not_here' : null,
        ]));

        $raw_profile = intval($row['abook_profile'] ?? 0);
        return [
            'id'         => intval($row['abook_id']),
            'xchan_hash' => $row['xchan_hash'],
            'name'       => $row['xchan_name'],
            'address'    => $row['xchan_addr'],
            'url'        => $row['xchan_url'],
            'photo'      => $row['xchan_photo_m'],
            'network'    => $row['xchan_network'],
            'is_forum'   => (bool) intval($row['xchan_pubforum']),
            'connected'  => $row['abook_created'],
            'closeness'  => intval($row['abook_closeness']),
            'role'       => $row['abook_role'] ?? '',
            'status'     => $status,
            'pending'    => (bool) intval($row['abook_pending']),
            'profile_id' => $raw_profile > 0 ? $raw_profile : null,
        ];
    }
}
