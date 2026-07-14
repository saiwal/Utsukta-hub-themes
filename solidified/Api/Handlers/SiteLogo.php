<?php

namespace Theme\Solidified\Api\Handlers;

use Theme\Solidified\Api\Auth;
use Theme\Solidified\Api\Response;
use Zotlabs\Lib\Config;

class SiteLogo
{
    // imgscale values for the generated sizes. The /photo/<hash>-<n> route
    // (Zotlabs\Module\Photo) only parses a single trailing digit, so these
    // must stay 0-9. attach_store() itself auto-populates scales 0-3 for
    // every upload (the generic PHOTO_RES_1024/640/320 bucket), so reuse
    // 4-6 instead to avoid immediately overwriting those. photo_usage is
    // set to PHOTO_NORMAL below, not PHOTO_PROFILE, so Photo.php's
    // profile-specific scale restrictions don't apply to this resource_id.
    private const RES_512 = 4;
    private const RES_192 = 5;
    private const RES_FAVICON = 6;

    public function post(): void
    {
        Auth::requireLocalMultipart();

        if (!local_channel() || !is_site_admin()) {
            Response::error(403, 'Permission denied');
        }

        $channel = get_sys_channel();
        if (!$channel) {
            Response::error(500, 'Sys channel not found.');
        }

        $uid = intval($channel['channel_id']);

        require_once('include/attach.php');
        require_once('include/photos.php');
        require_once('include/photo/photo_driver.php');

        $oldHash = Config::Get('system', 'sitelogo_hash');

        if (($_POST['remove'] ?? '') === '1' && empty($_FILES['file']['name'])) {
            if ($oldHash) {
                attach_delete($uid, $oldHash, 1);
            }
            Config::Delete('system', 'sitelogo_hash');
            Config::Delete('system', 'sitelogo_512');
            Config::Delete('system', 'sitelogo_192');
            Config::Delete('system', 'sitelogo_favicon');
            Response::send(['status' => 'ok']);
        }

        if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            $code = $_FILES['file']['error'] ?? UPLOAD_ERR_NO_FILE;
            Response::error(400, 'Upload error: ' . $code);
        }

        $hash = photo_new_resource();
        $_FILES['userfile'] = $_FILES['file'];

        $res = attach_store($channel, $channel['xchan_hash'], '', [
            'album'  => t('Site Logo'),
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
            'aid'          => intval($channel['channel_account_id']),
            'uid'          => $uid,
            'resource_id'  => $hash,
            'filename'     => $base['filename'],
            'album'        => t('Site Logo'),
            'os_path'      => $base['os_path'] ?? '',
            'display_path' => $base['display_path'] ?? '',
            'photo_usage'  => PHOTO_NORMAL,
            'edited'       => dbescdate($base['edited']),
        ];

        $im->scaleImageSquare(512);
        $r1 = $im->storeThumbnail($p, self::RES_512);
        $im->scaleImageSquare(192);
        $r2 = $im->storeThumbnail($p, self::RES_192);
        $im->scaleImageSquare(32);
        $r3 = $im->storeThumbnail($p, self::RES_FAVICON);

        if ($r1 === false || $r2 === false || $r3 === false) {
            q(
                "DELETE FROM photo WHERE resource_id = '%s' AND uid = %d AND imgscale IN (%d,%d,%d)",
                dbesc($hash),
                intval($uid),
                self::RES_512,
                self::RES_192,
                self::RES_FAVICON
            );
            Response::error(500, 'Image resize failed.');
        }

        if ($oldHash && $oldHash !== $hash) {
            attach_delete($uid, $oldHash, 1);
        }

        $urls = [
            'sitelogo_512'     => z_root() . '/photo/' . $hash . '-' . self::RES_512 . '?t=' . time(),
            'sitelogo_192'     => z_root() . '/photo/' . $hash . '-' . self::RES_192 . '?t=' . time(),
            'sitelogo_favicon' => z_root() . '/photo/' . $hash . '-' . self::RES_FAVICON . '?t=' . time(),
        ];

        Config::Set('system', 'sitelogo_hash', $hash);
        foreach ($urls as $k => $v) {
            Config::Set('system', $k, $v);
        }

        Response::send($urls);
    }
}
