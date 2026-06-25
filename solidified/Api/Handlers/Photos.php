<?php
namespace Theme\Solidified\Api\Handlers;

use Theme\Solidified\Api\Auth;
use Theme\Solidified\Api\Response;
use Zotlabs\Lib\Libsync;

class Photos
{
    // GET /api/photos/:nick           → album list
    // GET /api/photos/:nick/album/:hash → photos in album
    // GET /api/photos/:nick/image/:id   → single photo detail

    public function get(): void
    {
        require_once 'include/photo/photo_driver.php';
        require_once 'include/items.php';
        require_once 'include/security.php';

        $channel = $this->resolveChannel();
        $owner_uid = intval($channel['channel_id']);
        $ob_hash = get_observer_hash();  // use directly, not via get_observer()

        if (!perm_is_allowed($owner_uid, $ob_hash, 'view_storage')) {
            Response::error(403, 'Permission denied');
        }

        $datatype = \App::$argv[3] ?? 'summary';
        $datum    = \App::$argv[4] ?? '';

        // GET /api/photos/:nick/(image|album)/:id/acl
        if ((\App::$argv[5] ?? '') === 'acl') {
            if (!local_channel() || local_channel() != $owner_uid)
                Response::error(403, 'Owner access required');
            $this->getAcl($channel, $datatype, $datum);
            return;
        }

        switch ($datatype) {
            case 'albums':
                $this->getAlbumsSummary($channel, $ob_hash);
                break;
            case 'album':
                $this->getAlbum($channel, $ob_hash, $datum);
                break;
            case 'image':
                $this->getImage($channel, $ob_hash, $datum);
                break;
            default:
                $this->getSummary($channel, $ob_hash);
                break;
        }
    }

