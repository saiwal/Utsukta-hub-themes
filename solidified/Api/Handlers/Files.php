<?php
namespace Theme\Solidified\Api\Handlers;

use Theme\Solidified\Api\Auth;
use Theme\Solidified\Api\Response;

/**
 * Files (cloud storage) handler
 *
 * GET  /api/files/:nick                  → list root folder
 * GET  /api/files/:nick/folder/:hash     → list folder by hash ('' = root)
 * GET  /api/files/:nick/meta/:hash       → single file metadata + ACL
 * POST /api/files/:nick/permissions      → update file ACL
 *
 * Register in Router.php $map:
 *   'files' => Handlers\Files::class,
 */
class Files
{
    public function get(): void
    {
        require_once 'include/attach.php';
        require_once 'include/security.php';

        $channel  = $this->resolveChannel();
        $owner_uid = intval($channel['channel_id']);
        $ob_hash  = get_observer_hash();

        if (!perm_is_allowed($owner_uid, $ob_hash, 'view_storage')) {
            Response::error(403, 'Permission denied');
        }

        $action = \App::$argv[3] ?? '';
        $datum  = \App::$argv[4] ?? '';

        switch ($action) {
            case 'folder':
                $this->listFolder($owner_uid, $ob_hash, $datum);
                break;
            case 'meta':
                $this->fileMeta($owner_uid, $ob_hash, $datum);
                break;
            default:
                // Root folder (folder hash = '')
                $this->listFolder($owner_uid, $ob_hash, '');
                break;
        }
    }

    public function post(): void
    {
        require_once 'include/attach.php';

        $uid  = Auth::requireLocalJson();
        $data = Auth::$parsedBody;

        $action = \App::$argv[3] ?? '';

        if ($action === 'permissions') {
            $this->updatePermissions($uid, $data);
        } else {
            Response::error(404, 'Unknown action');
        }
    }

    // ── Folder listing ────────────────────────────────────────────────────────

    private function listFolder(int $uid, string $ob_hash, string $folder_hash): void
    {
        $sql_extra   = permissions_sql($uid, $ob_hash, 'attach');
        $folder_cond = "folder = '" . dbesc($folder_hash) . "'";

        $r = q(
            "SELECT id, hash, filename, filetype, filesize, folder,
                    display_path, is_dir, is_photo, created, edited,
                    allow_cid, allow_gid, deny_cid, deny_gid
               FROM attach
              WHERE uid = %d
                AND $folder_cond
                $sql_extra
              ORDER BY is_dir DESC, filename ASC",
            intval($uid)
        );

        $items = [];
        foreach (($r ?: []) as $row) {
            $items[] = $this->formatRow($row);
        }

        Response::send($items, ['folder' => $folder_hash]);
    }

    // ── Single file metadata ──────────────────────────────────────────────────

    private function fileMeta(int $uid, string $ob_hash, string $hash): void
    {
        if (!$hash) Response::error(400, 'Hash required');

        $sql_extra = permissions_sql($uid, $ob_hash, 'attach');

        $r = q(
            "SELECT id, hash, filename, filetype, filesize, folder,
                    display_path, is_dir, is_photo, created, edited,
                    allow_cid, allow_gid, deny_cid, deny_gid
               FROM attach
              WHERE uid = %d
                AND hash = '%s'
                $sql_extra
              LIMIT 1",
            intval($uid),
            dbesc($hash)
        );

        if (!$r) Response::error(404, 'File not found');

        Response::send($this->formatRow($r[0]));
    }

    // ── Permissions update ────────────────────────────────────────────────────

    private function updatePermissions(int $uid, array $data): void
    {
        $hash    = $data['hash']    ?? '';
        $recurse = !empty($data['recurse']);

        if (!$hash) Response::error(400, 'hash required');

        // Verify the file belongs to this channel
        $r = q(
            "SELECT id, hash, is_dir FROM attach WHERE uid = %d AND hash = '%s' LIMIT 1",
            intval($uid),
            dbesc($hash)
        );
        if (!$r) Response::error(404, 'File not found');

        $allow_gid = $this->packAcl($data['group_allow']   ?? []);
        $allow_cid = $this->packAcl($data['contact_allow'] ?? []);
        $deny_gid  = $this->packAcl($data['group_deny']    ?? []);
        $deny_cid  = $this->packAcl($data['contact_deny']  ?? []);

        attach_change_permissions($uid, $hash, $allow_cid, $allow_gid, $deny_cid, $deny_gid, $recurse, true);

        // Return fresh metadata
        $updated = q(
            "SELECT id, hash, filename, filetype, filesize, folder,
                    display_path, is_dir, is_photo, created, edited,
                    allow_cid, allow_gid, deny_cid, deny_gid
               FROM attach WHERE uid = %d AND hash = '%s' LIMIT 1",
            intval($uid),
            dbesc($hash)
        );

        Response::send($updated ? $this->formatRow($updated[0]) : null);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /** Wrap each value in angle brackets: ["a","b"] → "<a><b>" */
    private function packAcl(array $items): string
    {
        $out = '';
        foreach ($items as $v) {
            $v = trim((string) $v);
            if ($v) $out .= '<' . $v . '>';
        }
        return $out;
    }

    /** Expand stored ACL "<a><b>" → ["a","b"] */
    private function expandAcl(string $s): array
    {
        if (!$s) return [];
        $parts = explode('>', str_replace('<', '', $s));
        return array_values(array_filter(array_map('trim', $parts)));
    }

    private function formatRow(array $row): array
    {
        return [
            'id'           => intval($row['id']),
            'hash'         => $row['hash'],
            'filename'     => $row['filename'],
            'filetype'     => $row['filetype'],
            'filesize'     => intval($row['filesize']),
            'folder'       => $row['folder'],
            'display_path' => $row['display_path'],
            'is_dir'       => (bool) intval($row['is_dir']),
            'is_photo'     => (bool) intval($row['is_photo']),
            'created'      => $row['created'],
            'edited'       => $row['edited'],
            'acl' => [
                'allow_gid' => $this->expandAcl($row['allow_gid']),
                'allow_cid' => $this->expandAcl($row['allow_cid']),
                'deny_gid'  => $this->expandAcl($row['deny_gid']),
                'deny_cid'  => $this->expandAcl($row['deny_cid']),
            ],
        ];
    }

    private function resolveChannel(): array
    {
        $nick = \App::$argv[2] ?? null;

        if ($nick) {
            $channel = channelx_by_nick($nick);
            if (!$channel || $channel['channel_removed'])
                Response::error(404, 'Channel not found');
            return $channel;
        }

        if (!local_channel())
            Response::error(401, 'Authentication required');

        $channel = \App::get_channel();
        if (!$channel)
            Response::error(500, 'Could not resolve channel');
        return $channel;
    }
}
