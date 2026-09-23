<?php
namespace Utsukta\SpaCore\Api\Handlers;

use Utsukta\SpaCore\Api\Response;
use Zotlabs\Lib\Cache;
use Zotlabs\Module\Linkinfo;

/**
 * GET /spa/oembed?url=<page url>
 *
 * Resolves an [embed]<url>[/embed] tag to the provider's player iframe src,
 * for any oEmbed site (PeerTube, Dailymotion, SoundCloud, …) — YouTube and
 * Vimeo never get here, oembedResolver.ts plays those through Plyr.
 *
 * ── Policy is core's, the fetch is ours ──────────────────────────────────────
 * oembed_action() decides, so the admin's embed_allow/embed_deny lists and a
 * member's own embed_deny apply exactly as in the classic UI. Only 'allow'
 * embeds: core's 'filter' verdict purifies the provider HTML, which strips the
 * iframe, so there a non-allowlisted site renders as a link — mirrored here by
 * answering src:null. core's oembed_fetch_url() is not reused for the fetch:
 * it goes through z_fetch_url, whose redirect handling isn't SSRF-safe for a
 * client-supplied URL (see Linkmeta.php), so both hops use Linkmeta::safeFetch.
 *
 * ── Only a src leaves this endpoint ──────────────────────────────────────────
 * The provider's HTML is never passed on. The client builds a sandboxed
 * <iframe> itself from an https src (useEmbeds.ts), so a hostile oEmbed
 * response can at worst frame its own page.
 *
 * Open to visitors (they read public posts too); the allowlist gate plus the
 * 24h cache (negative results included) bound what an anonymous caller can
 * make the hub fetch.
 */
class Oembed
{
    private const CACHE_AGE = '24 HOUR';

    public function get(): void
    {
        require_once 'include/oembed.php';

        $url = trim($_GET['url'] ?? '');
        if (!Linkmeta::isPublicHttpUrl($url)) {
            Response::error(400, 'Invalid URL');
        }

        // Checked before the cache: an admin tightening the list takes effect
        // at once rather than a day later.
        if ((oembed_action($url)['action'] ?? '') !== 'allow') {
            Response::send(['src' => null]);
        }

        $cache_key = 'spa_oembed:' . hash('sha256', $url);
        $cached = Cache::get($cache_key, self::CACHE_AGE);
        if ($cached) {
            $data = json_decode($cached, true);
            if (is_array($data)) {
                Response::send($data);
            }
        }

        $data = self::resolve($url);
        Cache::set($cache_key, json_encode($data));
        Response::send($data);
    }

    /** Page → discovered oEmbed JSON → player src. Any failure is src:null. */
    private static function resolve(string $url): array
    {
        $none = ['src' => null];

        $page = Linkmeta::safeFetch($url);
        if (!$page) {
            return $none;
        }
        $href = self::discoverHref($page['body'], $page['url']);
        if (!$href || !Linkmeta::isPublicHttpUrl($href)) {
            return $none;
        }
        $json = Linkmeta::safeFetch($href, 'json');
        $j = $json ? json_decode($json['body'], true) : null;
        if (!is_array($j) || !is_string($j['html'] ?? null)) {
            return $none;
        }
        $src = self::iframeSrc($j['html']);
        if (!$src) {
            return $none;
        }

        $w = (float) ($j['width'] ?? 0);
        $h = (float) ($j['height'] ?? 0);
        return [
            'src'   => $src,
            // Width/height ratio for the frame's aspect-ratio; null = client default.
            'ratio' => ($w > 0 && $h > 0) ? round($w / $h, 4) : null,
            'title' => is_string($j['title'] ?? null) ? $j['title'] : '',
        ];
    }

    /**
     * The page's oEmbed discovery link, absolute, or ''. SoundCloud and a few
     * others advertise text/json+oembed instead of application/json+oembed —
     * core's discovery accepts both, so this does too.
     */
    public static function discoverHref(string $html, string $baseUrl): string
    {
        $doc = new \DOMDocument();
        $prev = libxml_use_internal_errors(true);
        @$doc->loadHTML('<?xml encoding="UTF-8">' . $html);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);
        $hit = (new \DOMXPath($doc))->query(
            "//link[@type='application/json+oembed' or @type='text/json+oembed']/@href"
        );
        if (!$hit || !$hit->length) {
            return '';
        }
        $href = html_entity_decode(trim($hit->item(0)->nodeValue), ENT_QUOTES, 'UTF-8');
        return $href === '' ? '' : Linkinfo::completeurl($href, $baseUrl);
    }

    /** First <iframe src> in oEmbed html, https only, or null. */
    public static function iframeSrc(string $html): ?string
    {
        $doc = new \DOMDocument();
        $prev = libxml_use_internal_errors(true);
        @$doc->loadHTML('<?xml encoding="UTF-8">' . $html);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);
        $hit = (new \DOMXPath($doc))->query('//iframe/@src');
        if (!$hit || !$hit->length) {
            return null;
        }
        $src = trim($hit->item(0)->nodeValue);
        // Protocol-relative srcs are common in oEmbed html.
        if (str_starts_with($src, '//')) {
            $src = 'https:' . $src;
        }
        return preg_match('#^https://#i', $src) && filter_var($src, FILTER_VALIDATE_URL)
            ? $src : null;
    }
}
