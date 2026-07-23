<?php
namespace Theme\Solidified\Api\Handlers;

use Theme\Solidified\Api\Auth;
use Theme\Solidified\Api\Response;
use Zotlabs\Lib\Libsync;

/**
 * Files (cloud storage) handler
 *
 * GET  /api/files/:nick                  → list root folder
 * GET  /api/files/:nick/folder/:hash     → list folder by hash ('' = root)
 * GET  /api/files/:nick/meta/:hash       → single file metadata + ACL
 * GET  /api/files/:nick/quota            → storage used/limit in bytes
 * GET  /api/files/:nick/download/:hash   → download a file, or a folder as a zip
 * GET  /api/files/:nick/categories/:hash → list category terms for a file/folder
 * POST /api/files/:nick/permissions      → update file ACL
 * POST /api/files/:nick/rename           → rename a file/folder in place
 * POST /api/files/:nick/move             → move a file/folder to another folder
 * POST /api/files/:nick/copy             → copy a file/folder to another folder
 * POST /api/files/:nick/categories       → replace category terms for a file/folder
 *
 * Register in Router.php $map:
 *   'files' => Handlers\Files::class,
 */
class Files
{
    private const ROW_COLUMNS = "id, hash, filename, filetype, filesize, folder,
                    display_path, is_dir, is_photo, created, edited, revision,
                    allow_cid, allow_gid, deny_cid, deny_gid";

    public function get(): void
    {
        require_once 'include/attach.php';
        require_once 'include/security.php';
        require_once 'include/channel.php';

        $channel  = $this->resolveChannel();
        $owner_uid = intval($channel['channel_id']);
        $ob_hash  = get_observer_hash();

        if (!perm_is_allowed($owner_uid, $ob_hash, 'view_storage')) {
            Response::error(403, 'Permission denied');
        }

        // write_storage is a channel-wide grant — any observer (local or
        // remote) holding it may edit this channel's storage, not just the owner.
        $can_write = (bool) perm_is_allowed($owner_uid, $ob_hash, 'write_storage');

        $action = \App::$argv[3] ?? '';
        $datum  = \App::$argv[4] ?? '';

        switch ($action) {
            case 'folder':
                $this->listFolder($owner_uid, $ob_hash, $datum, $can_write);
                break;
            case 'meta':
                $this->fileMeta($owner_uid, $ob_hash, $datum);
                break;
            case 'quota':
                $this->quota($channel);
                break;
            case 'download':
                $this->download($owner_uid, $ob_hash, $datum, $channel);
                break;
            case 'categories':
                $this->getCategories($owner_uid, $ob_hash, $datum);
                break;
            default:
                // Root folder (folder hash = '')
                $this->listFolder($owner_uid, $ob_hash, '', $can_write);
                break;
        }
    }

    // ── Storage quota ────────────────────────────────────────────────────────
    //
    // Mirrors Zotlabs\Storage\Browser::CloudDirectory()'s quota calculation:
    // limit is the account's attach_upload_limit service-class setting (falls
    // back to the site's free disk space if configured to report it), used is
    // the sum of all attach rows for the channel's account (all its channels).

    private function quota(array $channel): void
    {
        require_once 'include/text.php';

        $limit = engr_units_to_bytes(service_class_fetch($channel['channel_id'], 'attach_upload_limit'));

        if (!$limit && \Zotlabs\Lib\Config::Get('system', 'cloud_report_disksize')) {
            $limit = intval(disk_free_space('store'));
        }

        $r = q("SELECT SUM(filesize) AS total FROM attach WHERE aid = %d",
            intval($channel['channel_account_id'])
        );

        Response::send([
            'used' => intval($r[0]['total'] ?? 0),
            'limit' => intval($limit) ?: null,
        ]);
    }

