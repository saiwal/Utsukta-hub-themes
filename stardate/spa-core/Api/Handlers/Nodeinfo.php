<?php

namespace Utsukta\SpaCore\Api\Handlers;

use Utsukta\SpaCore\Api\Response;
use Utsukta\SpaCore\Api\Concerns\FetchesRemoteActor;
use Zotlabs\Lib\Cache;

class Nodeinfo
{
    use FetchesRemoteActor;

    public function get(): void
    {
        $url = $_GET['url'] ?? '';
        $host = parse_url($url, PHP_URL_HOST);
        if (!$host) Response::error(400, 'url required');

        $cached = Cache::get('nodeinfo:' . $host, '7 DAY');
        if ($cached !== null) {
            Response::send(['software' => json_decode($cached, true)['software'] ?? null]);
        }

        $software = $this->discoverSoftware($host);
        Cache::set('nodeinfo:' . $host, json_encode(['software' => $software]));
        Response::send(['software' => $software]);
    }

    private function discoverSoftware(string $host): ?string
    {
        $wf_body = $this->fetchRemoteUrl(
            'https://' . $host . '/.well-known/nodeinfo',
            ['Accept: application/json']
        );
        if (!$wf_body) return null;

        $wf = json_decode($wf_body, true) ?? [];
        $href = null;
        foreach ($wf['links'] ?? [] as $link) {
            if (str_contains($link['rel'] ?? '', 'nodeinfo.diaspora.software/ns/schema/2')) {
                $href = $link['href'] ?? null;
                break;
            }
        }
        if (!$href) return null;

        $ni_body = $this->fetchRemoteUrl($href, ['Accept: application/json']);
        if (!$ni_body) return null;

        $ni = json_decode($ni_body, true);
        $name = $ni['software']['name'] ?? null;
        return $name ? strtolower($name) : null;
    }
}
