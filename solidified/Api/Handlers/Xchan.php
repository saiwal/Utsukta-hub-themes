<?php

namespace Theme\Solidified\Api\Handlers;

use Theme\Solidified\Api\Response;

class Xchan
{
    public function get(): void
    {
        $hash = $_GET['hash'] ?? null;
        if (!$hash) Response::error(400, 'hash required');

        require_once 'include/channel.php';
        require_once 'include/permissions.php';

        // Look up xchan by URL first, then by hash
        $xchans = q(
            "SELECT * FROM xchan WHERE xchan_url = '%s' LIMIT 1",
            dbesc($hash)
        );
        if (!$xchans) {
            $xchans = q(
                "SELECT * FROM xchan WHERE xchan_hash = '%s' LIMIT 1",
                dbesc($hash)
            );
        }
        if (!$xchans) Response::error(404, 'Channel not found');

        $xchan = $xchans[0];
        $ob_hash = get_observer_hash();

        // Connection status — only meaningful for local viewers
        $is_connected = false;
        $abook_id = null;
        if ($ob_hash && local_channel()) {
            $abook = q(
                "SELECT abook_id FROM abook
                 WHERE abook_channel = %d AND abook_xchan = '%s' LIMIT 1",
                intval(local_channel()),
                dbesc($xchan['xchan_hash'])
            );
            if ($abook) {
                $is_connected = true;
                $abook_id = intval($abook[0]['abook_id']);
            }
        }

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

        Response::send(array_merge([
            'xchan_hash'   => $xchan['xchan_hash'],
            'name'         => $xchan['xchan_name'],
            'address'      => $xchan['xchan_addr'],
            'url'          => $xchan['xchan_url'],
            'photo'        => $xchan['xchan_photo_l'] ?: ($xchan['xchan_photo_m'] ?? ''),
            'network'      => $xchan['xchan_network'],
            'is_forum'     => (bool) intval($xchan['xchan_pubforum'] ?? 0),
            'is_connected' => $is_connected,
            'abook_id'     => $abook_id,
            'local_nick'   => $local_nick,
        ], $profile_data));
    }
}