    public function post(): void
    {
        require_once 'include/attach.php';

        $owner    = $this->resolveChannel();
        $uid      = intval($owner['channel_id']);
        $obs_hash = Auth::requireLoggedInJson();

        // write_storage is a channel-wide grant — any observer (local or
        // remote) holding it may edit this channel's cloud storage, not just
        // the owner (matches WebDAV's own perm_is_allowed(..., 'write_storage')).
        if (!perm_is_allowed($uid, $obs_hash, 'write_storage')) {
            Response::error(403, 'Permission denied');
        }

        $data   = Auth::$parsedBody;
        $action = \App::$argv[3] ?? '';

        switch ($action) {
            case 'permissions':
                // ACL changes stay owner-only — write_storage access
                // shouldn't let a visitor grant themselves broader access.
                if (!local_channel() || local_channel() !== $uid) {
                    Response::error(403, 'Owner access required');
                }
                $this->updatePermissions($uid, $data);
                break;
            case 'rename':
                $this->renameItem($uid, $owner, $data);
                break;
            case 'move':
                $this->moveOrCopyItem($uid, $owner, $data, false);
                break;
            case 'copy':
                $this->moveOrCopyItem($uid, $owner, $data, true);
                break;
            case 'categories':
                $this->updateCategories($uid, $owner, $data);
                break;
            default:
                Response::error(404, 'Unknown action');
        }
    }

    // ── Folder listing ────────────────────────────────────────────────────────

