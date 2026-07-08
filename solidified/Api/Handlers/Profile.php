<?php

namespace Theme\Solidified\Api\Handlers;

use Theme\Solidified\Api\Auth;
use Theme\Solidified\Api\Response;
use Theme\Solidified\Api\Concerns\FetchesRemoteActor;

class Profile
{
    use FetchesRemoteActor;
    public function get(): void {
        $sub = \App::$argv[3] ?? null;
        if ($sub === 'connections') {
            $this->getConnections();
            return;
        }
        if ($sub === 'activity') {
            $this->getActivity();
            return;
        }
        $this->getProfile();
    }

    // Mirrors core rconnect_url(): the connect link lives on the *observer's*
    // hub (their xchan_follow template), filled with the target's reddress.
    // Empty when there is no authenticated observer.
    private function connectUrlFor(string $ob_hash, string $target_addr): string {
        if (!$ob_hash || !$target_addr) {
            return '';
        }
        $r = q("SELECT xchan_follow FROM xchan WHERE xchan_hash = '%s' LIMIT 1",
            dbesc($ob_hash));
        if ($r && $r[0]['xchan_follow']) {
            return sprintf($r[0]['xchan_follow'], urlencode($target_addr));
        }
        $r = q("SELECT hubloc_url FROM hubloc WHERE hubloc_hash = '%s' AND hubloc_primary = 1 LIMIT 1",
            dbesc($ob_hash));
        if ($r) {
            return $r[0]['hubloc_url'] . '/follow?f=&url=' . urlencode($target_addr);
        }
        return '';
    }

    // ── /api/profile/:nick ───────────────────────────────────────────────────

    private function getProfile(): void {
        $nick = \App::$argv[2] ?? null;
        if (!$nick) Response::error(400, 'Nick required');

        require_once 'include/channel.php';
        require_once 'include/permissions.php';

        profile_load($nick);

        if (\App::$error === 404) {
            if (str_contains($nick, '@')) {
                $this->getRemoteProfile($nick);
                return;
            }
            Response::error(404, 'Channel not found');
        }

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
            'connect_url'     => $is_connected ? '' : $this->connectUrlFor($ob_hash, $channel['xchan_addr'] ?? ''),
            'viewer_xchan'    => $ob_hash,
            'viewer_is_local' => (bool) local_channel(),
        ]);
    }

    // ── Remote channel profile via WebFinger + AP actor ─────────────────────

    private function getRemoteProfile(string $nick): void
    {
        [$user, $domain] = explode('@', $nick, 2);

        // Validate domain to prevent SSRF
        if (!preg_match('/^[a-zA-Z0-9.\-]+(:\d+)?$/', $domain)) {
            Response::error(404, 'Channel not found');
        }

        $ob_hash   = get_observer_hash();
        $local_uid = local_channel();

        // xchan cache — this server may already know this actor
        $xchan = q("SELECT * FROM xchan WHERE xchan_addr = '%s' LIMIT 1", dbesc($nick));
        $xrow  = $xchan ? $xchan[0] : null;

        $name   = $xrow['xchan_name']    ?? '';
        $photo  = $xrow['xchan_photo_l'] ?? '';
        $url    = $xrow['xchan_url']     ?? '';
        $about  = '';
        $cover  = '';
        $fields = [];

        $enriched = $this->fetchActorEnrichment($nick, $domain);
        if ($enriched) {
            $name   = $enriched['name']  ?: $name;
            $photo  = $enriched['photo'] ?: $photo;
            $url    = $enriched['url']   ?: $url;
            $about  = $enriched['about'];
            $cover  = $enriched['cover'];
            $fields = $enriched['actor_fields'];
        }

        if (!$name && !$photo) {
            Response::error(404, 'Channel not found');
        }

        // Connection status
        $is_connected = false;
        if ($local_uid && $xrow && !empty($xrow['xchan_hash'])) {
            $ab = q(
                "SELECT abook_id FROM abook WHERE abook_channel = %d AND abook_xchan = '%s' LIMIT 1",
                intval($local_uid),
                dbesc($xrow['xchan_hash'])
            );
            $is_connected = !empty($ab);
        }

        Response::send([
            'channel_name'    => $name,
            'channel_address' => $user,
            'xchan_addr'      => $nick,
            'channel_photo_l' => $photo,
            'channel_cover'   => $cover,
            'pdesc'           => '',
            'about'           => $about,
            'location'        => '',
            'address'         => '',
            'hometown'        => '',
            'homepage'        => $url,
            'keywords'        => [],
            'gender'          => '', 'marital'   => '', 'sexual'    => '',
            'politic'         => '', 'religion'  => '', 'dob'       => '',
            'music'           => '', 'book'      => '', 'tv'        => '',
            'film'            => '', 'interest'  => '', 'romance'   => '',
            'work'            => '', 'education' => '', 'likes'     => '',
            'dislikes'        => '', 'contact'   => '', 'channels'  => '',
            'hide_friends'    => false,
            'connections'     => 0,
            'is_connected'    => $is_connected,
            'connect_url'     => $is_connected ? '' : $this->connectUrlFor($ob_hash, $nick),
            'viewer_xchan'    => $ob_hash,
            'viewer_is_local' => (bool) $local_uid,
            'is_remote'       => true,
            'actor_fields'    => $fields,
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

    // ── /api/profile/:nick/activity ──────────────────────────────────────────
    //
    // Per-day wall-post counts for the last DAYS days, for the Activity
    // Heatmap widget. Respects the same visibility rules as the channel
    // stream (item_permissions_sql), so visitors never see private posts.

    private const ACTIVITY_DAYS = 371;

    private function getActivity(): void {
        $nick = \App::$argv[2] ?? null;
        if (!$nick) Response::error(400, 'Nick required');

        require_once 'include/items.php';
        require_once 'include/security.php';

        $channel = channelx_by_nick($nick);
        if (!$channel || $channel['channel_removed']) Response::error(404, 'Channel not found');

        $uid = intval($channel['channel_id']);
        $observer_xchan = get_observer_hash();

        $perms = get_all_perms($uid, $observer_xchan);
        if (!$perms['view_stream']) Response::error(403, 'Permission denied');

        $since = gmdate('Y-m-d H:i:s', time() - self::ACTIVITY_DAYS * 86400);

        $sql_extra = item_normal($uid);
        $sql_extra .= item_permissions_sql($uid, $observer_xchan);

        $rows = q(
            "SELECT DATE(item.created) AS d, COUNT(*) AS c
               FROM item
              WHERE item.uid = %d
                AND item.item_wall = 1
                AND item.item_thread_top = 1
                AND item.created >= '%s'
                $sql_extra
              GROUP BY DATE(item.created)",
            $uid,
            dbesc($since)
        );

        $counts = [];
        foreach ($rows ?: [] as $r) {
            $counts[(string) $r['d']] = intval($r['c']);
        }

        Response::send([
            'days'   => self::ACTIVITY_DAYS,
            'since'  => $since,
            'counts' => $counts,
        ]);
    }
}
