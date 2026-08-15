<?php
namespace Utsukta\SpaCore\Api\Handlers;

use Utsukta\SpaCore\Api\Response;
use Utsukta\SpaCore\Api\Concerns\ValidatesRemoteHost;
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
    use ValidatesRemoteHost;

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

        $res = self::fetch_validated($url);
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

    /** http(s) only. Actual address safety is (re-)checked per-hop in fetch_validated(). */
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
        return true;
    }

    /**
     * Fetches $url with the DNS-rebind/TOCTOU gap closed: resolves the
     * host once, validates that specific IP, then pins the curl connection
     * to it via CURLOPT_RESOLVE (so curl's own connect-time lookup can't
     * diverge from the check). Redirects are followed manually (up to 5
     * hops), re-validating the target host/IP on every hop — z_fetch_url()
     * can't be used here because it follows redirects internally with no
     * re-validation hook and offers no IP-pinning option.
     */
    private static function fetch_validated(string $url): array
    {
        for ($hop = 0; $hop <= 5; $hop++) {
            $parts = parse_url($url);
            if (!$parts || !in_array($parts['scheme'] ?? '', ['http', 'https'], true)) {
                return ['success' => false, 'body' => ''];
            }
            $host = $parts['host'] ?? '';
            if (!$host || strcasecmp($host, 'localhost') === 0) {
                return ['success' => false, 'body' => ''];
            }
            $ip = self::resolveSafePublicIp($host);
            if (!$ip) {
                return ['success' => false, 'body' => ''];
            }
            $port = $parts['port'] ?? ($parts['scheme'] === 'https' ? 443 : 80);

            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RESOLVE => ["$host:$port:$ip"],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HEADER => true,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_TIMEOUT => 10,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
                CURLOPT_USERAGENT => \Zotlabs\Lib\System::get_useragent(),
            ]);
            $raw = curl_exec($ch);
            $info = curl_getinfo($ch);
            curl_close($ch);

            if ($raw === false) {
                return ['success' => false, 'body' => ''];
            }

            $header_size = $info['header_size'] ?? 0;
            $body = substr($raw, $header_size);
            $code = intval($info['http_code'] ?? 0);

            if (in_array($code, [301, 302, 303, 307, 308], true)) {
                $header = substr($raw, 0, $header_size);
                if (!preg_match('/^Location:\s*(.+?)\r?$/mi', $header, $m)) {
                    return ['success' => false, 'body' => ''];
                }
                $location = trim($m[1]);
                $url = self::resolve_redirect($url, $location);
                if (!$url) {
                    return ['success' => false, 'body' => ''];
                }
                continue;
            }

            return ['success' => $code >= 200 && $code < 300, 'body' => (string)$body];
        }

        return ['success' => false, 'body' => ''];
    }

    /** Resolves a possibly-relative Location header against the URL that produced it. */
    private static function resolve_redirect(string $base, string $location): ?string
    {
        if (parse_url($location, PHP_URL_SCHEME)) {
            return $location; // already absolute
        }
        $base_parts = parse_url($base);
        if (!$base_parts || empty($base_parts['scheme']) || empty($base_parts['host'])) {
            return null;
        }
        $origin = $base_parts['scheme'] . '://' . $base_parts['host']
            . (isset($base_parts['port']) ? ':' . $base_parts['port'] : '');

        if (str_starts_with($location, '//')) {
            return $base_parts['scheme'] . ':' . $location;
        }
        if (str_starts_with($location, '/')) {
            return $origin . $location;
        }
        $base_path = $base_parts['path'] ?? '/';
        $dir = substr($base_path, 0, strrpos($base_path, '/') + 1) ?: '/';
        return $origin . $dir . $location;
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
