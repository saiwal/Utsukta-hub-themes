<?php
namespace Theme\Solidified\Api\Handlers;

use Theme\Solidified\Api\Auth;
use Theme\Solidified\Api\Response;

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
        $datum = \App::$argv[4] ?? '';

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
        require_once \App::$basepath . '/include/attach.php';

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
        if (!$albumHash)
            Response::error(400, 'Album hash required');

        require_once \App::$basepath . '/include/attach.php';

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
