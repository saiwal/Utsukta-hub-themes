<?php
namespace Theme\Solidified\Api\Handlers;

use Theme\Solidified\Api\Response;

/**
 * GET /spa/feed/:nick?type=posts|articles&tag=<tag>&cat=<slug>
 *
 * RSS 2.0 feed generator covering the two filters core's own /feed module
 * (Zotlabs/Module/Feed.php) doesn't support: a single hashtag, and
 * articles-only. Everything else (whole wall, top-level-only, a single
 * category, webpages) is already served by core's /feed and doesn't need
 * this endpoint. tag and cat are mutually exclusive — cat wins if both are
 * given, matching how the SPA's own category/tag stream widgets already
 * clear one when the other is picked.
 *
 * Public: gated the same way core's /feed is, via the channel's view_stream
 * permission for the requesting (possibly anonymous) observer.
 */
class Feed
{
    private const MAX_ITEMS = 30;

    public function get(): void
    {
        require_once 'include/security.php';

        $nick = \App::$argv[2] ?? null;
        if (!$nick) {
            Response::error(400, 'No channel specified');
        }

        $channel = channelx_by_nick($nick);
        if (!$channel || $channel['channel_removed']) {
            Response::error(404, 'Channel not found');
        }

        $observer_hash = get_observer_hash();
        $perms = get_all_perms($channel['channel_id'], $observer_hash);
        if (!$perms['view_stream']) {
            Response::error(403, 'Permission denied');
        }

        $uid = intval($channel['channel_id']);
        $type = ($_GET['type'] ?? '') === 'articles' ? 'articles' : 'posts';
        $item_type_val = $type === 'articles' ? ITEM_TYPE_ARTICLE : ITEM_TYPE_POST;
        $cat = trim((string)($_GET['cat'] ?? ''));
        $tag = trim((string)($_GET['tag'] ?? ''));

        $term_join = '';
        if ($cat !== '') {
            $term_join = "AND item.id IN (SELECT oid FROM term WHERE uid = $uid AND ttype = " . TERM_CATEGORY . " AND term = '" . dbesc($cat) . "')";
        } elseif ($tag !== '') {
            $term_join = "AND item.id IN (SELECT oid FROM term WHERE uid = $uid AND ttype = " . TERM_HASHTAG . " AND term = '" . dbesc($tag) . "')";
        }

        $item_normal = item_normal(null, 'item', $item_type_val);
        $perm_sql = item_permissions_sql($uid, $observer_hash);

        $rows = dbq(
            "SELECT item.uuid, item.mid, item.title, item.body, item.created, item.plink, item.author_xchan
             FROM item
             WHERE item.uid             = $uid
               AND item.item_thread_top = 1
               AND item.item_wall       = 1
               $term_join
               $perm_sql $item_normal
             ORDER BY item.created DESC
             LIMIT " . self::MAX_ITEMS
        );

        header('Content-Type: application/rss+xml; charset=utf-8');
        echo $this->render($channel, $rows ?: []);
    }

    private function render(array $channel, array $rows): string
    {
        $channel_name = htmlspecialchars($channel['channel_name'] ?? $channel['xchan_name'] ?? '', ENT_QUOTES | ENT_XML1);
        $channel_link = htmlspecialchars($channel['xchan_url'] ?? z_root(), ENT_QUOTES | ENT_XML1);

        $items = '';
        foreach ($rows as $row) {
            $link = $row['plink'] ?: $row['mid'];
            $title = $row['title'] !== '' ? $row['title'] : mb_strimwidth(strip_tags(bbcode($row['body'], ['tryoembed' => false])), 0, 80, '…');
            $pubDate = (new \DateTime($row['created'], new \DateTimeZone('UTC')))->format(\DATE_RSS);

            $items .= '<item>'
                . '<title>' . htmlspecialchars($title, ENT_QUOTES | ENT_XML1) . '</title>'
                . '<link>' . htmlspecialchars($link, ENT_QUOTES | ENT_XML1) . '</link>'
                . '<guid isPermaLink="true">' . htmlspecialchars($link, ENT_QUOTES | ENT_XML1) . '</guid>'
                . '<pubDate>' . $pubDate . '</pubDate>'
                . '<description><![CDATA[' . bbcode($row['body'], ['tryoembed' => false]) . ']]></description>'
                . '</item>';
        }

        return '<?xml version="1.0" encoding="UTF-8"?>'
            . '<rss version="2.0"><channel>'
            . '<title>' . $channel_name . '</title>'
            . '<link>' . $channel_link . '</link>'
            . '<description>' . $channel_name . '</description>'
            . $items
            . '</channel></rss>';
    }
}
