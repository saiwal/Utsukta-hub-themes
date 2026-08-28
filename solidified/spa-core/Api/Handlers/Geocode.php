<?php
namespace Utsukta\SpaCore\Api\Handlers;

use Utsukta\SpaCore\Api\Response;
use Zotlabs\Lib\Cache;

/**
 * GET /spa/geocode?place=<place name>
 *
 * Forward geocode for the SPA's map feature ([map]Place[/map] bodies and the
 * composer's map picker). Proxied server-side through the openstreetmap
 * addon's configured Nominatim server so the app needs no third-party
 * connect-src CSP exception, and so Nominatim sees one identified server
 * instead of every viewer's browser (their usage policy asks for exactly
 * that, plus aggressive caching — hence the 24h Cache below; a place name's
 * coordinates don't move).
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
    private const CACHE_AGE = '24 HOUR';

    public function get(): void
    {
        if (!plugin_is_installed('openstreetmap')) {
            Response::error(404, 'Map support not enabled');
        }

        $place = trim($_GET['place'] ?? '');
        if (!$place || mb_strlen($place) > 200) {
            Response::error(400, 'Invalid location');
        }

        $cache_key = 'spa_geocode:' . mb_strtolower($place);
        $cached = Cache::get($cache_key, self::CACHE_AGE);
        if ($cached) {
            $data = json_decode($cached, true);
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

        $res = z_fetch_url($url, false, 0, ['timeout' => 10]);
        if (!$res['success'] || !$res['body']) {
            Response::error(502, 'Geocoding lookup failed');
        }

        $hit = (json_decode($res['body'], true) ?: [])[0] ?? null;
        if (!$hit || !isset($hit['lat'], $hit['lon'])) {
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
}
