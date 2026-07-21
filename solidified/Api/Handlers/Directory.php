<?php
namespace Theme\Solidified\Api\Handlers;

use Theme\Solidified\Api\Response;
use Theme\Solidified\Api\Concerns\FiltersBlockedChannels;

/**
 * GET /api/directory
 *
 * Delegates to /dirsearch — the same endpoint the core Directory module uses —
 * so remote-channel profile data comes from the federated directory rather than
 * the local xchan/xprof cache (which may be stale or empty).
 *
 * For local channels the response is supplemented with profile-table data
 * (always current) and cover-photo URLs, both via batched queries.
 *
 * Params: search, keywords, order (date|rdate|alphabetic|ralpha),
 *         global (1|0), safe (1|0), pubforums (1|0), start, suggest (1|0)
 */
class Directory
{
    use FiltersBlockedChannels;

    private const LIMIT = 30;

    // Our order keys → dirsearch order keys
    private const ORDER_MAP = [
        'alphabetic' => 'normal',
        'ralpha'     => 'reverse',
        'rdate'      => 'reversedate',
        'date'       => '',          // default in dirsearch: xchan_name_date DESC
    ];

    public function get(): void
    {
        require_once 'include/bbcode.php';
        require_once 'include/html2plain.php';
        require_once 'include/socgraph.php';

        $local_channel = local_channel();
        $observer      = get_observer_hash();

        $search    = notags(trim(rawurldecode($_GET['search']   ?? '')));
        $keywords  = notags(trim(rawurldecode($_GET['keywords'] ?? '')));
        $order     = $_GET['order'] ?? 'date';
        $start     = max(0, intval($_GET['start'] ?? 0));
        $globaldir = array_key_exists('global', $_GET) ? intval($_GET['global']) : 1;
        $suggest   = $local_channel && !empty($_GET['suggest']);
        $safe      = !empty($_GET['safe']) ? 1 : 0;
        $pubforums = !empty($_GET['pubforums']) ? 1 : 0;

        if ($suggest) {
            $this->suggest($local_channel, $observer, $order, $safe);
        }

        // ── Resolve /dirsearch URL ─────────────────────────────────────────────
        $dirmode = intval(\Zotlabs\Lib\Config::Get('system', 'directory_mode'));

        if (in_array($dirmode, [DIRECTORY_MODE_PRIMARY, DIRECTORY_MODE_SECONDARY, DIRECTORY_MODE_STANDALONE])) {
            $base_url = z_root() . '/dirsearch';
        } else {
            $dir      = \Zotlabs\Lib\Libzotdir::find_upstream_directory($dirmode);
            $base_url = rtrim($dir['url'] ?? z_root(), '/') . '/dirsearch';
        }

        // ── Build query params ─────────────────────────────────────────────────
        $page     = intval($start / self::LIMIT) + 1;
        $ds_order = self::ORDER_MAP[$order] ?? '';

        $params = [
            'f'            => '',
            'return_total' => 1,
            'n'            => self::LIMIT,
            'p'            => $page,
        ];

        // dirsearch defaults 'safe' to 1 (on) when the param is absent, so the
        // off case must be sent explicitly rather than merely omitted.
        $params['safe'] = $safe;
        if ($pubforums) $params['pubforums'] = 1;
        if (!$globaldir) $params['hub']      = \App::get_hostname();
        if ($ds_order)  $params['order']     = $ds_order;

        if ($search) {
            $params['name']     = $search;
            $params['keywords'] = $search;
        }
        if ($keywords) {
            $params['keywords'] = $keywords;
        }

        $token = \Zotlabs\Lib\Config::Get('system', 'realm_token');
        if ($token) $params['t'] = $token;

        $query_url = $base_url . '?' . http_build_query($params);
        logger('Directory API: dirsearch → ' . $query_url, LOGGER_DEBUG);

        // ── Call /dirsearch ───────────────────────────────────────────────────
        $x = z_fetch_url($query_url);

        if (!$x['success']) {
            Response::error(502, 'Directory service unavailable');
        }

        $j = json_decode($x['body'], true);

        if (empty($j['success'])) {
            Response::send([], [
                'total' => 0, 'page' => $page, 'start' => $start, 'limit' => self::LIMIT,
                'globaldir' => (bool) $globaldir, 'safe_mode' => $safe,
                'suggest' => false, 'order' => $order,
            ]);
        }

        $results = $j['results'] ?? [];
        $total   = intval($j['total_items'] ?? $j['records'] ?? count($results));

        Response::send(
            $this->formatResults($results, $local_channel, $safe),
            [
                'total'     => $total,
                'page'      => intval($j['page'] ?? $page),
                'start'     => $start,
                'limit'     => self::LIMIT,
                'globaldir' => (bool) $globaldir,
                'safe_mode' => $safe,
                'suggest'   => false,
                'order'     => $order,
            ]
        );
    }

