<?php

namespace Theme\Solidified\Api\Handlers;

use Theme\Solidified\Api\Auth;
use Theme\Solidified\Api\Response;

class Profile
{
public function get(): void {
    $nick = \App::$argv[2] ?? null;
    if (!$nick) Response::error(400, 'Nick required');

    require_once 'include/channel.php';
    require_once 'include/permissions.php';

    // Loads the correct profile for the current observer into App::$profile
    // Handles per-connection profile selection, default fallback,
    // and sets permission_to_view
    profile_load($nick);

    if (\App::$error === 404) Response::error(404, 'Channel not found');

    $profile = \App::$profile;
    if (!$profile) Response::error(404, 'Profile not found');

    // profile_load sets this flag — use it directly
    if (!$profile['permission_to_view']) {
        Response::error(403, 'Permission denied');
    }

    $uid     = intval($profile['profile_uid']);
    $ob_hash = get_observer_hash();

    // hidewall — mirrors profile_sidebar logic
    $block = (
        !empty($profile['hidewall'])
        && !local_channel()
        && !remote_channel()
    );

    $cover         = get_cover_photo($uid, 'array', PHOTO_RES_COVER_1200);
    $default_cover = \Zotlabs\Lib\Config::Get('system', 'default_cover_photo', 'hubzilla');
    $cover_url     = $cover
        ? $cover['url']
        : z_root() . '/images/default_cover_photos/' . $default_cover . '/1200.png';

    $channel = channelx_by_nick($nick);

    $conn_count = q("SELECT COUNT(*) AS total FROM abook
                     WHERE abook_channel = %d AND abook_self = 0",
        intval($uid));

    $is_connected = false;
    if ($ob_hash) {
        $existing = q("SELECT abook_id FROM abook
                       WHERE abook_channel = %d AND abook_xchan = '%s' LIMIT 1",
            intval($uid),
            dbesc($ob_hash));
        $is_connected = !empty($existing);
    }

    $location_parts = array_filter([
        $profile['locality'],
        $profile['region'],
        $profile['country_name'],
    ]);

    Response::send([
        'channel_name'    => $profile['channel_name'],
        'channel_address' => $profile['channel_address'],
        'channel_photo_l' => $channel['xchan_photo_l'] ?? '',
        'channel_cover'   => $cover_url,
        'profile_name'    => $profile['profile_name'],
        'is_default'      => (bool) $profile['is_default'],
        // Fields hidden when hidewall is set
        'pdesc'           => $block ? '' : ($profile['pdesc']     ?? ''),
        'about'           => $block ? '' : ($profile['about']     ?? ''),
        'location'        => $block ? '' : implode(', ', $location_parts),
        'address'         => $block ? '' : ($profile['address']   ?? ''),
        'hometown'        => $block ? '' : ($profile['hometown']  ?? ''),
        'homepage'        => $block ? '' : ($profile['homepage']  ?? ''),
        'keywords'        => $block ? [] : array_values(array_filter(explode(' ', $profile['keywords'] ?? ''))),
        'gender'          => $block ? '' : ($profile['gender']    ?? ''),
        'marital'         => $block ? '' : ($profile['marital']   ?? ''),
        'sexual'          => $block ? '' : ($profile['sexual']    ?? ''),
        'politic'         => $block ? '' : ($profile['politic']   ?? ''),
        'religion'        => $block ? '' : ($profile['religion']  ?? ''),
        'dob'             => $block ? '' : ($profile['dob']       ?? ''),
        'music'           => $block ? '' : ($profile['music']     ?? ''),
        'book'            => $block ? '' : ($profile['book']      ?? ''),
        'tv'              => $block ? '' : ($profile['tv']        ?? ''),
        'film'            => $block ? '' : ($profile['film']      ?? ''),
        'interest'        => $block ? '' : ($profile['interest']  ?? ''),
        'romance'         => $block ? '' : ($profile['romance']   ?? ''),
        'work'            => $block ? '' : ($profile['employment'] ?? ''),
        'education'       => $block ? '' : ($profile['education'] ?? ''),
        'likes'           => $block ? '' : ($profile['likes']     ?? ''),
        'dislikes'        => $block ? '' : ($profile['dislikes']  ?? ''),
        'contact'         => $block ? '' : ($profile['contact']   ?? ''),
        'channels'        => $block ? '' : ($profile['channels']  ?? ''),
        'hide_friends'    => (bool) ($profile['hide_friends'] ?? false),
        'connections'     => intval($conn_count[0]['total']   ?? 0),
        'is_connected'    => $is_connected,
        'viewer_xchan'    => $ob_hash,
        'viewer_is_local' => (bool) local_channel(),
    ]);
}
}
