<?php
namespace Zotlabs\Module;

class Weather extends \Zotlabs\Web\Controller
{
    function get()
    {
        // support city OR lat/lon
        $q = trim($_GET['q'] ?? '');
        $lat = $_GET['lat'] ?? null;
        $lon = $_GET['lon'] ?? null;

        if ($lat && $lon) {
            $location = $lat . ',' . $lon;
        } else {
            $location = $q ?: 'Delhi';  // fallback
        }

        $url = 'https://wttr.in/' . urlencode($location) . '?format=j1';

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'hubzilla/solidified');
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

        $body = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if (!$body || $code !== 200) {
            http_response_code(502);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'weather unavailable']);
            exit;
        }

        header('Content-Type: application/json');
        header('Cache-Control: public, max-age=1800');
        echo $body;
        exit;
    }
}
