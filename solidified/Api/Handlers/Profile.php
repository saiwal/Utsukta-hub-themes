<?php

namespace Theme\Solidified\Api\Handlers;

use Theme\Solidified\Api\Auth;
use Theme\Solidified\Api\Response;

class Profile
{
    public function get(): void {
        $sub = \App::$argv[3] ?? null;
        if ($sub === 'connections') {
            $this->getConnections();
            return;
        }
        $this->getProfile();
    }

    // ── /api/profile/:nick ───────────────────────────────────────────────────

    private function getProfile(): void {
        $nick = \App::$argv[2] ?? null;
        if (!$nick) Response::error(400, 'Nick required');

        require_once 'include/channel.php';
        require_once 'include/permissions.php';

        profile_load($nick);

        if (\App::$error === 404) Response::error(404, 'Channel not found');

        $profile = \App::$profile;
        if (!$profile) Response::error(404, 'Profile not found');

        if (!$profile['permission_to_view']) {
            Response::error(403, 'Permission denied');
        }

        $uid     = intval($profile['profile_uid']);
        $ob_hash = get_observer_hash();

        // Fetch channel record early — needed for channel_address and as fallback for channel_name
        $channel = channelx_by_nick($nick);

        // If the observer has a specific profile assigned to them via abook_profile, swap to it.
        // Fall back to local_channel() xchan_hash in case get_observer_hash() returns empty
        // (e.g. App::$observer not initialised in this request context).
        $effective_hash = $ob_hash;
        if (!$effective_hash) {
            $local_uid_check = local_channel();
            if ($local_uid_check) {
                $lc = channelx_by_n($local_uid_check);
                if ($lc && !empty($lc['xchan_hash'])) {
                    $effective_hash = $lc['xchan_hash'];
                }
            }
        }

        if ($effective_hash) {
            $abook_row = q(
                "SELECT abook_profile FROM abook
                 WHERE abook_channel = %d AND abook_xchan = '%s' AND abook_self = 0 LIMIT 1",
                intval($uid),
                dbesc($effective_hash)
            );
            if ($abook_row && intval($abook_row[0]['abook_profile']) > 0) {
                $assigned_id = intval($abook_row[0]['abook_profile']);
                $assigned = q(
                    "SELECT * FROM profile WHERE id = %d AND uid = %d LIMIT 1",
                    intval($assigned_id),
                    intval($uid)
                );
                if ($assigned) {
                    $profile = array_merge($assigned[0], [
                        'permission_to_view' => $profile['permission_to_view'],
                        // Use the assigned profile's fullname as the display name
                        'channel_name'       => $assigned[0]['fullname'] ?: ($channel['channel_name'] ?? ($profile['channel_name'] ?? '')),
                        'channel_address'    => $channel['channel_address'] ?? ($profile['channel_address'] ?? ''),
                        'profile_uid'        => $uid,
                    ]);
                }
            }
        }

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
            'xchan_addr'      => $channel['xchan_addr'] ?? '',
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

    // ── /api/profile/:nick/connections ───────────────────────────────────────

    private function getConnections(): void {
        $nick = \App::$argv[2] ?? null;
        if (!$nick) Response::error(400, 'Nick required');

        require_once 'include/channel.php';

        $channel = channelx_by_nick($nick);
        if (!$channel) Response::error(404, 'Channel not found');

        $uid = intval($channel['channel_id']);

        // Gate on the default profile's hide_friends; the channel owner always sees their own list
        $prow = q(
            "SELECT hide_friends FROM profile WHERE uid = %d AND is_default = 1 LIMIT 1",
            intval($uid)
        );
        $hide_friends = !empty($prow[0]['hide_friends']);
        $is_owner     = (local_channel() === $uid);

        if ($hide_friends && !$is_owner) {
            Response::send(['connections' => [], 'total' => 0, 'hidden' => true]);
            return;
        }

        $limit = min(24, max(1, (int) ($_GET['limit'] ?? 24)));
        $start = max(0, (int) ($_GET['start'] ?? 0));

        $rows = q(
            "SELECT xchan.xchan_name, xchan.xchan_addr,
                    xchan.xchan_photo_m, xchan.xchan_url,
                    channel.channel_address AS local_nick
             FROM abook
             LEFT JOIN xchan   ON abook.abook_xchan   = xchan.xchan_hash
             LEFT JOIN channel ON channel.channel_hash = xchan.xchan_hash
             WHERE abook.abook_channel  = %d
               AND abook.abook_self     = 0
               AND abook.abook_blocked  = 0
               AND abook.abook_pending  = 0
               AND abook.abook_archived = 0
             ORDER BY xchan.xchan_name ASC
             LIMIT %d OFFSET %d",
            intval($uid),
            intval($limit),
            intval($start)
        );

        $total_row = q(
            "SELECT COUNT(*) AS total FROM abook
             WHERE abook_channel = %d AND abook_self = 0
               AND abook_blocked = 0 AND abook_pending = 0 AND abook_archived = 0",
            intval($uid)
        );

        $connections = array_map(fn($r) => [
            'name'       => $r['xchan_name']        ?? '',
            'address'    => $r['xchan_addr']         ?? '',
            'photo'      => $r['xchan_photo_m']      ?? '',
            'url'        => $r['xchan_url']           ?? '',
            'local_nick' => $r['local_nick']          ?: null,
        ], $rows ?: []);

        Response::send([
            'connections' => $connections,
            'total'       => intval($total_row[0]['total'] ?? 0),
            'hidden'      => false,
        ]);
    }
}
