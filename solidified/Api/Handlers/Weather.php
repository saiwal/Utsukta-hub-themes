<?php
namespace Theme\Solidified\Api\Handlers;

use Theme\Solidified\Api\Response;
use Zotlabs\Lib\Cache;

/**
 * GET /api/weather?place=<place name>&unit=c|f
 *
 * Server-side geocode + current-conditions lookup for the SPA's Weather
 * widget, proxied through Open-Meteo (no API key required) — kept server-side
 * so the app's CSP doesn't need a third-party connect-src exception and so
 * repeated widget views don't hammer the upstream API.
 *
 * NOTE: the param is named "place", not "q" — Hubzilla's own front-controller
 * rewrite (.htaccess `RewriteRule ^(.*)$ index.php?q=$1 [QSA,...]`) reads
 * `$_GET['q']` for its own clean-URL routing (boot.php's App::$cmd). QSA
 * appends the real query string after that rewritten `q=`, so a second `q=`
 * param here would collide: PHP keeps only the last `q` value, corrupting
 * Hubzilla's own routing for this request. Never name a query param `q`.
 *
 * Result cached 20 minutes, keyed by the lowercased query + unit.
 */
class Weather
{
    private const CACHE_AGE = '20 MINUTE';
    private const GEOCODE_URL = 'https://geocoding-api.open-meteo.com/v1/search';
    private const FORECAST_URL = 'https://api.open-meteo.com/v1/forecast';

    public function get(): void
    {
        $q = trim($_GET['place'] ?? '');
        $unit = ($_GET['unit'] ?? 'c') === 'f' ? 'f' : 'c';

        if (!$q || strlen($q) > 100) {
            Response::error(400, 'Invalid location');
        }

        $cache_key = 'spa_weather:' . strtolower($q) . ':' . $unit;
        $cached = Cache::get($cache_key, self::CACHE_AGE);
        if ($cached) {
            $data = json_decode($cached, true);
            if (is_array($data)) {
                Response::send($data);
            }
        }

        $geo_url = self::GEOCODE_URL . '?' . http_build_query([
            'name' => $q,
            'count' => 1,
            'language' => 'en',
            'format' => 'json',
        ]);
        $geo_res = z_fetch_url($geo_url, false, 0, ['timeout' => 10]);
        if (!$geo_res['success'] || !$geo_res['body']) {
            Response::error(502, 'Geocoding lookup failed');
        }
        $geo = json_decode($geo_res['body'], true);
        $place = $geo['results'][0] ?? null;
        if (!$place) {
            Response::error(404, 'Location not found');
        }

        $lat = (float) $place['latitude'];
        $lon = (float) $place['longitude'];

        $forecast_url = self::FORECAST_URL . '?' . http_build_query([
            'latitude' => $lat,
            'longitude' => $lon,
            'current_weather' => 'true',
            'temperature_unit' => $unit === 'f' ? 'fahrenheit' : 'celsius',
            'windspeed_unit' => $unit === 'f' ? 'mph' : 'kmh',
            'timezone' => 'auto',
        ]);
        $fc_res = z_fetch_url($forecast_url, false, 0, ['timeout' => 10]);
        if (!$fc_res['success'] || !$fc_res['body']) {
            Response::error(502, 'Forecast lookup failed');
        }
        $fc = json_decode($fc_res['body'], true);
        $current = $fc['current_weather'] ?? null;
        if (!$current) {
            Response::error(502, 'No forecast data');
        }

        $label = trim(implode(', ', array_filter([
            $place['name'] ?? '',
            $place['admin1'] ?? '',
            $place['country'] ?? '',
        ])));

        $data = [
            'label' => $label,
            'lat' => $lat,
            'lon' => $lon,
            'unit' => $unit,
            'temperature' => $current['temperature'],
            'windspeed' => $current['windspeed'],
            'weathercode' => intval($current['weathercode']),
            'is_day' => intval($current['is_day'] ?? 1) === 1,
            'updated' => $current['time'] ?? null,
        ];

        Cache::set($cache_key, json_encode($data));
        Response::send($data);
    }
}