    private function listFolder(int $uid, string $ob_hash, string $folder_hash, bool $can_write): void
    {
        $sql_extra   = permissions_sql($uid, $ob_hash, 'attach');
        $folder_cond = "folder = '" . dbesc($folder_hash) . "'";

        $r = q(
            "SELECT " . self::ROW_COLUMNS . "
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

        Response::send($items, ['folder' => $folder_hash, 'can_write' => $can_write]);
    }

    // ── Single file metadata ──────────────────────────────────────────────────

    private function fileMeta(int $uid, string $ob_hash, string $hash): void
    {
        if (!$hash) Response::error(400, 'Hash required');

        $sql_extra = permissions_sql($uid, $ob_hash, 'attach');

        $r = q(
            "SELECT " . self::ROW_COLUMNS . "
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
        $updated = $this->fetchRow($uid, $hash);
        Response::send($updated ? $this->formatRow($updated) : null);
    }

    // ── Rename / Move / Copy ─────────────────────────────────────────────────

    private function renameItem(int $uid, array $channel, array $data): void
    {
        $hash    = $data['hash'] ?? '';
        $newname = trim((string) ($data['filename'] ?? ''));
        if (!$hash || $newname === '') Response::error(400, 'hash and filename required');

        $r = q(
            "SELECT id, hash, folder FROM attach WHERE uid = %d AND hash = '%s' LIMIT 1",
            intval($uid),
            dbesc($hash)
        );
        if (!$r) Response::error(404, 'File not found');

        $res = attach_move($uid, $hash, $r[0]['folder'], $newname);
        if (empty($res['success'])) Response::error(500, $res['message'] ?? 'Rename failed');

        $this->syncAttach($uid, $channel, $hash);

        $updated = $this->fetchRow($uid, $hash);
        Response::send($updated ? $this->formatRow($updated) : null);
    }

    private function moveOrCopyItem(int $uid, array $channel, array $data, bool $copy): void
    {
        $hash      = $data['hash'] ?? '';
        $newFolder = (string) ($data['folder'] ?? '');
        if (!$hash) Response::error(400, 'hash required');

        $r = q(
            "SELECT id, hash, filename, is_dir FROM attach WHERE uid = %d AND hash = '%s' LIMIT 1",
            intval($uid),
            dbesc($hash)
        );
        if (!$r) Response::error(404, 'File not found');

        if ($newFolder) {
            $f = q(
                "SELECT id FROM attach WHERE uid = %d AND hash = '%s' AND is_dir = 1 LIMIT 1",
                intval($uid),
                dbesc($newFolder)
            );
            if (!$f) Response::error(404, 'Destination folder not found');
        }

        if (intval($r[0]['is_dir']) && $newFolder !== '' && $this->isSelfOrDescendant($uid, $newFolder, $hash)) {
            Response::error(400, $copy
                ? 'Cannot copy a folder into itself or one of its own sub-folders'
                : 'Cannot move a folder into itself or one of its own sub-folders');
        }

        $res = $copy
            ? attach_copy($uid, $hash, $newFolder)
            : attach_move($uid, $hash, $newFolder);

        if (empty($res['success'])) {
            Response::error(500, $res['message'] ?? ($copy ? 'Copy failed' : 'Move failed'));
        }

        $resultHash = $copy ? $res['resource_id'] : $hash;
        $this->syncAttach($uid, $channel, $resultHash);

        $updated = $this->fetchRow($uid, $resultHash);
        Response::send($updated ? $this->formatRow($updated) : null);
    }

    /** Walk $candidateFolderHash's ancestor chain up to root, checking whether $sourceHash appears in it. */
    private function isSelfOrDescendant(int $uid, string $candidateFolderHash, string $sourceHash): bool
    {
        $hash = $candidateFolderHash;
        $seen = [];
        while ($hash !== '') {
            if ($hash === $sourceHash) return true;
            if (isset($seen[$hash])) break; // guard against a pre-existing cycle
            $seen[$hash] = true;
            $r = q("SELECT folder FROM attach WHERE uid = %d AND hash = '%s' LIMIT 1", intval($uid), dbesc($hash));
            if (!$r) break;
            $hash = $r[0]['folder'];
        }
        return false;
    }

    /** Mirrors classic core's attach_edit sync: export the changed attach row and build a sync packet for clones. */
    private function syncAttach(int $uid, array $channel, string $resource): void
    {
        $sync = attach_export_data($channel, $resource, false);
        if ($sync) Libsync::build_sync_packet($uid, ['file' => [$sync]]);
    }

    // ── Download (file passthrough, folder → zip) ───────────────────────────

    private function download(int $uid, string $ob_hash, string $hash, array $channel): void
    {
        if (!$hash) Response::error(400, 'Hash required');

        $sql_extra = permissions_sql($uid, $ob_hash, 'attach');
        $r = q(
            "SELECT hash, filename, filetype, is_dir, content, os_storage
               FROM attach
              WHERE uid = %d AND hash = '%s' $sql_extra
              LIMIT 1",
            intval($uid),
            dbesc($hash)
        );
        if (!$r) Response::error(404, 'File not found');

        if (intval($r[0]['is_dir'])) {
            $this->downloadZip($uid, $ob_hash, $r[0], $channel);
        } else {
            $this->downloadFile($r[0], $channel);
        }
    }

    private function downloadFile(array $row, array $channel): void
    {
        $unsafe_types = ['text/html', 'text/css', 'application/javascript'];
        if (in_array($row['filetype'], $unsafe_types, true) && !channel_codeallowed($channel['channel_id'])) {
            header('Content-Type: text/plain');
        } else {
            header('Content-Type: ' . $row['filetype']);
        }
        header('Content-Disposition: attachment; filename="' . addslashes($row['filename']) . '"');

        if (!intval($row['os_storage'])) {
            $content = dbunescbin($row['content']);
            header('Content-Length: ' . strlen($content));
            echo $content;
            exit;
        }

        $fname = $this->resolveStorePath($row['content'], $channel['channel_address']);
        if (!is_file($fname)) Response::error(404, 'File data not found on disk');

        header('Content-Length: ' . filesize($fname));
        readfile($fname);
        exit;
    }

    private function downloadZip(int $uid, string $ob_hash, array $root, array $channel): void
    {
        $tmp_dir = 'store/[data]/' . $channel['channel_address'] . '/tmp';
        if (!is_dir($tmp_dir)) mkdir($tmp_dir, STORAGE_DEFAULT_PERMISSIONS, true);

        $zip_path = $tmp_dir . '/zip_' . random_string(32) . '.zip';
        $zip = new \ZipArchive();
        if ($zip->open($zip_path, \ZipArchive::CREATE) !== true) {
            Response::error(500, 'Could not create zip archive');
        }

        $this->addFolderToZip($zip, $uid, $ob_hash, $root['hash'], '', $channel['channel_address']);
        $zip->close();

        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . addslashes($root['filename']) . '.zip"');
        header('Content-Length: ' . filesize($zip_path));
        readfile($zip_path);
        unlink($zip_path);
        exit;
    }

    private function addFolderToZip(\ZipArchive $zip, int $uid, string $ob_hash, string $folderHash, string $zipPrefix, string $channelAddress): void
    {
        $sql_extra = permissions_sql($uid, $ob_hash, 'attach');
        $r = q(
            "SELECT hash, filename, is_dir, content, os_storage
               FROM attach
              WHERE uid = %d AND folder = '%s' $sql_extra",
            intval($uid),
            dbesc($folderHash)
        );

        foreach (($r ?: []) as $row) {
            $entryPath = $zipPrefix !== '' ? $zipPrefix . '/' . $row['filename'] : $row['filename'];

            if (intval($row['is_dir'])) {
                $zip->addEmptyDir($entryPath);
                $this->addFolderToZip($zip, $uid, $ob_hash, $row['hash'], $entryPath, $channelAddress);
                continue;
            }

            // Legacy DB-blob storage (os_storage = 0) has no on-disk path to zip; skip it.
            if (!intval($row['os_storage'])) continue;

            $fname = $this->resolveStorePath($row['content'], $channelAddress);
            if (is_file($fname)) {
                $zip->addFile($fname, $entryPath);
                // Compressing is CPU-intensive for potentially large archives — just store the data.
                $zip->setCompressionName($entryPath, \ZipArchive::CM_STORE);
            }
        }
    }

    /** attach.content is usually a full "store/<addr>/<hash>" path, but some rows only store the bare hash. */
    private function resolveStorePath(string $content, string $channelAddress): string
    {
        $fname = dbunescbin($content);
        if (strpos($fname, 'store') === false) {
            $fname = 'store/' . $channelAddress . '/' . $fname;
        }
        return $fname;
    }

    // ── Categories (term table, otype = TERM_OBJ_FILE) ──────────────────────

    private function getCategories(int $uid, string $ob_hash, string $hash): void
    {
        if (!$hash) Response::error(400, 'hash required');

        $sql_extra = permissions_sql($uid, $ob_hash, 'attach');
        $r = q(
            "SELECT id FROM attach WHERE uid = %d AND hash = '%s' $sql_extra LIMIT 1",
            intval($uid),
            dbesc($hash)
        );
        if (!$r) Response::error(404, 'File not found');

        $terms = q(
            "SELECT term FROM term WHERE uid = %d AND oid = %d AND otype = %d",
            intval($uid),
            intval($r[0]['id']),
            TERM_OBJ_FILE
        );

        Response::send([
            'categories' => array_map(fn($t) => $t['term'], $terms ?: []),
        ]);
    }

    private function updateCategories(int $uid, array $channel, array $data): void
    {
        require_once 'include/taxonomy.php';

        $hash       = $data['hash'] ?? '';
        $categories = is_array($data['categories'] ?? null) ? $data['categories'] : [];
        if (!$hash) Response::error(400, 'hash required');

        $r = q(
            "SELECT id FROM attach WHERE uid = %d AND hash = '%s' LIMIT 1",
            intval($uid),
            dbesc($hash)
        );
        if (!$r) Response::error(404, 'File not found');
        $attach_id = intval($r[0]['id']);

        q(
            "DELETE FROM term WHERE uid = %d AND oid = %d AND otype = %d",
            intval($uid),
            $attach_id,
            TERM_OBJ_FILE
        );

        $nick  = $channel['channel_address'];
        $saved = [];

        foreach ($categories as $term) {
            $term = trim((string) $term);
            if ($term === '') continue;
            $term_link = z_root() . '/cloud/' . $nick . '/?cat=' . urlencode($term);
            store_item_tag($uid, $attach_id, TERM_OBJ_FILE, TERM_CATEGORY, $term, $term_link);
            $saved[] = $term;
        }

        Response::send(['categories' => $saved]);
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
            'revision'     => intval($row['revision']),
            'acl' => [
                'allow_gid' => $this->expandAcl($row['allow_gid']),
                'allow_cid' => $this->expandAcl($row['allow_cid']),
                'deny_gid'  => $this->expandAcl($row['deny_gid']),
                'deny_cid'  => $this->expandAcl($row['deny_cid']),
            ],
        ];
    }

    /** Fetch a single attach row (owner-scoped, no observer permission filter) by hash. */
    private function fetchRow(int $uid, string $hash): ?array
    {
        $r = q(
            "SELECT " . self::ROW_COLUMNS . " FROM attach WHERE uid = %d AND hash = '%s' LIMIT 1",
            intval($uid),
            dbesc($hash)
        );
        return $r ? $r[0] : null;
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
