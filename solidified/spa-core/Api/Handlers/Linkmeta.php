<?php
namespace Utsukta\SpaCore\Api\Handlers;

use Utsukta\SpaCore\Api\Auth;
use Utsukta\SpaCore\Api\Response;
use Utsukta\SpaCore\Api\Concerns\ValidatesRemoteHost;
use Zotlabs\Lib\Cache;
use Zotlabs\Lib\System;
use Zotlabs\Module\Linkinfo;

/**
 * GET /spa/link-meta?url=<page url>
 *
 * Structured page metadata for the card composer's Link template, so pasting
 * a URL can fill in the title/summary/slug instead of the user retyping them.
 *
 * ── Why this does not call Linkinfo::parseurl_getsiteinfo() ──────────────────
 * That scraper would have saved the parsing below, but it fetches the URL
 * itself via z_fetch_url(), and that path is not SSRF-safe for a
 * client-supplied URL:
 *
 *   - z_fetch_url follows redirects by recursing on the Location header
 *     (include/network.php) without revalidating the new URL, so vetting only
 *     the submitted URL is bypassed by a 302 to 127.0.0.1 or 169.254.169.254.
 *   - parseurl_getsiteinfo passes novalidate => true, which turns
 *     CURLOPT_SSL_VERIFYPEER off.
 *   - Its image pass calls getimagesize() on every <img src> in the response,
 *     i.e. a second set of fetches driven by the fetched page's content.
 *
 * So the fetch is done here instead, following Rss.php's vetted pattern:
 * resolve the host to a public IP (ValidatesRemoteHost), pin the connection to
 * that IP with CURLOPT_RESOLVE so DNS can't be rebound between the check and
 * the request, refuse to follow redirects automatically, and revalidate every
 * hop we do follow. Only the small meta-tag extraction is reimplemented; it
 * needs no network and is the cheap half.
 *
 * Local-channel only. Result cached 1 hour, keyed by the URL.
 */
class Linkmeta
{
    use ValidatesRemoteHost;

    private const CACHE_AGE = '60 MINUTE';
    /** Hops we follow ourselves, revalidating the host at each one. */
    private const MAX_REDIRECTS = 3;
    /** Metadata lives in <head>; 1MB is generous and caps memory per request. */
    private const MAX_BYTES = 1048576;
    private const TIMEOUT = 10;

    public function get(): void
    {
        Auth::requireLocalGet();

        $url = trim($_GET['url'] ?? '');
        if (!self::isPublicHttpUrl($url)) {
            Response::error(400, 'Invalid URL');
        }

        $cache_key = 'spa_linkmeta:' . hash('sha256', $url);
        $cached = Cache::get($cache_key, self::CACHE_AGE);
        if ($cached) {
            $data = json_decode($cached, true);
            if (is_array($data)) {
                Response::send($data);
            }
        }

        $fetched = self::safeFetch($url);
        if (!$fetched) {
            Response::error(502, 'Could not read that page');
        }

        $data = self::extractMeta($fetched['body'], $fetched['url']);
        if (!$data['title'] && !$data['text']) {
            Response::error(502, 'Could not read that page');
        }

        Cache::set($cache_key, json_encode($data));
        Response::send($data);
    }

    /**
     * Scheme allowlist + host vetting. FILTER_VALIDATE_URL alone happily
     * passes file:// and ftp://, and a valid URL may still point at loopback,
     * link-local (cloud metadata), or RFC1918 space.
     */
    private static function isPublicHttpUrl(string $url): bool
    {
        if (!$url || strlen($url) > 2048) {
            return false;
        }
        if (!preg_match('#^https?://#i', $url) || !filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }
        $host = parse_url($url, PHP_URL_HOST);
        if (!$host || strcasecmp($host, 'localhost') === 0) {
            return false;
        }
        // Fails closed: an unresolvable host returns false, not "allowed".
        return self::resolveSafePublicIp($host) !== false;
    }

    /**
     * Fetch $url, following at most MAX_REDIRECTS hops and revalidating the
     * host at every one. Returns ['body' => html, 'url' => final url] or null.
     */
    private static function safeFetch(string $url): ?array
    {
        for ($hop = 0; $hop <= self::MAX_REDIRECTS; $hop++) {
            $parts = parse_url($url);
            $host = $parts['host'] ?? '';
            $scheme = strtolower($parts['scheme'] ?? '');
            $port = $parts['port'] ?? ($scheme === 'https' ? 443 : 80);

            $ip = self::resolveSafePublicIp($host);
            if (!$ip) {
                return null;
            }

            $body = '';
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                // Pin the vetted IP: without this the resolver runs again
                // inside curl and a short-TTL record can answer 127.0.0.1
                // the second time (DNS rebinding).
                CURLOPT_RESOLVE        => ["$host:$port:$ip"],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HEADER         => false,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_PROTOCOLS      => CURLPROTO_HTTP | CURLPROTO_HTTPS,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_TIMEOUT        => self::TIMEOUT,
                CURLOPT_CONNECTTIMEOUT => self::TIMEOUT,
                CURLOPT_USERAGENT      => System::get_useragent(),
                // Returning less than the chunk length aborts the transfer,
                // so an endless response can't exhaust memory.
                CURLOPT_WRITEFUNCTION  => function ($ch, string $chunk) use (&$body) {
                    $body .= $chunk;
                    return strlen($body) > self::MAX_BYTES ? 0 : strlen($chunk);
                },
            ]);
            curl_exec($ch);
            $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            $type = (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
            $next = (string) curl_getinfo($ch, CURLINFO_REDIRECT_URL);
            curl_close($ch);

            if ($code >= 300 && $code < 400 && $next) {
                // Loop round to revalidate the new host rather than letting
                // curl follow it unchecked.
                if (!self::isPublicHttpUrl($next)) {
                    return null;
                }
                $url = $next;
                continue;
            }

            if ($code < 200 || $code > 299 || $body === '') {
                return null;
            }
            if ($type && stripos($type, 'html') === false) {
                return null;   // only HTML carries the meta tags we want
            }
            return ['body' => $body, 'url' => $url];
        }