    // ── Suggest ───────────────────────────────────────────────────────────────────
    // Mirrors the core Directory module: get suggestion addresses, then query
    // /dirsearch with an advanced address query so profile data is federated.

    private function suggest(int $local_channel, string $observer, string $order, int $safe): never
    {
        require_once 'include/bbcode.php';
        require_once 'include/html2plain.php';

        $r = suggestion_query($local_channel, $observer, 0, self::LIMIT);
        if (!$r) {
            Response::send([], [
                'total' => 0, 'page' => 1, 'start' => 0, 'limit' => self::LIMIT,
                'globaldir' => true, 'safe_mode' => 1, 'suggest' => true, 'order' => $order,
            ]);
        }

        // Build address→common_count and address→sort-index maps (core pattern)
        $common    = [];
        $addresses = [];
        $index     = 0;
        foreach ($r as $rr) {
            $addr              = $rr['xchan_addr'];
            $common[$addr]     = max(0, intval($rr['total'] ?? 0) - 1);
            $addresses[$addr]  = $index++;
        }

        // Build advanced dirsearch query: address="addr1" address="addr2" …
        $advanced = '';
        foreach (array_keys($addresses) as $addr) {
            $advanced .= 'address="' . dbesc($addr) . '" ';
        }
        $advanced = rtrim($advanced);

        $dirmode  = intval(\Zotlabs\Lib\Config::Get('system', 'directory_mode'));
        if (in_array($dirmode, [DIRECTORY_MODE_PRIMARY, DIRECTORY_MODE_SECONDARY, DIRECTORY_MODE_STANDALONE])) {
            $base_url = z_root() . '/dirsearch';
        } else {
            $dir      = \Zotlabs\Lib\Libzotdir::find_upstream_directory($dirmode);
            $base_url = rtrim($dir['url'] ?? z_root(), '/') . '/dirsearch';
        }

        $params = ['f' => '', 'safe' => 1, 'n' => self::LIMIT, 'query' => $advanced];
        $token  = \Zotlabs\Lib\Config::Get('system', 'realm_token');
        if ($token) $params['t'] = $token;

        $x = z_fetch_url($base_url . '?' . http_build_query($params));

        if (!$x['success']) {
            Response::send([], [
                'total' => 0, 'page' => 1, 'start' => 0, 'limit' => self::LIMIT,
                'globaldir' => true, 'safe_mode' => 1, 'suggest' => true, 'order' => $order,
            ]);
        }

        $j       = json_decode($x['body'], true);
        $results = $j['results'] ?? [];

        // Restore original suggestion order and attach common_count
        $ordered = [];
        foreach ($results as $rr) {
            $addr = $rr['address'] ?? '';
            if (isset($addresses[$addr])) {
                $ordered[$addresses[$addr]] = array_merge($rr, [
                    '_common_count' => $common[$addr] ?? 0,
                    '_ignore_hash'  => $rr['hash'],
                ]);
            }
        }
        ksort($ordered);

        $entries = $this->formatResults(array_values($ordered), local_channel(), $safe, true);

        Response::send($entries, [
            'total'     => count($entries),
            'page'      => 1,
            'start'     => 0,
            'limit'     => self::LIMIT,
            'globaldir' => true,
            'safe_mode' => 1,
            'suggest'   => true,
            'order'     => $order,
        ]);
    }

    // ── Format /dirsearch results ──────────────────────────────────────────────────

