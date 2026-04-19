<?php
namespace Theme\Solidified\Api\Handlers;

use Theme\Solidified\Api\Auth;
use Theme\Solidified\Api\Response;

class Photos {

    // GET /api/photos/:nick           → album list
    // GET /api/photos/:nick/album/:hash → photos in album
    // GET /api/photos/:nick/image/:id   → single photo detail

    public function get(): void {
        require_once 'include/photo/photo.php';
        require_once 'include/items.php';
        require_once 'include/security.php';

        $channel = $this->resolveChannel();
        $owner_uid = intval($channel['channel_id']);
        $observer  = \App::get_observer();
        $ob_hash   = $observer ? $observer['xchan_hash'] : '';

        // Permission check
        if (!perm_is_allowed($owner_uid, $ob_hash, 'view_storage')) {
            Response::error(403, 'Permission denied');
        }

        $datatype = \App::$argv[3] ?? 'summary';
        $datum    = \App::$argv[4] ?? '';

        switch ($datatype) {
            case 'album': $this->getAlbum($channel, $ob_hash, $datum);   break;
            case 'image': $this->getImage($channel, $ob_hash, $datum);   break;
            default:      $this->getSummary($channel, $ob_hash);          break;
        }
    }

    // ── GET /api/photos/:nick — album list ────────────────────────────────────

    private function getSummary(array $channel, string $ob_hash): void {
        $result = photos_albums_list($channel, \App::get_observer());

        // photos_albums_list returns ['success' => true, 'albums' => [...]]
        if (empty($result['success'])) {
            Response::send([]);
            return;
        }

        Response::send($result['albums'] ?? []);
    }

    // ── GET /api/photos/:nick/album/:hash — photos in album ───────────────────

    private function getAlbum(array $channel, string $ob_hash, string $albumHash): void {
        if (!$albumHash) Response::error(400, 'Album hash required');

        $result = photos_list_photos($channel, \App::get_observer(), $albumHash);

        // photos_list_photos returns ['success' => true, 'photos' => [...]]
        if (empty($result['success'])) {
            Response::error(404, 'Album not found');
        }

        Response::send($result['photos'] ?? [], [
            'album' => $albumHash,
        ]);
    }

    // ── GET /api/photos/:nick/image/:id — single photo ────────────────────────

    private function getImage(array $channel, string $ob_hash, string $resourceId): void {
        if (!$resourceId) Response::error(400, 'Photo resource_id required');

        $owner_uid  = intval($channel['channel_id']);
        $sql_extra  = permissions_sql($owner_uid, $ob_hash, 'photo');
        $sql_attach = permissions_sql($owner_uid, $ob_hash, 'attach');
        $sql_item   = item_permissions_sql($owner_uid, $ob_hash);
        $item_normal = item_normal();

        $ph_drv     = photo_factory('');
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

        if (!$ph) Response::error(404, 'Photo not found or permission denied');

        // ── Verify attach visibility ──────────────────────────────────────────
        $x = dbq("SELECT folder FROM attach
                  WHERE hash = '" . dbesc($resourceId) . "'
                    AND uid = $owner_uid
                    $sql_attach
                  LIMIT 1");

        if (!$x) Response::error(403, 'Permission denied');

        $ext   = $phototypes[$ph[0]['mimetype']] ?? 'jpg';
        $hires = $ph[0];
        $lores = $ph[1] ?? $ph[0];

        $is_private = (
            strlen($ph[0]['allow_cid']) ||
            strlen($ph[0]['allow_gid']) ||
            strlen($ph[0]['deny_cid'])  ||
            strlen($ph[0]['deny_gid'])
        );

        // ── Linked item — reactions + comments ────────────────────────────────
        $like_count    = 0;
        $dislike_count = 0;
        $viewer_liked  = false;
        $viewer_disliked = false;
        $item_id       = null;
        $item_mid      = null;
        $comments      = [];

        $linked = dbq("SELECT * FROM item
                       WHERE resource_id = '" . dbesc($resourceId) . "'
                         AND resource_type = 'photo'
                         $sql_item
                       LIMIT 1");

        if ($linked) {
            xchan_query($linked);
            $linked    = fetch_post_tags($linked, true);
            $link_item = $linked[0];
            $item_id   = intval($link_item['id']);
            $item_mid  = $link_item['mid'];

            $reactions = dbq("SELECT verb, author_xchan FROM item
                              WHERE parent_mid = '" . dbesc($link_item['mid']) . "'
                                AND verb IN ('Like','Dislike')
                                AND item_deleted = 0
                                $item_normal
                                AND uid = $owner_uid");

            foreach (($reactions ?: []) as $react) {
                if ($react['verb'] === 'Like')    $like_count++;
                if ($react['verb'] === 'Dislike') $dislike_count++;
                if ($ob_hash && $react['author_xchan'] === $ob_hash) {
                    if ($react['verb'] === 'Like')    $viewer_liked    = true;
                    if ($react['verb'] === 'Dislike') $viewer_disliked = true;
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
                        'iid'     => intval($c['id']),
                        'mid'     => $c['mid'],
                        'body'    => $c['body'],
                        'created' => $c['created'],
                        'author'  => [
                            'name'  => $c['author']['xchan_name']  ?? '',
                            'url'   => $c['author']['xchan_url']   ?? '',
                            'photo' => $c['author']['xchan_photo_m'] ?? '',
                        ],
                    ];
                }
            }
        }

        // ── Prev / next within album ──────────────────────────────────────────
        $prevlink = null;
        $nextlink = null;
        $base     = z_root() . '/photos/' . $channel['channel_address'] . '/image/';

        if ($x) {
            $siblings = dbq("SELECT hash FROM attach
                             WHERE folder = '" . dbesc($x[0]['folder']) . "'
                               AND uid = $owner_uid
                               AND is_photo = 1
                               $sql_attach
                             ORDER BY created DESC");

            if ($siblings) {
                $hashes = array_column($siblings, 'hash');
                $pos    = array_search($resourceId, $hashes);
                if ($pos !== false) {
                    $prevlink = $base . $hashes[($pos - 1 + count($hashes)) % count($hashes)];
                    $nextlink = $base . $hashes[($pos + 1) % count($hashes)];
                }
            }
        }

        Response::send([
            'resource_id'     => $ph[0]['resource_id'],
            'filename'        => $ph[0]['filename'],
            'description'     => $ph[0]['description'],
            'album'           => $ph[0]['album'],
            'album_link'      => $x ? z_root() . '/photos/' . $channel['channel_address'] . '/album/' . $x[0]['folder'] : null,
            'created'         => $ph[0]['created'],
            'width'           => intval($ph[0]['width']),
            'height'          => intval($ph[0]['height']),
            'is_nsfw'         => (bool) intval($ph[0]['is_nsfw']),
            'is_private'      => (bool) $is_private,
            'src'             => z_root() . '/photo/' . $lores['resource_id'] . '-' . $lores['imgscale'] . '.' . $ext,
            'src_full'        => z_root() . '/photo/' . $hires['resource_id'] . '-' . $hires['imgscale'] . '.' . $ext,
            'prevlink'        => $prevlink,
            'nextlink'        => $nextlink,
            'like_count'      => $like_count,
            'dislike_count'   => $dislike_count,
            'viewer_liked'    => $viewer_liked,
            'viewer_disliked' => $viewer_disliked,
            'item_id'         => $item_id,
            'item_mid'        => $item_mid,
            'comments'        => $comments,
        ]);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function resolveChannel(): array {
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
        if (!$channel) Response::error(500, 'Could not resolve channel');
        return $channel;
    }
}
