<?php

namespace Utsukta\SpaCore\Api\Handlers;

use Utsukta\SpaCore\Api\Response;
use Utsukta\SpaCore\Api\Concerns\FetchesRemoteActor;
use Utsukta\SpaCore\Api\Concerns\ResolvesConnection;

class Xchan
{
    use FetchesRemoteActor;
    use ResolvesConnection;
    public function get(): void
    {
        $hash = $_GET['hash'] ?? null;
        if (!$hash) Response::error(400, 'hash required');

        require_once 'include/channel.php';
        require_once 'include/permissions.php';

        // Look up all xchan rows for this identity — the same channel can have
        // more than one (protocol change, re-key, hub migration) sharing this
        // url/hash but carrying a different xchan_hash each.
        $xchans = q(
            "SELECT * FROM xchan WHERE xchan_url = '%s' OR xchan_hash = '%s'",
            dbesc($hash), dbesc($hash)
        );
        if (!$xchans) Response::error(404, 'Channel not found');

        $xchan = $xchans[0];

        // Connection status — only meaningful for local viewers
        [$is_connected, $abook_id] = $this->connectionFor(
            local_channel(),
            array_column($xchans, 'xchan_hash')
        );

        // Enrich with full profile data if this is a local channel
        $profile_data = [];
        $local_nick = null;
        $channel_row = q(
            "SELECT channel_address FROM channel WHERE channel_hash = '%s' LIMIT 1",
            dbesc($xchan['xchan_hash'])
        );
        if ($channel_row) {
            $local_nick = $channel_row[0]['channel_address'];
            profile_load($local_nick);
            $p = \App::$profile ?? null;
            if ($p && !empty($p['permission_to_view'])) {
                $block = (
                    !empty($p['hidewall'])
                    && !local_channel()
                    && !remote_channel()
                );
                $location_parts = array_filter([
                    $p['locality']     ?? '',
                    $p['region']       ?? '',
                    $p['country_name'] ?? '',
                ]);
                $conn_count = q(
                    "SELECT COUNT(*) AS total FROM abook
                     WHERE abook_channel = %d AND abook_self = 0",
                    intval($p['profile_uid'])
                );
                $profile_data = [
                    'pdesc'       => $block ? '' : ($p['pdesc']    ?? ''),
                    'about'       => $block ? '' : ($p['about']    ?? ''),
                    'location'    => $block ? '' : implode(', ', $location_parts),
                    'homepage'    => $block ? '' : ($p['homepage'] ?? ''),
                    'keywords'    => $block ? [] : array_values(
                        array_filter(explode(' ', $p['keywords'] ?? ''))
                    ),
                    'connections' => intval($conn_count[0]['total'] ?? 0),
                    'cover'       => get_cover_photo(intval($p['profile_uid']), 'url', PHOTO_RES_COVER_1200) ?? '',
                ];
            }
        }

        // For remote channels enrich with WebFinger + AP actor data
        $actor_fields = [];
        if (!$local_nick) {
            $addr = $xchan['xchan_addr'] ?? '';
            if (str_contains($addr, '@')) {
                [, $domain] = explode('@', $addr, 2);
                if (preg_match('/^[a-zA-Z0-9.\-]+(:\d+)?$/', $domain)) {
                    $enriched = $this->fetchActorEnrichment($addr, $domain);
                    if ($enriched) {
                        $profile_data['about']        = $enriched['about'];
                        $profile_data['cover']        = $enriched['cover'];
                        $profile_data['homepage']     = $enriched['url'] ?: ($xchan['xchan_url'] ?? '');
                        $actor_fields                 = $enriched['actor_fields'];
                        $profile_data['remote_posts'] = $enriched['remote_posts'] ?? [];
                        if (!empty($enriched['photo'])) {
                            $xchan['xchan_photo_l'] = $enriched['photo'];
                        }
                    }
                }
            }
        }

        Response::send(array_merge([
            'xchan_hash'   => $xchan['xchan_hash'],
            'name'         => Response::decodeEntities($xchan['xchan_name']),
            'address'      => $xchan['xchan_addr'],
            'url'          => $xchan['xchan_url'],
            'photo'        => $xchan['xchan_photo_l'] ?: ($xchan['xchan_photo_m'] ?? ''),
            'network'      => $xchan['xchan_network'],
            'is_forum'     => (bool) intval($xchan['xchan_pubforum'] ?? 0),
            'is_connected' => $is_connected,
            'abook_id'     => $abook_id,
            'local_nick'   => $local_nick,
            'actor_fields' => $actor_fields,
        ], $profile_data));
    }
}
