<?php
// Api/Concerns/EnforcesServiceClass.php
namespace Utsukta\SpaCore\Api\Concerns;

use Utsukta\SpaCore\Api\Response;

// Ports core's Zotlabs\Module\Item::item_check_service_class() (which the SPA's
// own Item/Webpages handlers never called, silently letting either quota be
// bypassed) so a new top-level post or webpage gets rejected with the same
// friendly message channel creation already returns via create_identity().
trait EnforcesServiceClass
{
    private function checkTopLevelItemLimit(int $channel_id, bool $isWebpage): void
    {
        require_once('include/text.php');

        if ($isWebpage) {
            $r = q("SELECT COUNT(i.id) AS total FROM item i
                    RIGHT JOIN channel c ON (i.author_xchan = c.channel_hash AND i.uid = c.channel_id)
                    WHERE i.parent = i.id AND i.item_type = %d AND i.item_deleted = 0 AND i.uid = %d",
                intval(ITEM_TYPE_WEBPAGE), $channel_id);
        } else {
            require_once('include/items.php');
            $r = q("SELECT COUNT(id) AS total FROM item WHERE parent = id AND item_wall = 1 AND uid = %d " . item_normal(),
                $channel_id);
        }

        $total    = intval($r[0]['total'] ?? 0);
        $property = $isWebpage ? 'total_pages' : 'total_items';

        if (!service_class_allows($channel_id, $property, $total)) {
            Response::error(400, upgrade_message());
        }
    }
}
