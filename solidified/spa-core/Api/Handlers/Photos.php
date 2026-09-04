<?php
namespace Utsukta\SpaCore\Api\Handlers;

use Utsukta\SpaCore\Api\Auth;
use Utsukta\SpaCore\Api\Concerns\ReactionCounts;
use Utsukta\SpaCore\Api\Response;
use Zotlabs\Lib\Libsync;

class Photos
{
    // Mirrors core's include/photos.php::photo_upload() total-storage check —
    // the SPA stores photo uploads via attach_store() (which only enforces
    // attach_upload_limit), so photo_upload_limit was never checked at all.
    private function checkPhotoUploadLimit(array $owner, int $filesize): void
    {
        require_once('include/text.php');

        $limit = engr_units_to_bytes(service_class_fetch($owner['channel_id'], 'photo_upload_limit'));
        if ($limit === false) return;

        $r = q("SELECT SUM(filesize) AS total FROM photo WHERE aid = %d AND imgscale = 0",
            intval($owner['channel_account_id']));
        $total = intval($r[0]['total'] ?? 0);

        if ($total + $filesize > $limit) {
            Response::error(400, upgrade_message());
        }
    }

    // attach_store() returns ['success' => false, 'message' => '...'] with a
    // specific reason (file-size cap or attach_upload_limit quota) on failure
    // instead of throwing — forward that instead of a blanket 500.
    private function requireAttachStoreSuccess(?array $res): void
    {
        if (!$res || empty($res['success'])) {
            $msg = $res['message'] ?? '';
            Response::error($msg ? 400 : 500, $msg ?: 'Image save failed');
        }
    }

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