    private function formatResults(
        array      $results,
        int|false  $local_channel,
        int        $safe,
        bool       $suggest = false
    ): array {
        if (!$results) return [];

        require_once 'include/bbcode.php';
        require_once 'include/html2plain.php';

        // ── Batch-load supplemental local data ────────────────────────────────
        $hashes     = array_filter(array_column($results, 'hash'));
        $hashes_sql = implode("','", array_map('dbesc', $hashes));

        $blocked = $local_channel ? $this->blockedXchans((int) $local_channel) : [];

        // Local channels: get nick + channel_id
        $local_map = [];
        if ($hashes) {
            $chan_rows = q("SELECT channel_hash, channel_address, channel_id
                            FROM channel
                            WHERE channel_hash IN ('$hashes_sql') AND channel_removed = 0");
            foreach ($chan_rows ?: [] as $cr) {
                $local_map[$cr['channel_hash']] = [
                    'nick' => $cr['channel_address'],
                    'uid'  => intval($cr['channel_id']),
                ];
            }
        }

        // Local profile data (supplemental when dirsearch fields are empty)
        $profile_map = [];
        if ($local_map) {
            $uids_sql = implode(',', array_map('intval', array_column($local_map, 'uid')));
            $prof_rows = q("SELECT uid, pdesc, dob, gender, marital, locality, region, country_name,
                                   about, homepage, hometown, keywords
                            FROM profile
                            WHERE uid IN ($uids_sql) AND is_default = 1");
            foreach ($prof_rows ?: [] as $pr) {
                $profile_map[intval($pr['uid'])] = $pr;
            }
        }

        // Cover photos for local channels (batched)
        $cover_map = [];
        if ($local_map) {
            $uids_sql  = implode(',', array_map('intval', array_column($local_map, 'uid')));
            $cover_rows = q("SELECT uid, resource_id FROM photo
                             WHERE uid IN ($uids_sql)
                               AND imgscale = %d AND photo_usage = %d",
                            intval(PHOTO_RES_COVER_1200),
                            intval(PHOTO_COVER));
            foreach ($cover_rows ?: [] as $cvr) {
                $cover_map[intval($cvr['uid'])] = $cvr['resource_id'];
            }
        }

        // Connected contacts for the viewing user
        $my_contacts = [];
        if ($local_channel) {
            $cx = q('SELECT abook_xchan FROM abook WHERE abook_channel = %d', intval($local_channel));
            foreach ($cx ?: [] as $c)
                $my_contacts[] = $c['abook_xchan'];
        }

        // ── Build entries ──────────────────────────────────────────────────────
        $entries = [];
        foreach ($results as $rr) {
            $hash       = $rr['hash']    ?? '';
            if ($this->isBlockedHash($blocked, $hash)) continue;
            $addr       = $rr['address'] ?? '';
            $local_info = $local_map[$hash] ?? null;
            $local_nick = $local_info['nick'] ?? '';
            $local_uid  = $local_info ? intval($local_info['uid']) : 0;
            $prof       = $local_uid ? ($profile_map[$local_uid] ?? []) : [];

            $is_connected = in_array($hash, $my_contacts);
            $connect_url  = ($local_channel && !$is_connected)
                ? z_root() . '/follow?f=&interactive=1&url=' . urlencode($addr)
                : '';

            // dirsearch fields first; local profile table as fallback for local channels
            $desc      = ($rr['description'] ?? '') ?: ($prof['pdesc']       ?? '');
            $about_raw = ($rr['about']       ?? '') ?: ($prof['about']        ?? '');
            $gender    = ($rr['gender']      ?? '') ?: ($prof['gender']       ?? '');
            $marital   = ($rr['marital']     ?? '') ?: ($prof['marital']      ?? '');
            $locale    = ($rr['locale']      ?? '') ?: ($prof['locality']     ?? '');
            $region    = ($rr['region']      ?? '') ?: ($prof['region']       ?? '');
            $country   = ($rr['country']     ?? '') ?: ($prof['country_name'] ?? '');
            $homepage  = ($rr['homepage']    ?? '') ?: ($prof['homepage']     ?? '');
            $hometown  = ($rr['hometown']    ?? '') ?: ($prof['hometown']     ?? '');
            $kw_src    = ($rr['keywords']    ?? '') ?: ($prof['keywords']     ?? '');
            $dob_src   = ($rr['birthday']    ?? '') ?: ($prof['dob']          ?? '');

            $location_parts = array_filter([$locale, $region, $country]);

            $age = 0;
            if (!empty($dob_src) && ($y = age($dob_src, 'UTC', '')) > 0)
                $age = $y;
            elseif (($age_raw = intval($rr['age'] ?? 0)) > 0)
                $age = $age_raw;

            $about = '';
            if (!empty($about_raw)) {
                $about = zidify_links(bbcode($about_raw, ['tryoembed' => false]));
                if ($safe > 0) $about = strip_tags($about, '<br>');
            }

            $kw_raw = str_replace([',', '  '], [' ', ' '], $kw_src);
            $kw_arr = array_values(array_filter(explode(' ', $kw_raw)));

            $profile_url = $local_nick
                ? z_root() . '/channel/' . $local_nick
                : ($rr['url'] ?? '');

            $cover_rid = $local_uid ? ($cover_map[$local_uid] ?? null) : null;
            $cover     = $cover_rid
                ? z_root() . '/photo/' . $cover_rid . '-' . PHOTO_RES_COVER_1200
                : null;

            $entries[] = [
                'hash'         => $hash,
                'name'         => $rr['name']        ?? '',
                'address'      => $addr,
                'network'      => 'zot6',
                'photo'        => $rr['photo']        ?? '',
                'description'  => $desc,
                'about'        => $about,
                'location'     => implode(', ', $location_parts),
                'age'          => $age ?: null,
                'gender'       => $gender,
                'marital'      => $marital,
                'homepage'     => html2plain($homepage),
                'hometown'     => html2plain($hometown),
                'keywords'     => $kw_arr,
                'updated'      => '',
                'public_forum' => !empty($rr['public_forum']),
                'is_connected' => $is_connected,
                'connect_url'  => $connect_url,
                'profile_url'  => $profile_url,
                'cover'        => $cover,
                'common_count' => $suggest ? ($rr['_common_count'] ?? null) : null,
                'ignore_url'   => $suggest ? z_root() . '/directory?ignore=' . $hash : null,
            ];
        }

        return $entries;
    }
}