    private function getSummary(array $channel, string $ob_hash): void
    {
        // Recent photos — last 8, any album
        $sql_extra = permissions_sql($channel['channel_id'], $ob_hash, 'photo');
        $ph_drv = photo_factory('');
        $phototypes = $ph_drv->supportedTypes();

        $r = dbq('SELECT resource_id, filename, mimetype, imgscale, description, album, created
              FROM photo
              WHERE uid = ' . intval($channel['channel_id']) . '
                AND photo_usage IN (' . PHOTO_NORMAL . ',' . PHOTO_PROFILE . ")
                AND imgscale = 2
                $sql_extra
              ORDER BY created DESC
              LIMIT 8");

        $out = [];
        foreach (($r ?: []) as $row) {
            $ext = $phototypes[$row['mimetype']] ?? 'jpg';
            $out[] = [
                'resource_id' => $row['resource_id'],
                'filename' => $row['filename'],
                'description' => $row['description'] ?? '',
                'album' => $row['album'],
                'created' => $row['created'],
                'src' => z_root() . '/photo/' . $row['resource_id'] . '-' . $row['imgscale'] . '.' . $ext,
                'link' => z_root() . '/photos/' . $channel['channel_address'] . '/image/' . $row['resource_id'],
            ];
        }

        Response::send($out);
    }

    private function getAlbumsSummary(array $channel, string $ob_hash): void
    {
        require_once 'include/attach.php';
        require_once 'include/photos.php';

        $result = photos_albums_list($channel, \App::get_observer());

        if (empty($result['success'])) {
            Response::send([]);
            return;
        }

        // photos_albums_list returns entries with bin2hex (folder hash) and text (display name)
        // Fetch a thumbnail for each album from the first photo in that folder
        $ph_drv = photo_factory('');
        $phototypes = $ph_drv->supportedTypes();
        $sql_extra = permissions_sql($channel['channel_id'], $ob_hash, 'photo');

        $albums = [];
        foreach (($result['albums'] ?? []) as $album) {
            $folder = $album['bin2hex'];  // folder hash
            $name = $album['text'];
            $total = intval($album['total']);

            // Fetch one thumbnail photo from this folder
            $thumb = null;
            if ($folder) {
                $t = dbq("SELECT p.resource_id, p.mimetype, p.imgscale
                      FROM photo p
                      INNER JOIN attach a ON a.hash = p.resource_id
                      WHERE a.folder = '" . dbesc($folder) . "'
                        AND p.uid = " . intval($channel['channel_id']) . '
                        AND p.imgscale = 2
                        AND p.photo_usage IN (' . PHOTO_NORMAL . ',' . PHOTO_PROFILE . ")
                        $sql_extra
                      LIMIT 1");
                if ($t) {
                    $ext = $phototypes[$t[0]['mimetype']] ?? 'jpg';
                    $thumb = z_root() . '/photo/' . $t[0]['resource_id'] . '-' . $t[0]['imgscale'] . '.' . $ext;
                }
            }

            $albums[] = [
                'album' => $name,
                'folder' => $folder,
                'total' => $total,
                'url' => $album['url'],
                'thumb' => $thumb,
            ];
        }

        Response::send($albums);
    }

    private function getAlbum(array $channel, string $ob_hash, string $albumHash): void
    {
        require_once 'include/photos.php';
        require_once 'include/attach.php';

        // Empty hash → root-level photos (not inside any folder)
        if ($albumHash === '') {
            $this->getRootPhotos($channel, $ob_hash);
            return;
        }

        // Verify album exists and observer can see it
        $album_row = photos_album_exists($channel['channel_id'], $ob_hash, $albumHash);
        if (!$album_row)
            Response::error(404, 'Album not found');

        $display_path = $album_row['display_path'];
        $sql_extra = permissions_sql($channel['channel_id'], $ob_hash, 'photo');
        $ph_drv = photo_factory('');
        $phototypes = $ph_drv->supportedTypes();

        $r = dbq("SELECT p.resource_id, p.filename, p.mimetype, p.imgscale,
                     p.description, p.album, p.created
              FROM photo p
              INNER JOIN attach a ON a.hash = p.resource_id
              WHERE a.folder = '" . dbesc($albumHash) . "'
                AND p.uid = " . intval($channel['channel_id']) . '
                AND p.imgscale = 2
                AND p.photo_usage IN (' . PHOTO_NORMAL . ',' . PHOTO_PROFILE . ")
                $sql_extra
              ORDER BY p.created DESC");

        $out = [];
        foreach (($r ?: []) as $row) {
            $ext = $phototypes[$row['mimetype']] ?? 'jpg';
            $out[] = [
                'resource_id' => $row['resource_id'],
                'filename' => $row['filename'],
                'description' => $row['description'] ?? '',
                'album' => $row['album'],
                'created' => $row['created'],
                'src' => z_root() . '/photo/' . $row['resource_id'] . '-' . $row['imgscale'] . '.' . $ext,
                'link' => z_root() . '/photos/' . $channel['channel_address'] . '/image/' . $row['resource_id'],
            ];
        }

        Response::send($out, ['album_name' => $display_path]);
    }

    // ── GET /api/photos/:nick/image/:id — single photo ────────────────────────

    private function getImage(array $channel, string $ob_hash, string $resourceId): void
    {
        if (!$resourceId)
            Response::error(400, 'Photo resource_id required');

        $owner_uid = intval($channel['channel_id']);
        $sql_extra = permissions_sql($owner_uid, $ob_hash, 'photo');
        $sql_attach = permissions_sql($owner_uid, $ob_hash, 'attach');
        $sql_item = item_permissions_sql($owner_uid, $ob_hash);
        $item_normal = item_normal();

        $ph_drv = photo_factory('');
        $phototypes = $ph_drv->supportedTypes();

        // ── Photo rows ────────────────────────────────────────────────────────
        $ph = dbq("SELECT id, uid, resource_id, created, edited,
                          title, description, album, filename, mimetype,
                          height, width, filesize, imgscale, photo_usage,
                          is_nsfw, allow_cid, allow_gid, deny_cid, deny_gid
                   FROM photo
                   WHERE uid = $owner_uid
                     AND resource_id = '" . dbesc($resourceId) . "'
                     $sql_extra
                   ORDER BY imgscale ASC");

        if (!$ph)
            Response::error(404, 'Photo not found or permission denied');

        // ── Verify attach visibility ──────────────────────────────────────────
        $x = dbq("SELECT folder FROM attach
                  WHERE hash = '" . dbesc($resourceId) . "'
                    AND uid = $owner_uid
                    $sql_attach
                  LIMIT 1");

        if (!$x)
            Response::error(403, 'Permission denied');

        $ext = $phototypes[$ph[0]['mimetype']] ?? 'jpg';
        $hires = $ph[0];
        $lores = $ph[1] ?? $ph[0];

        $is_private = (
            strlen($ph[0]['allow_cid']) ||
            strlen($ph[0]['allow_gid']) ||
            strlen($ph[0]['deny_cid']) ||
            strlen($ph[0]['deny_gid'])
        );

        // ── Linked item — reactions + comments ────────────────────────────────
        $like_count = 0;
        $dislike_count = 0;
        $viewer_liked = false;
        $viewer_disliked = false;
        $item_id = null;
        $item_mid = null;
        $comments = [];

        $linked = dbq("SELECT * FROM item
                       WHERE resource_id = '" . dbesc($resourceId) . "'
                         AND resource_type = 'photo'
                         $sql_item
                       LIMIT 1");

        if ($linked) {
            xchan_query($linked);
            $linked = fetch_post_tags($linked, true);
            $link_item = $linked[0];
            $item_id = intval($link_item['id']);
            $item_mid = $link_item['mid'];

            $reactions = dbq("SELECT verb, author_xchan FROM item
                              WHERE parent_mid = '" . dbesc($link_item['mid']) . "'
                                AND verb IN ('Like','Dislike')
                                AND item_deleted = 0
                                $item_normal
                                AND uid = $owner_uid");

            foreach (($reactions ?: []) as $react) {
                if ($react['verb'] === 'Like')
                    $like_count++;
                if ($react['verb'] === 'Dislike')
                    $dislike_count++;
                if ($ob_hash && $react['author_xchan'] === $ob_hash) {
                    if ($react['verb'] === 'Like')
                        $viewer_liked = true;
                    if ($react['verb'] === 'Dislike')
                        $viewer_disliked = true;
                }
            }

            $comment_rows = dbq("SELECT * FROM item
                                 WHERE parent_mid = '" . dbesc($link_item['mid']) . "'
                                   AND verb NOT IN ('Like','Dislike')
                                   $item_normal
                                   AND uid = $owner_uid
                                   $sql_item
                                 ORDER BY created ASC");

            if ($comment_rows) {
                xchan_query($comment_rows);
                $comment_rows = fetch_post_tags($comment_rows, true);
                foreach ($comment_rows as $c) {
                    $comments[] = [
                        'iid' => intval($c['id']),
                        'mid' => $c['mid'],
                        'body' => $c['body'],
                        'created' => $c['created'],
                        'author' => [
                            'name' => $c['author']['xchan_name'] ?? '',
                            'url' => $c['author']['xchan_url'] ?? '',
                            'photo' => $c['author']['xchan_photo_m'] ?? '',
                        ],
                    ];
                }
            }
        }

        // ── Prev / next within album ──────────────────────────────────────────
        $prevlink = null;
        $nextlink = null;
        $base = z_root() . '/photos/' . $channel['channel_address'] . '/image/';

        if ($x) {
            $siblings = dbq("SELECT hash FROM attach
                             WHERE folder = '" . dbesc($x[0]['folder']) . "'
                               AND uid = $owner_uid
                               AND is_photo = 1
                               $sql_attach
                             ORDER BY created DESC");
            if ($siblings) {
                $hashes = array_column($siblings, 'hash');
                $pos = array_search($resourceId, $hashes);
                if ($pos !== false) {
                    $prevlink = $base . $hashes[($pos - 1 + count($hashes)) % count($hashes)];
                    $nextlink = $base . $hashes[($pos + 1) % count($hashes)];
                }
            }
        }

        Response::send([
            'resource_id' => $ph[0]['resource_id'],
            'filename' => $ph[0]['filename'],
            'description' => $ph[0]['description'],
            'album' => $ph[0]['album'],
            'album_link' => $x ? z_root() . '/photos/' . $channel['channel_address'] . '/album/' . $x[0]['folder'] : null,
            'created' => $ph[0]['created'],
            'width' => intval($ph[0]['width']),
            'height' => intval($ph[0]['height']),
            'is_nsfw' => (bool) intval($ph[0]['is_nsfw']),
            'is_private' => (bool) $is_private,
            'src' => z_root() . '/photo/' . $lores['resource_id'] . '-' . $lores['imgscale'] . '.' . $ext,
            'src_full' => z_root() . '/photo/' . $hires['resource_id'] . '-' . $hires['imgscale'] . '.' . $ext,
            'prevlink' => $prevlink,
            'nextlink' => $nextlink,
            'like_count' => $like_count,
            'dislike_count' => $dislike_count,
            'viewer_liked' => $viewer_liked,
            'viewer_disliked' => $viewer_disliked,
            'item_id' => $item_id,
            'item_mid' => $item_mid,
            'comments' => $comments,
        ]);
    }

    // ── POST /api/photos/:nick/image/:resource_id/edit ────────────────────────
    // Saves the edited image as a NEW copy in the same album; never touches the original.

    public function post(): void
    {
        require_once 'include/photo/photo_driver.php';
        require_once 'include/attach.php';
        require_once 'include/photos.php';
        require_once 'include/security.php';

        $datatype = \App::$argv[3] ?? '';

        // POST /api/photos/:nick/albums — create a new album (JSON)
        if ($datatype === 'albums') {
            Auth::requireLocalJson();
            $this->createAlbum();
            return;
        }

        // POST /api/photos/:nick/image/:id/rename — rename photo (JSON)
        if ($datatype === 'image' && (\App::$argv[5] ?? '') === 'rename') {
            Auth::requireLocalJson();
            $this->renamePhoto(\App::$argv[4] ?? '');
            return;
        }

        // POST /api/photos/:nick/(image|album)/:id/acl — save privacy ACL (JSON)
        if (in_array($datatype, ['image', 'album']) && (\App::$argv[5] ?? '') === 'acl') {
            Auth::requireLocalJson();
            $this->postAcl($datatype, \App::$argv[4] ?? '');
            return;
        }

        $uid      = Auth::requireLocalMultipart();
        $channel  = \App::get_channel();
        $origId   = \App::$argv[4] ?? '';
        $action   = \App::$argv[5] ?? '';

        if ($datatype !== 'image' || !$origId) {
            Response::error(400, 'Invalid request');
        }

        if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            Response::error(400, 'No file uploaded');
        }

        // ── POST /api/photos/:nick/image/upload ─────────────────────────────────
        // Upload a new photo to the user's library (no original required).
        if ($origId === 'upload') {
            $album = trim($_POST['album'] ?? '');
            // If a folder hash is provided, look up the album display name from it
            if (!$album && !empty($_POST['folder'])) {
                $f = q("SELECT filename FROM attach WHERE hash = '%s' AND uid = %d AND is_dir = 1 LIMIT 1",
                    dbesc($_POST['folder']), intval($uid));
                if ($f) $album = $f[0]['filename'];
            }
            $newHash = photo_new_resource();

            $_FILES['userfile'] = $_FILES['file'];
            $res = attach_store($channel, get_observer_hash(), '', [
                'album'  => $album,
                'hash'   => $newHash,
                'nosync' => true,
                'source' => 'photos',
            ]);

            if (!$res || !intval($res['data']['is_photo'] ?? 0)) {
                Response::error(500, 'Image save failed');
            }

            $rows = q("SELECT * FROM photo WHERE resource_id = '%s' AND uid = %d ORDER BY imgscale ASC LIMIT 1",
                      dbesc($newHash), intval($uid));

            if (!$rows) {
                Response::error(500, 'Photo record not found after save');
            }

            $base      = $rows[0];
            $imagedata = intval($base['os_storage'])
                ? @file_get_contents(dbunescbin($base['content']))
                : dbunescbin($base['content']);

            $im = photo_factory($imagedata, $base['mimetype']);
            if (!$im->is_valid()) {
                Response::error(500, 'Unable to process image');
            }

            $ph_drv    = photo_factory('');
            $phototypes = $ph_drv->supportedTypes();
            $ext       = $phototypes[$base['mimetype']] ?? 'jpg';
            $fullScale = intval($base['imgscale']);

            $p = [
                'aid'          => get_account_id(),
                'uid'          => $uid,
                'resource_id'  => $newHash,
                'filename'     => basename($_FILES['file']['name'] ?? 'photo.jpg'),
                'description'  => '',
                'album'        => $album,
                'os_path'      => $base['os_path'] ?? '',
                'display_path' => $base['display_path'] ?? '',
                'photo_usage'  => PHOTO_NORMAL,
                'allow_cid'    => $channel['channel_allow_cid'],
                'allow_gid'    => $channel['channel_allow_gid'],
                'deny_cid'     => $channel['channel_deny_cid'],
                'deny_gid'     => $channel['channel_deny_gid'],
            ];

            $im->scaleImage(1024);
            $im->storeThumbnail($p, 1);

            $im2 = photo_factory($imagedata, $base['mimetype']);
            $im2->scaleImage(320);
            $im2->storeThumbnail($p, 2);

            $t = time();
            Response::send([
                'resource_id' => $newHash,
                'src'         => z_root() . '/photo/' . $newHash . '-2.' . $ext . '?t=' . $t,
                'src_full'    => z_root() . '/photo/' . $newHash . '-' . $fullScale . '.' . $ext . '?t=' . $t,
            ]);
            return;
        }

        if ($action !== 'edit') {
            Response::error(400, 'Expected /photos/:nick/image/:id/edit');
        }

        // Verify the original belongs to this user and get its album
        $existing = q("SELECT filename, description, album, allow_cid, allow_gid, deny_cid, deny_gid
                       FROM photo
                       WHERE uid = %d AND resource_id = '%s' AND photo_usage = %d
                       LIMIT 1",
                      intval($uid), dbesc($origId), intval(PHOTO_NORMAL));

        if (!$existing) {
            Response::error(404, 'Photo not found or not yours');
        }

        $meta    = $existing[0];
        $newHash = photo_new_resource();

        // Store the edited file as a fresh attachment in the same album (original untouched)
        $_FILES['userfile'] = $_FILES['file'];
        $res = attach_store($channel, get_observer_hash(), '', [
            'album'  => $meta['album'],
            'hash'   => $newHash,
            'nosync' => true,
            'source' => 'photos',
        ]);

        if (!$res || !intval($res['data']['is_photo'] ?? 0)) {
            Response::error(500, 'Image save failed');
        }

        // Load the photo row created by attach_store to get image data + mimetype
        $rows = q("SELECT * FROM photo WHERE resource_id = '%s' AND uid = %d ORDER BY imgscale ASC LIMIT 1",
                  dbesc($newHash), intval($uid));

        if (!$rows) {
            Response::error(500, 'Photo record not found after save');
        }

        $base      = $rows[0];
        $imagedata = intval($base['os_storage'])
            ? @file_get_contents(dbunescbin($base['content']))
            : dbunescbin($base['content']);

        $im = photo_factory($imagedata, $base['mimetype']);
        if (!$im->is_valid()) {
            Response::error(500, 'Unable to process saved image');
        }

        $ph_drv    = photo_factory('');
        $phototypes = $ph_drv->supportedTypes();
        $ext       = $phototypes[$base['mimetype']] ?? 'jpg';
        $fullScale = intval($base['imgscale']);

        $p = [
            'aid'          => get_account_id(),
            'uid'          => $uid,
            'resource_id'  => $newHash,
            'filename'     => $meta['filename'],
            'description'  => $meta['description'],
            'album'        => $meta['album'],
            'os_path'      => $base['os_path'] ?? '',
            'display_path' => $base['display_path'] ?? '',
            'photo_usage'  => PHOTO_NORMAL,
            'allow_cid'    => $meta['allow_cid'],
            'allow_gid'    => $meta['allow_gid'],
            'deny_cid'     => $meta['deny_cid'],
            'deny_gid'     => $meta['deny_gid'],
            'edited'       => dbescdate($base['edited']),
        ];

        // Medium scale (imgscale 1, ≤1024px) — for ImageView display
        $im->scaleImage(1024);
        $im->storeThumbnail($p, 1);

        // Thumbnail (imgscale 2, ≤320px) — for the photo grid
        $im2 = photo_factory($imagedata, $base['mimetype']);
        $im2->scaleImage(320);
        $im2->storeThumbnail($p, 2);

        $t = time();
        Response::send([
            'resource_id' => $newHash,
            'src'         => z_root() . '/photo/' . $newHash . '-2.' . $ext . '?t=' . $t,
            'src_full'    => z_root() . '/photo/' . $newHash . '-' . $fullScale . '.' . $ext . '?t=' . $t,
        ]);
    }

    // ── DELETE /api/photos/:nick/* ────────────────────────────────────────────

    public function delete(): void
    {
        require_once 'include/photo/photo_driver.php';
        require_once 'include/attach.php';
        require_once 'include/photos.php';
        require_once 'include/security.php';

        $uid     = Auth::requireLocalJson();
        $channel = \App::get_channel();
        $dtype   = \App::$argv[3] ?? '';
        $datum   = \App::$argv[4] ?? '';

        if ($dtype === 'image' && $datum) { $this->deletePhoto($uid, $channel, $datum); return; }
        if ($dtype === 'images') {
            $ids = Auth::$parsedBody['resource_ids'] ?? [];
            if (!is_array($ids) || empty($ids)) Response::error(400, 'resource_ids required');
            $this->batchDeletePhotos($uid, $channel, $ids); return;
        }
        if ($dtype === 'album' && $datum) { $this->deleteAlbum($uid, $channel, $datum); return; }
        Response::error(400, 'Invalid request');
    }

    private function deletePhoto(int $uid, array $channel, string $resourceId): void
    {
        $r = q("SELECT id FROM photo WHERE uid = %d AND resource_id = '%s' LIMIT 1",
            intval($uid), dbesc($resourceId));
        if (!$r) Response::error(404, 'Photo not found or not yours');
        attach_delete($uid, $resourceId, true);
        $sync = attach_export_data($channel, $resourceId, true);
        if ($sync) Libsync::build_sync_packet($uid, ['file' => [$sync]]);
        Response::send(['deleted' => true]);
    }

    private function batchDeletePhotos(int $uid, array $channel, array $resourceIds): void
    {
        $deleted = [];
        foreach ($resourceIds as $rid) {
            $rid = strval($rid);
            $r = q("SELECT id FROM photo WHERE uid = %d AND resource_id = '%s' LIMIT 1",
                intval($uid), dbesc($rid));
            if (!$r) continue;
            attach_delete($uid, $rid, true);
            $sync = attach_export_data($channel, $rid, true);
            if ($sync) Libsync::build_sync_packet($uid, ['file' => [$sync]]);
            $deleted[] = $rid;
        }
        Response::send(['deleted' => $deleted]);
    }

    private function deleteAlbum(int $uid, array $channel, string $folderHash): void
    {
        $f = q("SELECT id FROM attach WHERE uid = %d AND hash = '%s' AND is_dir = 1 LIMIT 1",
            intval($uid), dbesc($folderHash));
        if (!$f) Response::error(404, 'Album not found or not yours');

        $attachPhotos = q("SELECT hash FROM attach WHERE folder = '%s' AND uid = %d AND is_photo = 1",
            dbesc($folderHash), intval($uid));

        if ($attachPhotos) {
            foreach ($attachPhotos as $p) {
                $rid = $p['hash'];
                $items = q("SELECT resource_id FROM item WHERE resource_id = '%s' AND resource_type = 'photo' AND uid = %d LIMIT 1",
                    dbesc($rid), intval($uid));
                if ($items) attach_delete($uid, $rid, true);
            }
            $str = implode("','", array_map(fn($p) => dbesc($p['hash']), $attachPhotos));
            q("DELETE FROM photo WHERE resource_id IN ('$str') AND uid = %d", intval($uid));
        }

        attach_delete($uid, $folderHash);
        $sync = attach_export_data($channel, $folderHash, true);
        if ($sync) Libsync::build_sync_packet($uid, ['file' => [$sync]]);

        Response::send(['deleted' => true]);
    }

    // ── Root photos (folder = '') ─────────────────────────────────────────────

    private function getRootPhotos(array $channel, string $ob_hash): void
    {
        require_once 'include/photo/photo_driver.php';

        $sql_extra  = permissions_sql($channel['channel_id'], $ob_hash, 'photo');
        $ph_drv     = photo_factory('');
        $phototypes = $ph_drv->supportedTypes();

        $r = dbq("SELECT p.resource_id, p.filename, p.mimetype, p.imgscale,
                         p.description, p.album, p.created
                  FROM photo p
                  INNER JOIN attach a ON a.hash = p.resource_id
                  WHERE a.folder = ''
                    AND p.uid = " . intval($channel['channel_id']) . '
                    AND p.imgscale = 2
                    AND p.photo_usage IN (' . PHOTO_NORMAL . ',' . PHOTO_PROFILE . ")
                    $sql_extra
                  ORDER BY p.created DESC");

        $out = [];
        foreach (($r ?: []) as $row) {
            $ext    = $phototypes[$row['mimetype']] ?? 'jpg';
            $out[]  = [
                'resource_id' => $row['resource_id'],
                'filename'    => $row['filename'],
                'description' => $row['description'] ?? '',
                'album'       => $row['album'],
                'created'     => $row['created'],
                'src'         => z_root() . '/photo/' . $row['resource_id'] . '-' . $row['imgscale'] . '.' . $ext,
                'link'        => z_root() . '/photos/' . $channel['channel_address'] . '/image/' . $row['resource_id'],
            ];
        }

        Response::send($out, ['album_name' => '']);
    }

    // ── ACL helpers ───────────────────────────────────────────────────────────

    private function parseAclField(string $field): array
    {
        if (!$field) return [];
        preg_match_all('/<([^>]+)>/', $field, $m);
        return $m[1] ?? [];
    }

    private function buildAclField(array $ids): string
    {
        $ids = array_filter(array_map('strval', $ids));
        return $ids ? '<' . implode('><', $ids) . '>' : '';
    }

    // ── GET /api/photos/:nick/(image|album)/:id/acl ───────────────────────────

    private function getAcl(array $channel, string $type, string $datum): void
    {
        $uid = local_channel();

        $gRows  = q("SELECT id, gname FROM `groups` WHERE uid = %d AND deleted = 0 ORDER BY gname ASC",
            intval($uid));
        $groups = array_map(fn($g) => ['id' => strval($g['id']), 'name' => $g['gname']], $gRows ?: []);

        if ($type === 'image') {
            $r = q("SELECT allow_cid, allow_gid, deny_cid, deny_gid FROM photo
                    WHERE uid = %d AND resource_id = '%s' LIMIT 1",
                intval($uid), dbesc($datum));
            if (!$r) Response::error(404, 'Photo not found');
        } else {
            $r = q("SELECT allow_cid, allow_gid, deny_cid, deny_gid FROM attach
                    WHERE uid = %d AND hash = '%s' AND is_dir = 1 LIMIT 1",
                intval($uid), dbesc($datum));
            if (!$r) Response::error(404, 'Album not found');
        }

        $row = $r[0];
        Response::send([
            'allow_cid' => $this->parseAclField($row['allow_cid']),
            'allow_gid' => $this->parseAclField($row['allow_gid']),
            'deny_cid'  => $this->parseAclField($row['deny_cid']),
            'deny_gid'  => $this->parseAclField($row['deny_gid']),
            'groups'    => $groups,
        ]);
    }

    // ── POST /api/photos/:nick/(image|album)/:id/acl ──────────────────────────

    private function postAcl(string $type, string $datum): void
    {
        require_once 'include/attach.php';

        $uid     = local_channel();
        $channel = \App::get_channel();
        $body    = Auth::$parsedBody;

        $allow_gid = $this->buildAclField($body['allow_gid'] ?? []);
        $allow_cid = $this->buildAclField($body['allow_cid'] ?? []);
        $deny_gid  = $this->buildAclField($body['deny_gid']  ?? []);
        $deny_cid  = $this->buildAclField($body['deny_cid']  ?? []);

        if ($type === 'image') {
            if (!$datum) Response::error(400, 'resource_id required');
            q("UPDATE photo SET allow_gid = '%s', allow_cid = '%s', deny_gid = '%s', deny_cid = '%s'
               WHERE uid = %d AND resource_id = '%s'",
                dbesc($allow_gid), dbesc($allow_cid), dbesc($deny_gid), dbesc($deny_cid),
                intval($uid), dbesc($datum));
            q("UPDATE attach SET allow_gid = '%s', allow_cid = '%s', deny_gid = '%s', deny_cid = '%s'
               WHERE uid = %d AND hash = '%s'",
                dbesc($allow_gid), dbesc($allow_cid), dbesc($deny_gid), dbesc($deny_cid),
                intval($uid), dbesc($datum));
        } else {
            if (!$datum) Response::error(400, 'folder hash required');
            q("UPDATE attach SET allow_gid = '%s', allow_cid = '%s', deny_gid = '%s', deny_cid = '%s'
               WHERE uid = %d AND hash = '%s' AND is_dir = 1",
                dbesc($allow_gid), dbesc($allow_cid), dbesc($deny_gid), dbesc($deny_cid),
                intval($uid), dbesc($datum));
        }

        $sync = attach_export_data($channel, $datum, false);
        if ($sync) Libsync::build_sync_packet($uid, ['file' => [$sync]]);

        Response::send(['ok' => true]);
    }

    // ── POST /api/photos/:nick/image/:id/rename ───────────────────────────────

    private function renamePhoto(string $resourceId): void
    {
        require_once 'include/attach.php';

        $uid     = local_channel();
        $channel = \App::get_channel();
        $newName = trim(Auth::$parsedBody['filename'] ?? '');

        if (!$newName)
            Response::error(400, 'filename required');

        $r = q("SELECT id FROM photo WHERE uid = %d AND resource_id = '%s' LIMIT 1",
            intval($uid), dbesc($resourceId));

        if (!$r)
            Response::error(404, 'Photo not found or not yours');

        q("UPDATE photo SET filename = '%s' WHERE uid = %d AND resource_id = '%s'",
            dbesc($newName), intval($uid), dbesc($resourceId));

        q("UPDATE attach SET filename = '%s' WHERE uid = %d AND hash = '%s'",
            dbesc($newName), intval($uid), dbesc($resourceId));

        $sync = attach_export_data($channel, $resourceId, false);
        if ($sync) Libsync::build_sync_packet($uid, ['file' => [$sync]]);

        Response::send(['filename' => $newName]);
    }

    // ── POST /api/photos/:nick/albums — create album ──────────────────────────

    private function createAlbum(): void
    {
        $channel = \App::get_channel();
        $data    = Auth::$parsedBody;
        $name    = trim($data['name'] ?? '');

        if (!$name) {
            Response::error(400, 'Album name required');
        }

        $res = attach_mkdir($channel, get_observer_hash(), [
            'filename' => $name,
            'folder'   => '',
        ]);

        if (empty($res['success'])) {
            Response::error(422, $res['message'] ?? 'Could not create album');
        }

        $folder_hash = $res['data']['hash'] ?? '';
        Response::send([
            'album'  => $name,
            'folder' => $folder_hash,
            'total'  => 0,
            'url'    => '',
            'thumb'  => null,
        ]);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function resolveChannel(): array
    {
        $nick = \App::$argv[2] ?? null;

        if ($nick) {
            $channel = channelx_by_nick($nick);
            if (!$channel || $channel['channel_removed']) {
                Response::error(404, 'Channel not found');
            }
            return $channel;
        }

        if (!local_channel()) {
            Response::error(401, 'Authentication required');
        }

        $channel = \App::get_channel();
        if (!$channel)
            Response::error(500, 'Could not resolve channel');
        return $channel;
    }
}
