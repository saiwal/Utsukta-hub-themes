<?php
namespace Theme\Solidified\Api\Handlers;

use Theme\Solidified\Api\Concerns\ReactionCounts;
use Theme\Solidified\Api\Response;

class StreamWidgets
{
    public function get(): void
    {
        require_once 'include/items.php';
        require_once 'include/channel.php';
        require_once 'include/taxonomy.php';

        // URL: /api/stream-widgets/tags
        //      /api/stream-widgets/categories
        //      /api/stream-widgets/popular
        $sub = \App::$argv[2] ?? null;

        if (!$sub) {
            Response::error(400, 'No sub-resource specified');
        }

        match ($sub) {
            'tags'       => $this->getTags(),
            'categories' => $this->getCategories(),
            'popular'    => $this->getPopular(),
            'archive'    => $this->getArchive(),
            default      => Response::error(404, "Unknown sub-resource: {$sub}"),
        };
    }

    // ── /api/stream-widgets/tags ─────────────────────────────────────────────

    private function getTags(): void
    {
        $uid           = $this->resolveUid();
        $type          = $this->itemType();
        $item_type_val = $type === 'articles' ? ITEM_TYPE_ARTICLE : ITEM_TYPE_POST;
        $item_normal   = item_normal(null, 'item', $item_type_val);
        $perm_sql      = item_permissions_sql($uid);

        $rows = dbq(
            "SELECT term.term, COUNT(term.term) AS total
             FROM term
             LEFT JOIN item ON term.oid = item.id
             WHERE term.uid   = " . intval($uid) . "
               AND term.ttype = " . intval(TERM_HASHTAG) . "
               AND term.otype = " . intval(TERM_OBJ_POST) . "
               AND item.item_thread_top = 1
               AND item.item_wall       = 1
               $perm_sql $item_normal
             GROUP BY term.term
             ORDER BY total DESC
             LIMIT 100"
        );

        $tags = array_map(fn($r) => [
            'name'  => $r['term'],
            'count' => (int) $r['total'],
        ], $rows ?: []);

        Response::send(['tags' => $tags]);
    }

    // ── /api/stream-widgets/categories ──────────────────────────────────────

    private function getCategories(): void
    {
        $uid           = $this->resolveUid();
        $type          = $this->itemType();
        $item_type_val = $type === 'articles' ? ITEM_TYPE_ARTICLE : ITEM_TYPE_POST;
        $item_normal   = item_normal(null, 'item', $item_type_val);
        $perm_sql      = item_permissions_sql($uid);

        $rows = dbq(
            "SELECT term.term, COUNT(term.term) AS total
             FROM term
             LEFT JOIN item ON term.oid = item.id
             WHERE term.uid   = " . intval($uid) . "
               AND term.ttype = " . intval(TERM_CATEGORY) . "
               AND term.otype = " . intval(TERM_OBJ_POST) . "
               AND item.item_thread_top = 1
               AND item.item_wall       = 1
               $perm_sql $item_normal
             GROUP BY term.term
             ORDER BY total DESC"
        );

        $categories = array_map(fn($r) => [
            'name'  => $r['term'],
            'slug'  => $r['term'],
            'count' => (int) $r['total'],
        ], $rows ?: []);

        Response::send(['categories' => $categories]);
    }

    // ── /api/stream-widgets/popular ──────────────────────────────────────────
    // Most commented thread-top items for the channel, permission-aware.

    private function getPopular(): void
    {
        require_once 'include/conversation.php';

        $uid           = $this->resolveUid();
        $type          = $this->itemType();
        $limit         = min(20, max(1, (int) ($_GET['limit'] ?? 5)));
        $observer_hash = get_observer_hash();
        $item_normal   = item_normal();

        $item_type_val = $type === 'articles' ? ITEM_TYPE_ARTICLE : ITEM_TYPE_POST;

        $permission_sql = item_permissions_sql($uid, $observer_hash);

        $items = dbq(
            "SELECT item.uuid,
                    item.title,
                    item.body,
                    item.created,
                    item.author_xchan,
                    " . ReactionCounts::commentCountSubquery() . " AS comment_count
             FROM item
             WHERE item.uid             = " . intval($uid) . "
               AND item.item_thread_top = 1
               AND item.item_wall       = 1
               AND item.item_type       = " . intval($item_type_val) . "
               $item_normal
               $permission_sql
             ORDER BY comment_count DESC, item.created DESC
             LIMIT " . intval($limit)
        );

        if (!$items) {
            Response::send(['popular' => []]);
            return;
        }

        xchan_query($items, true);

        $popular = array_map(fn($item) => [
            'uuid'         => $item['uuid'],
            'title'        => $item['title'] ?? '',
            'body'         => $item['body']  ?? '',
            'authorName'   => $item['author']['xchan_name']    ?? '',
            'authorAvatar' => $item['author']['xchan_photo_m'] ?? '',
            'created'      => $item['created'],
            'commentCount' => (int) $item['comment_count'],
        ], $items);

        Response::send(['popular' => $popular]);
    }

    // ── /api/stream-widgets/archive ──────────────────────────────────────────

    private function getArchive(): void
    {
        $uid           = $this->resolveUid();
        $type          = $this->itemType();
        $item_type_val = $type === 'articles' ? ITEM_TYPE_ARTICLE : ITEM_TYPE_POST;
        $item_normal   = item_normal(null, 'item', $item_type_val);
        $perm_sql      = item_permissions_sql($uid);

        $rows = dbq(
            "SELECT YEAR(item.created) AS yr, MONTH(item.created) AS mo, COUNT(*) AS total
             FROM item
             WHERE item.uid             = " . intval($uid) . "
               AND item.item_thread_top = 1
               AND item.item_wall       = 1
               AND item.item_deleted    = 0
               AND item.verb           != 'Add'
               $perm_sql $item_normal
             GROUP BY yr, mo
             ORDER BY yr DESC, mo DESC"
        );

        $years = [];
        foreach ($rows ?: [] as $row) {
            $yr = (int) $row['yr'];
            $mo = (int) $row['mo'];
            $n  = (int) $row['total'];
            if (!isset($years[$yr])) {
                $years[$yr] = ['year' => $yr, 'count' => 0, 'months' => []];
            }
            $years[$yr]['count']    += $n;
            $years[$yr]['months'][]  = ['month' => $mo, 'count' => $n];
        }

        Response::send(['archive' => array_values($years)]);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Resolve channel uid from ?channel_nick=, falling back to local_channel().
     */
    private function resolveUid(): int
    {
        $nick = $_GET['channel_nick'] ?? null;

        if ($nick) {
            $channel = channelx_by_nick($nick);
            if (!$channel) {
                Response::error(404, 'Channel not found');
            }
            return (int) $channel['channel_id'];
        }

        $uid = local_channel();
        if (!$uid) {
            Response::error(401, 'Authentication required');
        }

        return (int) $uid;
    }

    /**
     * Read ?type= param. Returns 'articles' or 'posts'.
     */
    private function itemType(): string
    {
        return ($_GET['type'] ?? '') === 'articles' ? 'articles' : 'posts';
    }

    /**
     * Lowercase slug: spaces → hyphens, strip non-alphanumeric.
     */
    private function slugify(string $term): string
    {
        $slug = mb_strtolower($term);
        $slug = preg_replace('/\s+/', '-', $slug);
        $slug = preg_replace('/[^a-z0-9\-]/', '', $slug);
        return trim($slug, '-');
    }
}
