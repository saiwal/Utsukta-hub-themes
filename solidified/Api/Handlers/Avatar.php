<?php

namespace Theme\Solidified\Api\Handlers;

use Theme\Solidified\Api\Auth;
use Theme\Solidified\Api\Response;

class Avatar
{
    public function post(): void
    {
        $uid     = Auth::requireLocalMultipart();
        $channel = \App::get_channel();
        $type    = $_GET['type'] ?? 'avatar';

        if (!in_array($type, ['avatar', 'cover'], true)) {
            Response::error(400, 'Invalid type. Use avatar or cover.');
        }

        if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            $code = $_FILES['file']['error'] ?? UPLOAD_ERR_NO_FILE;
            Response::error(400, 'Upload error: ' . $code);
        }

        require_once('include/attach.php');
        require_once('include/photos.php');
        require_once('include/photo/photo_driver.php');

        $hash  = photo_new_resource();
        $album = ($type === 'avatar') ? t('Profile Photos') : t('Cover Photos');

        $_FILES['userfile'] = $_FILES['file'];

        $res = attach_store($channel, get_observer_hash(), '', [
            'album'  => $album,
            'hash'   => $hash,
            'nosync' => true,
            'source' => 'photos',
        ]);

        if (!$res || !intval($res['data']['is_photo'] ?? 0)) {
            Response::error(500, 'Image upload failed.');
        }

        $rows = q(
            "SELECT * FROM photo WHERE resource_id = '%s' AND uid = %d ORDER BY imgscale ASC LIMIT 1",
            dbesc($hash),
            intval($uid)
        );

        if (!$rows) {
            Response::error(500, 'Photo record not found after upload.');
        }

        $base      = $rows[0];
        $imagedata = intval($base['os_storage'])
            ? @file_get_contents(dbunescbin($base['content']))
            : dbunescbin($base['content']);

        $im = photo_factory($imagedata, $base['mimetype']);

        if (!$im->is_valid()) {
            Response::error(500, 'Unable to process image.');
        }

        $p = [
            'aid'          => get_account_id(),
            'uid'          => $uid,
            'resource_id'  => $hash,
            'filename'     => $base['filename'],
            'album'        => $album,
            'os_path'      => $base['os_path'] ?? '',
            'display_path' => $base['display_path'] ?? '',
            'photo_usage'  => ($type === 'avatar') ? PHOTO_PROFILE : PHOTO_COVER,
            'edited'       => dbescdate($base['edited']),
        ];

        if ($type === 'avatar') {
            $this->processAvatar($uid, $channel, $im, $p, $hash);
        } else {
            $this->processCover($uid, $im, $p, $hash);
        }
    }

    private function processAvatar(int $uid, array $channel, $im, array $p, string $hash): void
    {
        q("UPDATE photo SET photo_usage = %d WHERE photo_usage = %d AND resource_id != '%s' AND uid = %d",
            intval(PHOTO_NORMAL), intval(PHOTO_PROFILE), dbesc($hash), intval($uid));

        $im->scaleImageSquare(300);
        $r1 = $im->storeThumbnail($p, PHOTO_RES_PROFILE_300);
        $im->scaleImageSquare(80);
        $r2 = $im->storeThumbnail($p, PHOTO_RES_PROFILE_80);
        $im->scaleImageSquare(48);
        $r3 = $im->storeThumbnail($p, PHOTO_RES_PROFILE_48);

        if ($r1 === false || $r2 === false || $r3 === false) {
            q("DELETE FROM photo WHERE resource_id = '%s' AND uid = %d AND imgscale IN (%d,%d,%d)",
                dbesc($hash), intval($uid),
                PHOTO_RES_PROFILE_300, PHOTO_RES_PROFILE_80, PHOTO_RES_PROFILE_48);
            Response::error(500, 'Image resize failed.');
        }

        q("UPDATE profile SET photo = '%s', thumb = '%s' WHERE is_default = 1 AND uid = %d",
            dbesc(z_root() . '/photo/profile/l/' . $uid),
            dbesc(z_root() . '/photo/profile/m/' . $uid),
            intval($uid));

        q("UPDATE xchan SET xchan_photo_mimetype = '%s', xchan_photo_date = '%s',
               xchan_photo_l = '%s', xchan_photo_m = '%s', xchan_photo_s = '%s'
           WHERE xchan_hash = '%s'",
            dbesc($im->getType()),
            dbescdate(datetime_convert()),
            dbesc(z_root() . '/photo/profile/l/' . $uid),
            dbesc(z_root() . '/photo/profile/m/' . $uid),
            dbesc(z_root() . '/photo/profile/s/' . $uid),
            dbesc($channel['xchan_hash']));

        $def = q("SELECT id FROM profile WHERE uid = %d AND is_default = 1 LIMIT 1", intval($uid));
        if ($def) {
            photo_profile_setperms($uid, $hash, intval($def[0]['id']));
        }

        $_SESSION['reload_avatar'] = true;
        \Zotlabs\Daemon\Master::Summon(['Directory', $uid]);

        Response::send([
            'avatar_l' => z_root() . '/photo/profile/l/' . $uid . '?t=' . time(),
            'avatar_m' => z_root() . '/photo/profile/m/' . $uid . '?t=' . time(),
            'avatar_s' => z_root() . '/photo/profile/s/' . $uid . '?t=' . time(),
        ]);
    }

    private function processCover(int $uid, $im, array $p, string $hash): void
    {
        q("UPDATE photo SET photo_usage = %d WHERE photo_usage = %d AND uid = %d",
            intval(PHOTO_NORMAL), intval(PHOTO_COVER), intval($uid));

        $im->doScaleImage(1200, 435);
        $r1 = $im->storeThumbnail($p, PHOTO_RES_COVER_1200);
        $im->doScaleImage(850, 310);
        $r2 = $im->storeThumbnail($p, PHOTO_RES_COVER_850);
        $im->doScaleImage(425, 160);
        $r3 = $im->storeThumbnail($p, PHOTO_RES_COVER_425);

        if ($r1 === false || $r2 === false || $r3 === false) {
            q("DELETE FROM photo WHERE resource_id = '%s' AND uid = %d AND imgscale >= 7",
                dbesc($hash), intval($uid));
            Response::error(500, 'Cover resize failed.');
        }

        \Zotlabs\Daemon\Master::Summon(['Directory', $uid]);

        Response::send([
            'cover_url' => z_root() . '/photo/' . $hash . '-' . PHOTO_RES_COVER_1200 . '?t=' . time(),
        ]);
    }
}
