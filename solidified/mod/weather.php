<?php
namespace Zotlabs\Module;

class Weather extends \Zotlabs\Web\Controller
{
    function get()
    {
        $location = trim($_GET['q'] ?? '');
        $url = 'https://wttr.in/' . urlencode($location) . '?format=j1';

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Hubzilla/solidified');
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        $body = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if (!$body || $code !== 200) {
            http_response_code(502);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Weather unavailable']);
            exit;
        }

        header('Content-Type: application/json');
        header('Cache-Control: public, max-age=1800');
        echo $body;
        exit;
    }
}
