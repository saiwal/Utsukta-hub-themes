<?php
namespace Utsukta\SpaCore\Api\Handlers;

use Utsukta\SpaCore\Api\Auth;
use Utsukta\SpaCore\Api\Response;
use Zotlabs\Lib\Cache;
use Zotlabs\Module\Linkinfo;

/**
 * GET /spa/link-meta?url=<page url>
 *
 * Structured page metadata for the card composer's Link template, so pasting
 * a URL can fill in the title/summary/slug instead of the user retyping them.
 *
 * The scraping itself is core's — Linkinfo::parseurl_getsiteinfo() is a public
 * static that already reads og:/twitter:/dc: meta, falls back to <title> and
 * article body text, and sizes candidate images. Core's own /linkinfo module
 * wraps the same call but echoes bbcode; we want the fields, so we call the
 * scraper directly rather than parsing bbcode back apart.
 *
 * Local-channel only, http(s) only. That is the same exposure core's
 * /linkinfo already gives the same users — this adds no new fetch surface.
 *
 * Result cached 1 hour, keyed by the URL.
 */
class Linkmeta
{
    private const CACHE_AGE = '60 MINUTE';

    public function get(): void
    {
        Auth::requireLocalGet();

        $url = trim($_GET['url'] ?? '');

        // Scheme allowlist before anything fetches: FILTER_VALIDATE_URL alone
        // happily passes file:// and ftp://.
        if (!$url || strlen($url) > 2048
            || !preg_match('#^https?://#i', $url)
            || !filter_var($url, FILTER_VALIDATE_URL)) {
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

        $info = Linkinfo::parseurl_getsiteinfo($url);

        // parseurl_getsiteinfo returns [] on a failed fetch, and otherwise a
        // sparse array — every key below is optional. (No 'keywords': core
        // parses the meta tag into a local it then never returns.)
        $data = [
            'title' => trim((string) ($info['title'] ?? '')),
            'text'  => trim((string) ($info['text'] ?? '')),
            'image' => (string) ($info['images'][0]['src'] ?? ''),
        ];

        if (!$data['title'] && !$data['text']) {
            Response::error(502, 'Could not read that page');
        }

        Cache::set($cache_key, json_encode($data));
        Response::send($data);
    }
}
