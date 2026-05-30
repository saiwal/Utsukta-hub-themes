<?php
// Api/Handlers/Itemsrc.php
//
// GET /api/item-source/:iid
//
// Returns the ActivityPub source object for a post or comment.
// Mirrors the logic of core Viewsrc but returns clean JSON with no HTML.

namespace Theme\Solidified\Api\Handlers;

use Zotlabs\Lib\Activity;
use Zotlabs\Lib\IConfig;
use Zotlabs\Lib\ObjCache;

class Itemsrc
{
    public function get(): void
    {
        require_once 'include/items.php';

        if (!local_channel()) {
            json_return_and_die(['error' => 'Authentication required']);
        }

        $iid = intval(\App::$argv[2] ?? 0);

        if (!$iid) {
            json_return_and_die(['error' => 'Item id required']);
        }

        $sys         = get_sys_channel();
        $item_normal = item_normal_search();

        $r = q(
            "SELECT * FROM item WHERE uid IN (%d, %d) AND id = %d $item_normal LIMIT 1",
            intval(local_channel()),
            intval($sys['channel_id']),
            $iid
        );

        if (!$r) {
            json_return_and_die(['error' => 'Item not found']);
        }

        xchan_query($r, true);
        $r    = fetch_post_tags($r);
        $item = $r[0];

        $cached = true;
        $obj    = ObjCache::Get($item['mid']);

        if (!$obj) {
            $obj = IConfig::Get($item, 'activitypub', 'rawmsg');
        }

        if (in_array($item['owner']['xchan_network'] ?? '', ['diaspora'])) {
            $obj = ObjCache::Get($item['mid'], 'diaspora');
            if (!$obj) {
                $obj = IConfig::Get($item, 'diaspora', 'fields');
            }
        }

        if (!$obj) {
            $cached = false;
            $obj    = Activity::encode_activity($item);
        }

        // Cached values may be JSON strings — decode once so the outer
        // json_return_and_die encodes the object rather than double-encoding.
        if (is_string($obj)) {
            $decoded = json_decode($obj, true);
            if ($decoded !== null) {
                $obj = $decoded;
            }
        }

        json_return_and_die([
            'id'     => intval($item['id']),
            'mid'    => $item['mid'],
            'uuid'   => $item['uuid'],
            'plink'  => $item['plink'],
            'llink'  => $item['llink'],
            'cached' => $cached,
            'source' => $obj,
        ]);
    }
}
