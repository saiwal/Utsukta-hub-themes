<?php
namespace Theme\Solidified\Api\Handlers;

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
            default      => Response::error(404, "Unknown sub-resource: {$sub}"),
        };
    }

    // ── /api/stream-widgets/tags ─────────────────────────────────────────────
    // Uses Hubzilla's tagadelic() for posts, article_tagadelic() for articles.
    // Both use TERM_HASHTAG and respect item_permissions_sql() internally.

    private function getTags(): void
    {
        $uid  = $this->resolveUid();
        $type = $this->itemType();

        if ($type === 'articles') {
            // article_tagadelic defaults to TERM_CATEGORY — pass TERM_HASHTAG explicitly
            $rows = article_tagadelic($uid, 100, '', '', 'wall', 0, TERM_HASHTAG);
        } else {
            $rows = tagadelic($uid, 100, '', '', 'wall', ITEM_TYPE_POST, TERM_HASHTAG);
        }

        // tagadelic() returns [term, total, weight_class] arrays
        $tags = array_map(fn($r) => [
            'name'  => $r[0],
            'count' => (int) $r[1],
        ], $rows ?: []);

        Response::send(['tags' => $tags]);
    }

    // ── /api/stream-widgets/categories ──────────────────────────────────────
    // Uses article_tagadelic() with TERM_CATEGORY for articles,
    // tagadelic() with TERM_CATEGORY for posts.

    private function getCategories(): void
    {
        $uid  = $this->resolveUid();
        $type = $this->itemType();

        if ($type === 'articles') {
            // article_tagadelic defaults to TERM_CATEGORY
            $rows = article_tagadelic($uid, 0, '', '', 'wall', 0, TERM_CATEGORY);
        } else {
            $rows = tagadelic($uid, 0, '', '', 'wall', ITEM_TYPE_POST, TERM_CATEGORY);
        }

        // tagadelic() returns [term, total, weight_class] arrays
        $categories = array_map(fn($r) => [
            'name'  => $r[0],
            'slug'  => $this->slugify($r[0]),
            'count' => (int) $r[1],
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
                    (SELECT COUNT(*)
                     FROM item r
                     WHERE r.parent       = item.id
                       AND r.item_deleted  = 0
                       AND r.item_thread_top = 0
                       AND r.verb NOT IN ('Like','Dislike','Announce')) AS comment_count
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
