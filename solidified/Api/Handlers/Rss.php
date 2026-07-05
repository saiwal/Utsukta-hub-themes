<?php
namespace Theme\Solidified\Api\Handlers;

use Theme\Solidified\Api\Response;
use Zotlabs\Lib\Cache;

/**
 * GET /api/rss-feed?url=<feed url>&limit=<n>
 *
 * Server-side fetch + parse of an RSS/Atom feed for the SPA's RSS widget —
 * browsers can't fetch cross-origin feeds. Public: the widget renders for
 * visitors via the page owner's layout.
 *
 * Guards: http(s) only, hostname must not resolve to a private/loopback
 * address, response capped at MAX_BYTES, parsed result cached 15 minutes
 * (keyed by URL) so repeated visitors don't hammer the remote feed.
 */
class Rss
{
    private const MAX_ITEMS = 10;
    private const MAX_BYTES = 1048576; // 1 MB
    private const CACHE_AGE = '15 MINUTE';

    public function get(): void
    {
        $url = trim($_GET['url'] ?? '');
        $limit = max(1, min(self::MAX_ITEMS, intval($_GET['limit'] ?? 5)));

        if (!$url || strlen($url) > 1024 || !self::url_allowed($url)) {
            Response::error(400, 'Invalid feed URL');
        }

        $cache_key = 'spa_rss:' . $url;
        $cached = Cache::get($cache_key, self::CACHE_AGE);
        if ($cached) {
            $feed = json_decode($cached, true);
            if (is_array($feed)) {
                Response::send(self::with_limit($feed, $limit));
            }
        }

        $res = z_fetch_url($url, false, 0, ['timeout' => 10]);
        if (!$res['success'] || !$res['body']) {
            Response::error(502, 'Feed fetch failed');
        }
        if (strlen($res['body']) > self::MAX_BYTES) {
            Response::error(502, 'Feed too large');
        }

        $feed = self::parse($res['body']);
        if ($feed === null) {
            Response::error(422, 'Not a recognisable RSS/Atom feed');
        }

        Cache::set($cache_key, json_encode($feed));
        Response::send(self::with_limit($feed, $limit));
    }

    private static function with_limit(array $feed, int $limit): array
    {
        $feed['items'] = array_slice($feed['items'] ?? [], 0, $limit);
        return $feed;
    }

    /**
     * http(s) only, and the host must not be (or resolve to) a private,
     * reserved, or loopback address. Redirect targets are fetched by
     * z_fetch_url without re-checking — acceptable for owner-configured URLs.
     */
    private static function url_allowed(string $url): bool
    {
        $parts = parse_url($url);
        if (!$parts || !in_array($parts['scheme'] ?? '', ['http', 'https'], true)) {
            return false;
        }
        $host = $parts['host'] ?? '';
        if (!$host || strcasecmp($host, 'localhost') === 0) {
            return false;
        }

        $ip = filter_var($host, FILTER_VALIDATE_IP) ? $host : gethostbyname($host);
        if (filter_var($ip, FILTER_VALIDATE_IP)
            && !filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return false;
        }

        return true;
    }

    /**
     * Parses RSS 2.0, RSS 1.0 (RDF) and Atom into
     * { title, link, items: [{ title, link, published }] } (max MAX_ITEMS).
     */
    private static function parse(string $body): ?array
    {
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($body);
        if ($xml === false) {
            return null;
        }

        $root = $xml->getName();

        if ($root === 'feed') {
            return self::parse_atom($xml);
        }
        if ($root === 'rss' && isset($xml->channel)) {
            return self::parse_rss($xml->channel, $xml->channel->item);
        }
        if ($root === 'RDF' && isset($xml->channel)) {
            return self::parse_rss($xml->channel, $xml->item);
        }

        return null;
    }

    private static function parse_rss($channel, $items): array
    {
        $dc = 'http://purl.org/dc/elements/1.1/';
        $out = [];
        foreach ($items as $item) {
            if (count($out) >= self::MAX_ITEMS) break;
            $date = (string)$item->pubDate;
            if (!$date) {
                $date = (string)($item->children($dc)->date ?? '');
            }
            $out[] = [
                'title' => trim((string)$item->title),
                'link' => trim((string)$item->link),
                'published' => self::to_iso($date),
            ];
        }
        return [
            'title' => trim((string)$channel->title),
            'link' => trim((string)$channel->link) ?: null,
            'items' => $out,
        ];
    }

    private static function parse_atom($feed): array
    {
        $out = [];
        foreach ($feed->entry as $entry) {
            if (count($out) >= self::MAX_ITEMS) break;
            $out[] = [
                'title' => trim((string)$entry->title),
                'link' => self::atom_link($entry),
                'published' => self::to_iso((string)($entry->published ?: $entry->updated)),
            ];
        }
        return [
            'title' => trim((string)$feed->title),
            'link' => self::atom_link($feed),
            'items' => $out,
        ];
    }

    /** Prefers rel="alternate" (or unqualified) links, as browsers do. */
    private static function atom_link($node): ?string
    {
        $fallback = null;
        foreach ($node->link as $link) {
            $href = trim((string)$link['href']);
            if (!$href) continue;
            $rel = (string)$link['rel'];
            if ($rel === '' || $rel === 'alternate') {
                return $href;
            }
            $fallback = $fallback ?? $href;
        }
        return $fallback;
    }

    private static function to_iso(string $date): ?string
    {
        $date = trim($date);
        if (!$date) return null;
        $ts = strtotime($date);
        return $ts === false ? null : gmdate('c', $ts);
    }
}