        // write_storage is a channel-wide grant (not per-photo/per-album), so
        // it can be resolved once here and handed down — any observer (local
        // or remote) holding it may edit this channel's photos, not just the owner.
        $can_write = (bool) perm_is_allowed($owner_uid, $ob_hash, 'write_storage');

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
                $this->getAlbumsSummary($channel, $ob_hash, $can_write);
                break;
            case 'album':
                $this->getAlbum($channel, $ob_hash, $datum, $can_write);
                break;
            case 'image':
                $this->getImage($channel, $ob_hash, $datum, $can_write);
                break;
            default:
                $this->getSummary($channel, $ob_hash, $can_write);
                break;
        }
    }

    private function getSummary(array $channel, string $ob_hash, bool $can_write): void
    {
        // Recent photos — last 8, any album
        $sql_extra = permissions_sql($channel['channel_id'], $ob_hash, 'photo');
        $ph_drv = photo_factory('');
        $phototypes = $ph_drv->supportedTypes();

        $uid = intval($channel['channel_id']);

        // Over-fetch: the folder-visibility filter below runs after the limit.
        $r = dbq("SELECT photo.resource_id, photo.filename, photo.mimetype, photo.imgscale,
                         photo.title, photo.description, photo.is_nsfw, photo.album, photo.created,
                         photo.allow_cid, photo.allow_gid, photo.deny_cid, photo.deny_gid,
                         COALESCE(a.folder, '') AS fhash
              FROM photo
              LEFT JOIN attach a ON a.hash = photo.resource_id AND a.uid = $uid
              WHERE photo.uid = $uid
                AND photo.photo_usage IN (" . PHOTO_NORMAL . ',' . PHOTO_PROFILE . ")
                AND photo.imgscale = 2
                $sql_extra
              ORDER BY photo.created DESC
              LIMIT 40");

        $out = [];
        foreach (($r ?: []) as $row) {
            if (count($out) >= 8) break;
            $fhash = (string) $row['fhash'];
            if ($fhash !== '' && !$this->canViewFolder($uid, $ob_hash, $fhash)) continue;
            $ext = $phototypes[$row['mimetype']] ?? 'jpg';
            $out[] = [
                'resource_id' => $row['resource_id'],
                'filename' => $row['filename'],
                'title' => $row['title'] ?? '',
                'description' => $row['description'] ?? '',
                'is_nsfw' => (bool) intval($row['is_nsfw'] ?? 0),
                'is_private' => $this->rowIsPrivate($row, $uid, $fhash),
                'album' => $row['album'],
                'created' => $row['created'],
                'src' => z_root() . '/photo/' . $row['resource_id'] . '-' . $row['imgscale'] . '.' . $ext,
                'link' => z_root() . '/photos/' . $channel['channel_address'] . '/image/' . $row['resource_id'],
            ];
        }

        Response::send($out, ['can_write' => $can_write]);
    }

    private function getAlbumsSummary(array $channel, string $ob_hash, bool $can_write): void
    {
        $uid        = intval($channel['channel_id']);
        $ph_drv     = photo_factory('');
        $phototypes = $ph_drv->supportedTypes();

        // 'p' matches the alias in the query below — permissions_sql prefixes column names
        $sql_photo = permissions_sql($uid, $ob_hash, 'p');

        // Count visible photos per folder.
        // LEFT JOIN so photos without an attach record still appear (grouped under root '').
        // Album name comes from p.album (the photo record's own field) — no attach ACL check.
        $counts_raw = dbq(
            "SELECT COALESCE(a.folder, '') AS fhash, p.album AS album_name,
                    COUNT(DISTINCT p.resource_id) AS cnt
             FROM photo p
             LEFT JOIN attach a ON a.hash = p.resource_id AND a.uid = $uid
             WHERE p.uid = $uid
               AND p.imgscale = 2
               AND p.photo_usage IN (" . PHOTO_NORMAL . ',' . PHOTO_PROFILE . ")
               $sql_photo
             GROUP BY fhash, album_name"
        ) ?: [];

        if (!$counts_raw) {
            Response::send([], ['can_write' => $can_write]);
            return;
        }

        $albums = [];
        foreach ($counts_raw as $row) {
            $fhash = (string) $row['fhash'];
            $total = intval($row['cnt']);
            if ($total === 0) continue;
            // The photo rows may be public while the album folder is not —
            // core would refuse to serve them, so don't list them either.
            if ($fhash !== '' && !$this->canViewFolder($uid, $ob_hash, $fhash)) continue;

            $thumb = null;
            if ($fhash !== '') {
                $t = dbq(
                    "SELECT p.resource_id, p.mimetype, p.imgscale
                     FROM photo p
                     INNER JOIN attach a ON a.hash = p.resource_id AND a.uid = $uid
                     WHERE a.folder = '" . dbesc($fhash) . "'
                       AND p.uid = $uid
                       AND p.imgscale = 2
                       AND p.photo_usage IN (" . PHOTO_NORMAL . ',' . PHOTO_PROFILE . ")
                       $sql_photo
                     LIMIT 1"
                );
                if ($t) {
                    $ext   = $phototypes[$t[0]['mimetype']] ?? 'jpg';
                    $thumb = z_root() . '/photo/' . $t[0]['resource_id'] . '-' . $t[0]['imgscale'] . '.' . $ext;
                }
            }

            $albums[] = [
                'album'  => (string) $row['album_name'],
                'folder' => $fhash,
                'total'  => $total,
                'url'    => z_root() . '/photos/' . $channel['channel_address'] . '/album/' . $fhash,
                'thumb'  => $thumb,
            ];
        }

        Response::send($albums, ['can_write' => $can_write]);
    }

    private function getAlbum(array $channel, string $ob_hash, string $albumHash, bool $can_write): void
    {
        require_once 'include/photos.php';
        require_once 'include/attach.php';

        // Empty hash → root-level photos (not inside any folder)
        if ($albumHash === '') {
            $this->getRootPhotos($channel, $ob_hash, $can_write);
            return;
        }

        // Verify album exists and observer can see it
        $album_row = photos_album_exists($channel['channel_id'], $ob_hash, $albumHash);
        if (!$album_row)
            Response::error(404, 'Album not found');

        $display_path = $album_row['display_path'];
        $sql_extra = permissions_sql($channel['channel_id'], $ob_hash, 'p');
        $ph_drv = photo_factory('');
        $phototypes = $ph_drv->supportedTypes();

        $r = dbq("SELECT p.resource_id, p.filename, p.mimetype, p.imgscale,
                     p.title, p.description, p.is_nsfw, p.album, p.created,
                     p.allow_cid, p.allow_gid, p.deny_cid, p.deny_gid
              FROM photo p
              INNER JOIN attach a ON a.hash = p.resource_id AND a.uid = " . intval($channel['channel_id']) . "
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
                'title' => $row['title'] ?? '',
                'description' => $row['description'] ?? '',
                'is_nsfw' => (bool) intval($row['is_nsfw'] ?? 0),
                'is_private' => $this->rowIsPrivate($row, intval($channel['channel_id']), $albumHash),
                'album' => $row['album'],
                'created' => $row['created'],
                'src' => z_root() . '/photo/' . $row['resource_id'] . '-' . $row['imgscale'] . '.' . $ext,
                'link' => z_root() . '/photos/' . $channel['channel_address'] . '/image/' . $row['resource_id'],
            ];
        }

        Response::send($out, ['album_name' => $display_path, 'can_write' => $can_write]);
    }

    // ── GET /api/photos/:nick/image/:id — single photo ────────────────────────

    private function getImage(array $channel, string $ob_hash, string $resourceId, bool $can_write): void
    {
        if (!$resourceId)
            Response::error(400, 'Photo resource_id required');

        $owner_uid = intval($channel['channel_id']);
        $sql_extra = permissions_sql($owner_uid, $ob_hash, 'photo');
        $sql_attach = permissions_sql($owner_uid, $ob_hash, 'attach');
        $sql_item = item_permissions_sql($owner_uid, $ob_hash);
        // Pass $owner_uid so item_normal() recognizes the channel owner and
        // includes their own delayed/moderated comments (see Channel.php).
        $item_normal = item_normal($owner_uid);

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
        // attach_can_view() checks the file's own ACL *and* walks the folder
        // chain — the exact gate core's /photo applies, so we never hand back
        // an image URL that then 403s.
        require_once 'include/attach.php';
        if (!attach_can_view($owner_uid, $ob_hash, $resourceId))
            Response::error(403, 'Permission denied');

        $x = dbq("SELECT folder FROM attach
                  WHERE hash = '" . dbesc($resourceId) . "'
                    AND uid = $owner_uid
                  LIMIT 1");

        $ext = $phototypes[$ph[0]['mimetype']] ?? 'jpg';
        $hires = $ph[0];
        $lores = $ph[1] ?? $ph[0];

        // Effective privacy, not just the row's own ACL: a cover photo is public
        // in the photo table yet unreachable if its album folder is not. Asked
        // against an anonymous observer, which is the question the share sheet
        // needs answered — "will anyone I post this to be able to load it?"
        $is_private = (
            strlen($ph[0]['allow_cid']) ||
            strlen($ph[0]['allow_gid']) ||
            strlen($ph[0]['deny_cid']) ||
            strlen($ph[0]['deny_gid']) ||
            ($x && $x[0]['folder'] && !$this->canViewFolder($owner_uid, '', $x[0]['folder']))
        );

        // ── Linked item — reactions + comments ────────────────────────────────
        $like_count = 0;
        $dislike_count = 0;
        $viewer_liked = false;
        $viewer_disliked = false;
        $item_id = null;
        $item_mid = null;
        $item_uuid = null;
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
            $item_uuid = $link_item['uuid'];

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

            // Allowlist real comment verbs — parent_mid also catches reaction/marker
            // rows (Like, Dislike, Announce, Follow/Ignore thread-subscribe, RSVP),
            // which must never render as blank "comments" (mirrors Item.php::getComments()).
            $comment_rows = dbq("SELECT item.*, " . ReactionCounts::subqueries() . "
                                 FROM item
                                 WHERE parent_mid = '" . dbesc($link_item['mid']) . "'
                                   AND verb IN ('Create','Update','EmojiReact')
                                   $item_normal
                                   AND uid = $owner_uid
                                   $sql_item
                                 ORDER BY created ASC");

            if ($comment_rows) {
                xchan_query($comment_rows);
                $comment_rows = fetch_post_tags($comment_rows, true);
                foreach ($comment_rows as $c) {
                    $c_liked = $c_disliked = false;
                    if ($ob_hash && !empty($c['reaction_verbs'])) {
                        foreach (explode('|', $c['reaction_verbs']) as $rv) {
                            if (!str_contains($rv, ':')) continue;
                            [$rv_verb, $rv_xchan] = explode(':', $rv, 2);
                            if ($rv_xchan !== $ob_hash) continue;
                            if ($rv_verb === 'Like') $c_liked = true;
                            if ($rv_verb === 'Dislike') $c_disliked = true;
                        }
                    }
                    $comments[] = [
                        'iid' => intval($c['id']),
                        'mid' => $c['mid'],
                        'uuid' => $c['uuid'],
                        'parent_mid' => $c['parent_mid'],
                        'thr_parent' => $c['thr_parent'],
                        'item_thread_top' => intval($c['item_thread_top']),
                        'body' => $c['body'],
                        'created' => $c['created'],
                        'like_count' => intval($c['like_count'] ?? 0),
                        'dislike_count' => intval($c['dislike_count'] ?? 0),
                        'viewer_liked' => $c_liked,
                        'viewer_disliked' => $c_disliked,
                        'author' => [
                            'name' => Response::decodeEntities($c['author']['xchan_name'] ?? ''),
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
            'title' => $ph[0]['title'] ?? '',
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
            'item_uuid' => $item_uuid,
            'comments' => $comments,
            'can_write' => $can_write,
        ]);
    }

    // ── POST /api/photos/:nick/image/:resource_id/edit ────────────────────────
    // Saves the edited image as a NEW copy in the same album; never touches the original.

    /** write_storage is a channel-wide grant — any observer (local or remote) holding it may edit, not just the owner. */
    private function requireWrite(int $uid, string $obs_hash): void
    {
        if (!perm_is_allowed($uid, $obs_hash, 'write_storage')) {
            Response::error(403, 'Permission denied');
        }
    }

    public function post(): void
    {
        require_once 'include/photo/photo_driver.php';
        require_once 'include/attach.php';
        require_once 'include/photos.php';
        require_once 'include/security.php';

        $owner = $this->resolveChannel();
        $uid   = intval($owner['channel_id']);

        $datatype = \App::$argv[3] ?? '';

        // POST /api/photos/:nick/albums — create a new album (JSON)
        if ($datatype === 'albums') {
            $obs_hash = Auth::requireLoggedInJson();
            $this->requireWrite($uid, $obs_hash);
            $this->createAlbum($owner);
            return;
        }

        // POST /api/photos/:nick/image/:id/rename — rename photo (JSON)
        if ($datatype === 'image' && (\App::$argv[5] ?? '') === 'rename') {
            $obs_hash = Auth::requireLoggedInJson();
            $this->requireWrite($uid, $obs_hash);
            $this->renamePhoto($uid, $owner, \App::$argv[4] ?? '');
            return;
        }

        // POST /api/photos/:nick/image/:id/title — update title (JSON)
        if ($datatype === 'image' && (\App::$argv[5] ?? '') === 'title') {
            $obs_hash = Auth::requireLoggedInJson();
            $this->requireWrite($uid, $obs_hash);
            $this->updateTitle($uid, \App::$argv[4] ?? '');
            return;
        }

        // POST /api/photos/:nick/image/:id/description — update description (JSON)
        if ($datatype === 'image' && (\App::$argv[5] ?? '') === 'description') {
            $obs_hash = Auth::requireLoggedInJson();
            $this->requireWrite($uid, $obs_hash);
            $this->updateDescription($uid, \App::$argv[4] ?? '');
            return;
        }

        // POST /api/photos/:nick/image/:id/nsfw — toggle NSFW flag (JSON)
        if ($datatype === 'image' && (\App::$argv[5] ?? '') === 'nsfw') {
            $obs_hash = Auth::requireLoggedInJson();
            $this->requireWrite($uid, $obs_hash);
            $this->updateNsfw($uid, \App::$argv[4] ?? '');
            return;
        }

        // POST /api/photos/:nick/(image|album)/:id/acl — save privacy ACL (JSON)
        // ACL changes stay owner-only — write_storage access shouldn't let a
        // visitor grant themselves (or others) broader access.
        if (in_array($datatype, ['image', 'album']) && (\App::$argv[5] ?? '') === 'acl') {
            Auth::requireLocalJson();
            if (local_channel() !== $uid) {
                Response::error(403, 'Owner access required');
            }
            $this->postAcl($uid, $owner, $datatype, \App::$argv[4] ?? '');
            return;
        }

        // Multipart uploads still require a local session on this hub (no
        // remote-visitor multipart auth path exists) — but a local user
        // holding write_storage ACL on another channel may upload into it,
        // not just that channel's own owner.
        Auth::requireLocalMultipart();
        $this->requireWrite($uid, get_observer_hash());

        $origId = \App::$argv[4] ?? '';
        $action = \App::$argv[5] ?? '';

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

            $this->checkPhotoUploadLimit($owner, intval($_FILES['file']['size'] ?? 0));

            $_FILES['userfile'] = $_FILES['file'];
            $res = attach_store($owner, get_observer_hash(), '', [
                'album'  => $album,
                'hash'   => $newHash,
                'nosync' => true,
                'source' => 'photos',
            ]);

            $this->requireAttachStoreSuccess($res);
            if (!intval($res['data']['is_photo'] ?? 0)) {
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
                'aid'          => $owner['channel_account_id'],
                'uid'          => $uid,
                'resource_id'  => $newHash,
                'filename'     => basename($_FILES['file']['name'] ?? 'photo.jpg'),
                'description'  => '',
                'album'        => $album,
                'os_path'      => $base['os_path'] ?? '',
                'display_path' => $base['display_path'] ?? '',
                'photo_usage'  => PHOTO_NORMAL,
                'allow_cid'    => $owner['channel_allow_cid'],
                'allow_gid'    => $owner['channel_allow_gid'],
                'deny_cid'     => $owner['channel_deny_cid'],
                'deny_gid'     => $owner['channel_deny_gid'],
            ];

            $im->scaleImage(1024);
            $im->storeThumbnail($p, 1);

            $im2 = photo_factory($imagedata, $base['mimetype']);
            $im2->scaleImage(320);
            $im2->storeThumbnail($p, 2);

            // attach_store ran with nosync: the scales above are written
            // after it returns, and attach_export_data() reads the photo rows
            // at call time, so syncing there would have shipped a half-built
            // photo. Core defers it the same way and for the same reason —
            // Zotlabs/Storage/Directory.php::createFile() syncs last. Without
            // this a clone received the deletes below but never the upload.
            $sync = attach_export_data($owner, $newHash);
            if ($sync) Libsync::build_sync_packet($uid, ['file' => [$sync]]);

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

        $this->checkPhotoUploadLimit($owner, intval($_FILES['file']['size'] ?? 0));

        // Store the edited file as a fresh attachment in the same album (original untouched)
        $_FILES['userfile'] = $_FILES['file'];
        $res = attach_store($owner, get_observer_hash(), '', [
            'album'  => $meta['album'],
            'hash'   => $newHash,
            'nosync' => true,
            'source' => 'photos',
        ]);

        $this->requireAttachStoreSuccess($res);
        if (!intval($res['data']['is_photo'] ?? 0)) {
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
            'aid'          => $owner['channel_account_id'],
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

        // attach_store ran with nosync: the scales above are written
        // after it returns, and attach_export_data() reads the photo rows
        // at call time, so syncing there would have shipped a half-built
        // photo. Core defers it the same way and for the same reason —
        // Zotlabs/Storage/Directory.php::createFile() syncs last. Without
        // this a clone received the deletes below but never the upload.
        $sync = attach_export_data($owner, $newHash);
        if ($sync) Libsync::build_sync_packet($uid, ['file' => [$sync]]);

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

        $owner    = $this->resolveChannel();
        $uid      = intval($owner['channel_id']);
        $obs_hash = Auth::requireLoggedInJson();
        $this->requireWrite($uid, $obs_hash);

        $dtype   = \App::$argv[3] ?? '';
        $datum   = \App::$argv[4] ?? '';

        if ($dtype === 'image' && $datum) { $this->deletePhoto($uid, $owner, $datum); return; }
        if ($dtype === 'images') {
            $ids = Auth::$parsedBody['resource_ids'] ?? [];
            if (!is_array($ids) || empty($ids)) Response::error(400, 'resource_ids required');
            $this->batchDeletePhotos($uid, $owner, $ids); return;
        }
        // Whole-album deletion stays owner-only — bulk-destructive, unlike
        // deleting a single photo, matches the wiki "delete entire resource" gate.
        if ($dtype === 'album' && $datum) {
            if (!local_channel() || local_channel() !== $uid) {
                Response::error(403, 'Owner access required');
            }
            $this->deleteAlbum($uid, $owner, $datum); return;
        }
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

    private function getRootPhotos(array $channel, string $ob_hash, bool $can_write): void
    {
        require_once 'include/photo/photo_driver.php';

        $sql_extra  = permissions_sql($channel['channel_id'], $ob_hash, 'p');
        $ph_drv     = photo_factory('');
        $phototypes = $ph_drv->supportedTypes();

        $r = dbq("SELECT p.resource_id, p.filename, p.mimetype, p.imgscale,
                         p.title, p.description, p.is_nsfw, p.album, p.created,
                         p.allow_cid, p.allow_gid, p.deny_cid, p.deny_gid
                  FROM photo p
                  INNER JOIN attach a ON a.hash = p.resource_id AND a.uid = " . intval($channel['channel_id']) . "
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
                'title'       => $row['title'] ?? '',
                'description' => $row['description'] ?? '',
                'is_nsfw'     => (bool) intval($row['is_nsfw'] ?? 0),
                'is_private'  => $this->rowIsPrivate($row, intval($channel['channel_id']), ''),
                'album'       => $row['album'],
                'created'     => $row['created'],
                'src'         => z_root() . '/photo/' . $row['resource_id'] . '-' . $row['imgscale'] . '.' . $ext,
                'link'        => z_root() . '/photos/' . $channel['channel_address'] . '/image/' . $row['resource_id'],
            ];
        }

        Response::send($out, ['album_name' => '', 'can_write' => $can_write]);
    }

    // ── ACL helpers ───────────────────────────────────────────────────────────

    /**
     * Can the observer reach this folder (and every folder above it)?
     * This is the gate core's /photo applies via attach_can_view(); listings
     * that skip it advertise thumbnails that then 403. Memoised per request.
     */
    private array $folderVisible = [];

    private function canViewFolder(int $uid, string $ob_hash, string $folder): bool
    {
        require_once 'include/attach.php';
        // Keyed by observer too — the same request asks both "can the viewer
        // see this?" and "could an anonymous visitor?" (the is_private flag).
        $key = $folder . '|' . $ob_hash;
        return $this->folderVisible[$key] ??= attach_can_view_folder($uid, $ob_hash, $folder);
    }

    /**
     * Would an anonymous visitor be refused this photo? The row's own ACL is
     * not the whole answer — a cover photo is public in the photo table but
     * unreachable when its album folder is not. Drives the `is_private` flag,
     * which the share sheet uses to warn before embedding a URL that will 403.
     */
    private function rowIsPrivate(array $row, int $uid, string $folder): bool
    {
        return (bool) (
            strlen($row['allow_cid'] ?? '') ||
            strlen($row['allow_gid'] ?? '') ||
            strlen($row['deny_cid'] ?? '') ||
            strlen($row['deny_gid'] ?? '') ||
            ($folder !== '' && !$this->canViewFolder($uid, '', $folder))
        );
    }

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

        $cRows = q("SELECT x.xchan_hash, x.xchan_name, x.xchan_photo_m
                    FROM abook a
                    JOIN xchan x ON x.xchan_hash = a.abook_xchan
                    WHERE a.abook_channel = %d
                      AND a.abook_self = 0
                      AND a.abook_pending = 0
                      AND a.abook_archived = 0
                      AND a.abook_deleted = 0
                    ORDER BY x.xchan_name ASC",
            intval($uid));
        $connections = array_map(fn($c) => [
            'hash'  => $c['xchan_hash'],
            'name'  => Response::decodeEntities($c['xchan_name']),
            'photo' => $c['xchan_photo_m'] ?? '',
        ], $cRows ?: []);

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
            'allow_cid'   => $this->parseAclField($row['allow_cid']),
            'allow_gid'   => $this->parseAclField($row['allow_gid']),
            'deny_cid'    => $this->parseAclField($row['deny_cid']),
            'deny_gid'    => $this->parseAclField($row['deny_gid']),
            'groups'      => $groups,
            'connections' => $connections,
        ]);
    }

    // ── POST /api/photos/:nick/(image|album)/:id/acl ──────────────────────────

    private function postAcl(int $uid, array $channel, string $type, string $datum): void
    {
        require_once 'include/attach.php';

        $body = Auth::$parsedBody;

        if (($body['scope'] ?? null) === 'private') {
            $allow_gid = '';
            $allow_cid = '<' . $channel['channel_hash'] . '>';
            $deny_gid  = '';
            $deny_cid  = '';
        } else {
            $allow_gid = $this->buildAclField($body['allow_gid'] ?? []);
            $allow_cid = $this->buildAclField($body['allow_cid'] ?? []);
            $deny_gid  = $this->buildAclField($body['deny_gid']  ?? []);
            $deny_cid  = $this->buildAclField($body['deny_cid']  ?? []);
        }

        if (!$datum) {
            Response::error(400, $type === 'image' ? 'resource_id required' : 'folder hash required');
        }

        // Core's helper writes both the attach and photo rows, preserves any
        // existing guest tokens, and syncs. Albums recurse: without that the
        // contained photo rows keep their old (often public) ACL while core's
        // /photo denies them via the folder chain — a public photo that 403s.
        attach_change_permissions(
            $uid, $datum,
            $allow_cid, $allow_gid, $deny_cid, $deny_gid,
            $type === 'album',
            true
        );

        Response::send(['ok' => true]);
    }

    // ── POST /api/photos/:nick/image/:id/title ────────────────────────────────

    private function updateTitle(int $uid, string $resourceId): void
    {
        $title = trim(Auth::$parsedBody['title'] ?? '');

        $r = q("SELECT id FROM photo WHERE uid = %d AND resource_id = '%s' LIMIT 1",
            intval($uid), dbesc($resourceId));

        if (!$r)
            Response::error(404, 'Photo not found or not yours');

        q("UPDATE photo SET title = '%s' WHERE uid = %d AND resource_id = '%s'",
            dbesc($title), intval($uid), dbesc($resourceId));

        Response::send(['title' => $title]);
    }

    // ── POST /api/photos/:nick/image/:id/description ──────────────────────────

    private function updateDescription(int $uid, string $resourceId): void
    {
        $desc = trim(Auth::$parsedBody['description'] ?? '');

        $r = q("SELECT id FROM photo WHERE uid = %d AND resource_id = '%s' LIMIT 1",
            intval($uid), dbesc($resourceId));

        if (!$r)
            Response::error(404, 'Photo not found or not yours');

        q("UPDATE photo SET description = '%s' WHERE uid = %d AND resource_id = '%s'",
            dbesc($desc), intval($uid), dbesc($resourceId));

        Response::send(['description' => $desc]);
    }

    // ── POST /api/photos/:nick/image/:id/nsfw ────────────────────────────────

    private function updateNsfw(int $uid, string $resourceId): void
    {
        $is_nsfw  = !empty(Auth::$parsedBody['is_nsfw']) ? 1 : 0;

        $r = q("SELECT id FROM photo WHERE uid = %d AND resource_id = '%s' LIMIT 1",
            intval($uid), dbesc($resourceId));

        if (!$r)
            Response::error(404, 'Photo not found or not yours');

        q("UPDATE photo SET is_nsfw = %d WHERE uid = %d AND resource_id = '%s'",
            intval($is_nsfw), intval($uid), dbesc($resourceId));

        Response::send(['is_nsfw' => (bool) $is_nsfw]);
    }

    // ── POST /api/photos/:nick/image/:id/rename ───────────────────────────────

    private function renamePhoto(int $uid, array $channel, string $resourceId): void
    {
        require_once 'include/attach.php';

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

    private function createAlbum(array $channel): void
    {
        $data = Auth::$parsedBody;
        $name = trim($data['name'] ?? '');

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
