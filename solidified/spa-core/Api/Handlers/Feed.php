<?php
namespace Utsukta\SpaCore\Api\Handlers;

use Utsukta\SpaCore\Api\Response;

/**
 * GET /spa/feed/:nick?type=posts|articles|webpages&tag=<tag>&cat=<slug>
 *
 * RSS 2.0 feed generator behind every row of the SPA's feed modal
 * (src/shared/views/FeedModal.tsx). It replaces core's /feed module for the
 * SPA's own links: core emits Atom whose atom:author carries <id>/<link>
 * children an Atom person construct doesn't allow, so those feeds fail
 * validation. Core /feed/:nick is untouched and still serves federation and
 * feed discovery (Profile.php's feed_url).
 *
 * tag and cat are mutually exclusive — cat wins if both are given, matching
 * how the SPA's own category/tag stream widgets already clear one when the
 * other is picked.
 *
 * Public: gated the same way core's /feed is, via view_stream (posts,
 * articles) or view_pages (webpages) for the requesting (possibly anonymous)
 * observer.
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

        $type = $_GET['type'] ?? '';
        if (!in_array($type, ['articles', 'webpages'], true)) {
            $type = 'posts';
        }

        $observer_hash = get_observer_hash();
        $perms = get_all_perms($channel['channel_id'], $observer_hash);
        // Core splits the same way in get_feed_for(): pages need view_pages.
        if (!$perms[$type === 'webpages' ? 'view_pages' : 'view_stream']) {
            Response::error(403, 'Permission denied');
        }

        $uid = intval($channel['channel_id']);
        $item_type_val = match ($type) {
            'articles' => ITEM_TYPE_ARTICLE,
            'webpages' => ITEM_TYPE_WEBPAGE,
            default    => ITEM_TYPE_POST,
        };
        $cat = trim((string)($_GET['cat'] ?? ''));
        $tag = trim((string)($_GET['tag'] ?? ''));

        $term_join = '';
        if ($cat !== '') {
            $term_join = "AND item.id IN (SELECT oid FROM term WHERE uid = $uid AND ttype = " . TERM_CATEGORY . " AND term = '" . dbesc($cat) . "')";
        } elseif ($tag !== '') {
            $term_join = "AND item.id IN (SELECT oid FROM term WHERE uid = $uid AND ttype = " . TERM_HASHTAG . " AND term = '" . dbesc($tag) . "')";
        }

        // Webpages are addressed by their pagelink (/page/<nick>/<link>), not by
        // plink — same iconfig row Webpages.php reads for view_url, joined INNER
        // so only real pages come back. They also carry item_wall = 0 and are
        // created with verb Add, so neither of the stream filters applies to
        // them (core's own /feed?pages=1 keeps the wall filter and consequently
        // returns an empty feed).
        $page_select = '';
        $page_join = '';
        $wall_sql = 'AND item.item_wall = 1';
        $verb_sql = "AND item.verb NOT IN ('Add', 'Remove')";
        if ($type === 'webpages') {
            $page_select = ', iconfig.v AS pagelink';
            $page_join = "INNER JOIN iconfig ON iconfig.iid = item.id AND iconfig.cat = 'system' AND iconfig.k = 'WEBPAGE'";
            $wall_sql = '';
            $verb_sql = '';
        }

        // item_private = 0 on top of the observer's permission SQL: a feed URL
        // outlives the session that fetched it (readers store and re-poll it),
        // so it only ever carries what is public. Core's /feed drops private
        // items the same way (include/feedutils.php).
        //
        // Pass $uid so item_normal() includes the owner's own delayed/moderated
        // posts when they fetch their own feed (see Channel.php).
        $item_normal = item_normal($uid, 'item', $item_type_val);
        $perm_sql = item_permissions_sql($uid, $observer_hash);

        $rows = dbq(
            "SELECT item.uuid, item.mid, item.title, item.body, item.mimetype, item.created, item.plink, item.author_xchan $page_select
             FROM item
             $page_join
             WHERE item.uid             = $uid
               AND item.item_thread_top = 1
               AND item.item_private    = 0
               $wall_sql
               $verb_sql
               $term_join
               $perm_sql $item_normal
             ORDER BY item.created DESC
             LIMIT " . self::MAX_ITEMS
        );

        header('Content-Type: application/rss+xml; charset=utf-8');
        echo $this->render($channel, $rows ?: [], $type);
        killme();
    }

    private function render(array $channel, array $rows, string $type): string
    {
        $channel_name = self::xml($channel['channel_name'] ?? $channel['xchan_name'] ?? '');
        $channel_link = self::xml($channel['xchan_url'] ?? z_root());
        // Rebuilt from the params we honour — $_SERVER['QUERY_STRING'] also
        // carries Hubzilla's internal q= rewrite argument.
        $self_params = array_filter([
            'type' => $type !== 'posts' ? $type : '',
            'cat'  => trim((string)($_GET['cat'] ?? '')),
            'tag'  => trim((string)($_GET['tag'] ?? '')),
        ], fn($v) => $v !== '');
        $self_link = self::xml(z_root() . '/spa/feed/' . $channel['channel_address']
            . ($self_params ? '?' . http_build_query($self_params) : ''));

        $items = '';
        foreach ($rows as $row) {
            $link = $this->itemLink($row, $channel, $type);
            $body = $this->renderBody($row);
            $title = $row['title'] !== ''
                ? $row['title']
                : mb_strimwidth(
                    trim(preg_replace('/\s+/u', ' ',
                        html_entity_decode(strip_tags($body), ENT_QUOTES | ENT_HTML5, 'UTF-8'))),
                    0, 80, '…');

            // RSS 2.0: an item must carry a title or a description. Bodyless
            // activity rows (RSVPs, likes on own wall) can satisfy neither.
            if (trim($title) === '' && trim(strip_tags($body)) === '') {
                continue;
            }

            $pubDate = (new \DateTime($row['created'], new \DateTimeZone('UTC')))->format(\DATE_RSS);
            $permalink = (bool)preg_match('#^https?://#i', $link);

            $items .= '<item>'
                . '<title>' . self::xml($title) . '</title>'
                . '<link>' . self::xml($link) . '</link>'
                . '<guid isPermaLink="' . ($permalink ? 'true' : 'false') . '">' . self::xml($link) . '</guid>'
                . '<pubDate>' . $pubDate . '</pubDate>'
                . '<description><![CDATA[' . str_replace(']]>', ']]&gt;', self::strip_ctrl($body)) . ']]></description>'
                . '</item>';
        }

        return '<?xml version="1.0" encoding="UTF-8"?>'
            . '<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom"><channel>'
            . '<title>' . $channel_name . '</title>'
            . '<link>' . $channel_link . '</link>'
            . '<description>' . $channel_name . '</description>'
            . '<atom:link rel="self" type="application/rss+xml" href="' . $self_link . '" />'
            . $items
            . '</channel></rss>';
    }

    /**
     * Webpages and articles aren't necessarily bbcode — render each body by its
     * own mimetype (see prepare_text()), except the two executable/layout types,
     * which have no business being evaluated or dumped into a public feed.
     */
    private function renderBody(array $row): string
    {
        if ($row['body'] === '') {
            return '';
        }
        $mimetype = $row['mimetype'] ?: 'text/bbcode';
        if (in_array($mimetype, ['application/x-php', 'application/x-pdl'], true)) {
            return '';
        }
        return prepare_text($row['body'], $mimetype, ['tryoembed' => false]);
    }

    private function itemLink(array $row, array $channel, string $type): string
    {
        if ($type === 'webpages' && !empty($row['pagelink'])) {
            $pagelink = urldecode(str_replace('%2f', '/', $row['pagelink']));
            return z_root() . '/page/' . $channel['channel_address'] . '/' . $pagelink;
        }
        return $row['plink'] ?: $row['mid'];
    }

    /** Escapes for XML text, minus the control characters XML 1.0 forbids outright. */
    private static function xml(string $s): string
    {
        return htmlspecialchars(self::strip_ctrl($s), ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    private static function strip_ctrl(string $s): string
    {
        return preg_replace('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u', '', $s) ?? $s;
    }
}
