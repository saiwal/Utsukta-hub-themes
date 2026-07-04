<?php

namespace Theme\Solidified\Api\Concerns;

trait FetchesRemoteActor
{
    /**
     * WebFinger → AP actor → normalised profile fields.
     * Returns null if the actor could not be fetched.
     */
    protected function fetchActorEnrichment(string $addr, string $domain): ?array
    {
        // WebFinger to discover the actor URL
        $wf_body = $this->fetchRemoteUrl(
            'https://' . $domain . '/.well-known/webfinger?resource=' . urlencode('acct:' . $addr),
            ['Accept: application/json']
        );
        if (!$wf_body) return null;

        $actor_url = null;
        $wf = json_decode($wf_body, true) ?? [];
        foreach ($wf['links'] ?? [] as $link) {
            $rel  = $link['rel']  ?? '';
            $type = $link['type'] ?? '';
            if ($rel === 'self' && (
                str_contains($type, 'activity+json') || str_contains($type, 'ld+json')
            )) {
                $actor_url = $link['href'] ?? null;
                break;
            }
        }
        if (!$actor_url) return null;

        // Fetch the AP actor
        $actor_body = $this->fetchRemoteUrl($actor_url, [
            'Accept: application/activity+json, application/ld+json; profile="https://www.w3.org/ns/activitystreams"',
        ]);
        if (!$actor_body) return null;

        $actor = json_decode($actor_body, true);
        if (!$actor) return null;

        $fields = [];
        foreach ($actor['attachment'] ?? [] as $f) {
            if (($f['type'] ?? '') === 'PropertyValue' && !empty($f['name'])) {
                $fields[] = [
                    'name'  => $f['name'],
                    'value' => html_entity_decode(strip_tags($f['value'] ?? ''), ENT_QUOTES, 'UTF-8'),
                ];
            }
        }

        return [
            'name'         => $actor['name']         ?? '',
            'about'        => isset($actor['summary'])
                ? html_entity_decode(strip_tags($actor['summary']), ENT_QUOTES, 'UTF-8')
                : '',
            'url'          => $actor['url']           ?? '',
            'photo'        => $actor['icon']['url']   ?? '',
            'cover'        => $actor['image']['url']  ?? '',
            'actor_fields' => $fields,
        ];
    }

    protected function fetchRemoteUrl(string $url, array $headers = []): ?string
    {
        if (!function_exists('curl_init')) return null;
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 3,
            CURLOPT_TIMEOUT        => 8,
            CURLOPT_USERAGENT      => 'Hubzilla/1.0 (+https://hubzilla.org)',
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $body   = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return ($body !== false && $status >= 200 && $status < 300) ? $body : null;
    }
}