        return null;   // too many hops
    }

    /** og:/twitter:/dc: meta, falling back to <title> and meta description. */
    private static function extractMeta(string $html, string $baseUrl): array
    {
        // Honour a declared charset so a non-UTF-8 page doesn't yield mojibake.
        $charset = 'UTF-8';
        if (preg_match('/charset=["\']?([\w-]+)/i', $html, $m)) {
            $charset = $m[1];
        }
        if (strcasecmp($charset, 'UTF-8') !== 0) {
            $converted = @mb_convert_encoding($html, 'UTF-8', $charset);
            if ($converted !== false) {
                $html = $converted;
            }
        }

        $doc = new \DOMDocument();
        $prev = libxml_use_internal_errors(true);
        @$doc->loadHTML('<?xml encoding="UTF-8">' . $html);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);
        $xpath = new \DOMXPath($doc);

        $meta = function (string $attr, array $names) use ($xpath): string {
            foreach ($names as $name) {
                $q = sprintf('//meta[translate(@%s,"ABCDEFGHIJKLMNOPQRSTUVWXYZ",'
                    . '"abcdefghijklmnopqrstuvwxyz")="%s"]/@content', $attr, $name);
                $hit = $xpath->query($q);
                if ($hit && $hit->length && trim($hit->item(0)->nodeValue) !== '') {
                    return trim(html_entity_decode(
                        $hit->item(0)->nodeValue, ENT_QUOTES, 'UTF-8'
                    ));
                }
            }
            return '';
        };

        $title = $meta('property', ['og:title'])
            ?: $meta('name', ['twitter:title', 'dc.title', 'fulltitle']);
        if (!$title) {
            $node = $xpath->query('//title');
            if ($node && $node->length) {
                $title = trim(html_entity_decode($node->item(0)->nodeValue, ENT_QUOTES, 'UTF-8'));
            }
        }

        $text = $meta('property', ['og:description'])
            ?: $meta('name', ['twitter:description', 'dc.description', 'description']);
        // Plenty of pages (Wikipedia among them) declare no description at
        // all. Core's scraper fell back to body prose and the composer's
        // Summary field is the poorer without it, so keep that behaviour —
        // it costs no extra request.
        if ($text === '') {
            foreach (["//div[@class='article']", "//div[@class='content']", '//p'] as $q) {
                $nodes = $xpath->query($q);
                if (!$nodes) {
                    continue;
                }
                foreach ($nodes as $node) {
                    if (mb_strlen(trim($node->nodeValue)) > 40) {
                        $text .= ' ' . trim($node->nodeValue);
                    }
                }
                if (trim($text) !== '') {
                    break;
                }
            }
            $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
        }

        $image = $meta('property', ['og:image'])
            ?: $meta('name', ['twitter:image', 'twitter:image:src', 'thumbnail']);
        // Relative og:image is legal; completeurl() is core's own resolver and
        // touches no network.
        if ($image !== '') {
            $image = Linkinfo::completeurl($image, $baseUrl);
            // The composer renders this straight into an <img>, and it lands
            // in a [img] tag on save — keep it to real http(s) so a
            // javascript:/data: value can't ride along.
            if (!preg_match('#^https?://#i', $image)) {
                $image = '';
            }
        }

        return [
            // Collapse newlines: the title becomes a single-line [url] label.
            'title' => trim(preg_replace('/\s+/u', ' ', $title) ?? ''),
            'text'  => self::excerpt(trim(preg_replace('/\s+/u', ' ', $text) ?? '')),
            'image' => $image,
        ];
    }

    /** Trim to 350 chars on a word boundary rather than mid-word. */
    private static function excerpt(string $text, int $limit = 350): string
    {
        if (mb_strlen($text) <= $limit) {
            return $text;
        }
        $cut = mb_substr($text, 0, $limit);
        $sp = mb_strrpos($cut, ' ');
        if ($sp !== false && $sp > 0) {
            $cut = mb_substr($cut, 0, $sp);
        }
        return rtrim($cut, " ?.,:;!-") . '…';
    }
}
