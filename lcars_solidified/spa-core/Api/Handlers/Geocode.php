<?php
namespace Utsukta\SpaCore\Api\Handlers;

use Utsukta\SpaCore\Api\Response;
use Zotlabs\Lib\Cache;

/**
 * GET /spa/geocode?place=<place name>
 * GET /spa/geocode?lat=<lat>&lon=<lon>   (reverse: → { name }, e.g. "Plaka, Athens")
 *
 * Forward geocode for the SPA's map feature ([map]Place[/map] bodies and the
 * composer's map picker). Proxied server-side through the openstreetmap
 * addon's configured Nominatim server so the app needs no third-party
 * connect-src CSP exception, and so Nominatim sees one identified server
 * instead of every viewer's browser (their usage policy asks for exactly
 * that, plus aggressive caching — hence the 30-day Cache below; a place
 * name's coordinates don't move). Misses are cached too, and uncached
 * lookups are spaced to the policy's 1 request/second: the hub and directory
 * maps geocode dozens of free-text locations ("Deutschland", "Berlin,
 * Germany") one after another.
 *
 * NOTE: the param is named "place", not "q" — Hubzilla's own front-controller
 * rewrite reads $_GET['q'] for clean-URL routing, and a second `q` here would
 * clobber it. See Weather.php for the full explanation.
 *
 * 404s when the openstreetmap addon isn't enabled: without it the addon's
 * content_security_policy hook never whitelists the tile server, so an
 * embedded map couldn't render anyway.
 */
class Geocode
{
    private const CACHE_AGE = '30 DAY';

    public function get(): void
    {
        if (!plugin_is_installed('openstreetmap')) {
            Response::error(404, 'Map support not enabled');
        }

        if (isset($_GET['lat'], $_GET['lon'])) {
            $this->reverse();
        }

        $place = trim($_GET['place'] ?? '');
        if (!$place || mb_strlen($place) > 200) {
            Response::error(400, 'Invalid location');
        }

        $cache_key = 'spa_geocode:' . mb_strtolower($place);
        $cached = Cache::get($cache_key, self::CACHE_AGE);
        if ($cached) {
            $data = json_decode($cached, true);
            if ($data === []) {
                Response::error(404, 'Location not found');
            }
            if (is_array($data)) {
                Response::send($data);
            }
        }

        $nomserver = get_config('openstreetmap', 'nomserver', 'https://nominatim.openstreetmap.org/search')
            ?: 'https://nominatim.openstreetmap.org/search';

        $url = $nomserver . '?' . http_build_query([
            'q' => $place,
            'format' => 'json',
            'limit' => 1,
        ]);

        $this->throttle();
        $res = z_fetch_url($url, false, 0, ['timeout' => 10]);
        if (!$res['success'] || !$res['body']) {
            Response::error(502, 'Geocoding lookup failed');
        }

        $hit = (json_decode($res['body'], true) ?: [])[0] ?? null;
        if (!$hit || !isset($hit['lat'], $hit['lon'])) {
            Cache::set($cache_key, '[]');
            Response::error(404, 'Location not found');
        }

        $data = [
            'lat' => (float) $hit['lat'],
            'lon' => (float) $hit['lon'],
            'display_name' => (string) ($hit['display_name'] ?? $place),
        ];

        Cache::set($cache_key, json_encode($data));
        Response::send($data);
    }

    /**
     * Coordinates → a short place name for the photo view's location chip.
     * Rounded to 4 decimals (~11 m) for the cache key, so neighbouring photos
     * share a lookup; keyed on the viewer's language, since Nominatim names
     * places in the Accept-Language it's given. zoom=14 asks for
     * neighbourhood/town level, not a street address.
     */
    private function reverse(): never
    {
        $lat = (float) $_GET['lat'];
        $lon = (float) $_GET['lon'];
        if (!is_numeric($_GET['lat']) || !is_numeric($_GET['lon']) || abs($lat) > 90 || abs($lon) > 180) {
            Response::error(400, 'Invalid coordinates');
        }
        $lang = preg_replace('/[^a-z-]/i', '', (string) (\App::$language ?: 'en'));
        $key  = sprintf('spa_rgeocode:%.4f,%.4f:%s', $lat, $lon, $lang);

        $cached = Cache::get($key, self::CACHE_AGE);
        if ($cached !== null) {
            $cached === '' ? Response::error(404, 'Location not found') : Response::send(['name' => $cached]);
        }

        $nomserver = get_config('openstreetmap', 'nomserver', 'https://nominatim.openstreetmap.org/search')
            ?: 'https://nominatim.openstreetmap.org/search';
        // The addon stores the /search endpoint; /reverse sits beside it.
        $url = preg_replace('#/search/?$#', '', $nomserver) . '/reverse?' . http_build_query([
            'lat' => $lat, 'lon' => $lon, 'format' => 'json', 'zoom' => 14, 'accept-language' => $lang,
        ]);

        $this->throttle();
        $res = z_fetch_url($url, false, 0, ['timeout' => 10]);
        if (!$res['success'] || !$res['body']) {
            Response::error(502, 'Geocoding lookup failed');
        }

        $a = (json_decode($res['body'], true) ?: [])['address'] ?? [];
        // Most specific named area + its town, e.g. "Plaka, Athens".
        $area = $a['neighbourhood'] ?? $a['suburb'] ?? $a['quarter'] ?? $a['village'] ?? $a['hamlet'] ?? '';
        $town = $a['city'] ?? $a['town'] ?? $a['municipality'] ?? $a['county'] ?? $a['state'] ?? '';
        $name = implode(', ', array_unique(array_filter([$area, $town]))) ?: ($a['country'] ?? '');

        Cache::set($key, $name);
        $name === '' ? Response::error(404, 'Location not found') : Response::send(['name' => $name]);
    }

    // Nominatim allows 1 request/second per server.
    // ponytail: per-process check-then-sleep, two concurrent misses can still
    // both go out in the same second; a mutex if Nominatim ever complains
    private function throttle(): void
    {
        $last = floatval(Cache::get('spa_geocode_last', '1 MINUTE'));
        $wait = $last + 1.0 - microtime(true);
        if ($wait > 0) {
            usleep(intval(min($wait, 1.0) * 1e6));
        }
        Cache::set('spa_geocode_last', (string) microtime(true));
    }
}
