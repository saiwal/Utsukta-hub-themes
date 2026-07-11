<?php
namespace Theme\Solidified\Api\Handlers;

require_once('include/items.php');

use App;
use Theme\Solidified\Api\Auth;
use Theme\Solidified\Api\Response;
use Zotlabs\Daemon\Master;

/**
 * Scheduled (delayed-publish) posts — items stored with item_delayed = 1 by
 * the composer's "publish at" field. Daemon\Cron publishes them when their
 * created date arrives; until then they are invisible to all item_normal
 * queries, so this handler is the only way to see or manage them.
 */
class Scheduled
{
    // GET  /api/scheduled           → list the local channel's pending posts
    // POST /api/scheduled/publish   → { uuid } publish immediately
    // POST /api/scheduled/delete    → { uuid } cancel (delete) a pending post

    public function get(): void
    {
        Auth::requireLocalGet();
        $uid = local_channel();

        $rows = q(
            "SELECT id, uuid, mid, title, body, created FROM item
             WHERE uid = %d AND item_delayed = 1 AND item_deleted = 0
             ORDER BY created ASC",
            intval($uid)
        );

        Response::send(array_map(fn($r) => [
            'iid'     => intval($r['id']),
            'uuid'    => $r['uuid'],
            'mid'     => $r['mid'],
            'title'   => $r['title'],
            'body'    => $r['body'],
            'created' => $r['created'],
        ], $rows ?: []));
    }

    public function post(): void
    {
        $uid = Auth::requireLocalJson();
        $action = App::$argv[2] ?? '';
        $uuid = trim(Auth::$parsedBody['uuid'] ?? '');

        if (!$uuid) {
            Response::error(400, 'uuid required');
        }

        $rows = q(
            "SELECT * FROM item
             WHERE uid = %d AND uuid = '%s' AND item_delayed = 1 AND item_deleted = 0
             LIMIT 1",
            intval($uid),
            dbesc($uuid)
        );
        if (!$rows) {
            Response::error(404, 'Scheduled post not found');
        }

        switch ($action) {
            case 'publish':
                // Mirror Daemon\Cron's delayed-publish step, but also pull the
                // publish date forward — the item was slated for the future and
                // would otherwise sort ahead of newer posts.
                xchan_query($rows);
                $items = fetch_post_tags($rows);
                $item = $items[0];
                $now = datetime_convert();
                $item['item_delayed'] = 0;
                foreach (['created', 'edited', 'commented', 'received', 'changed'] as $f) {
                    $item[$f] = $now;
                }
                $post = item_store_update($item);
                if (empty($post['success'])) {
                    Response::error(500, 'Publish failed');
                }
                Master::Summon(['Notifier', 'wall-new', $post['item_id']]);
                Response::send(['published' => true]);
                break;

            case 'delete':
                // Never delivered anywhere yet — a plain local drop suffices
                drop_item(intval($rows[0]['id']));
                Response::send(['deleted' => true]);
                break;

            default:
                Response::error(400, 'Unknown action');
        }
    }
}
