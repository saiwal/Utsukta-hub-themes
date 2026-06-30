<?php
namespace Theme\Solidified\Api\Handlers;

use Theme\Solidified\Api\Response;

/**
 * GET /api/directory
 *
 * Queries xchan + xprof directly so ORDER BY is applied before pagination,
 * giving consistent sorted results across all pages.
 *
 * Params: search, keywords, order (date|rdate|alphabetic|ralpha),
 *         global (1|0), network (zot6|activitypub), safe (1|0),
 *         pubforums (1|0), start, suggest (1|0)
 */
class Directory
{
    private const LIMIT = 30;

    public function get(): void
    {
        require_once 'include/bbcode.php';
        require_once 'include/html2plain.php';

        $local_channel = local_channel();
        $observer      = get_observer_hash();

        $search    = notags(trim(rawurldecode($_GET['search']   ?? '')));
        $keywords  = notags(trim(rawurldecode($_GET['keywords'] ?? '')));
        $order     = $_GET['order'] ?? 'date';
        $start     = max(0, intval($_GET['start'] ?? 0));
        $globaldir = array_key_exists('global', $_GET) ? intval($_GET['global']) : 1;
        $suggest   = $local_channel && !empty($_GET['suggest']);
        $network   = in_array($_GET['network'] ?? '', ['zot6', 'activitypub']) ? $_GET['network'] : '';
        $safe      = !empty($_GET['safe']) ? 1 : 0;
        $pubforums = !empty($_GET['pubforums']) ? 1 : 0;

        if ($suggest) {
            require_once 'include/socgraph.php';
            $this->suggest($local_channel, $observer, $order);
        }

        // ── ORDER BY ──────────────────────────────────────────────────────────────
        $order_sql = match ($order) {
            'alphabetic' => 'x.xchan_name ASC',
            'ralpha'     => 'x.xchan_name DESC',
            'rdate'      => 'x.xchan_updated ASC',
            default      => 'x.xchan_updated DESC',   // 'date'
        };

        // ── WHERE conditions ──────────────────────────────────────────────────────
        $conds = [
            'x.xchan_deleted = 0',
            'x.xchan_orphan  = 0',
            'x.xchan_hidden  = 0',
            'x.xchan_system  = 0',
        ];

        if (!$globaldir) {
            $conds[] = "x.xchan_hash IN (SELECT channel_hash FROM channel WHERE channel_removed = 0)";
        }

        if ($search !== '') {
            $s       = protect_sprintf(dbesc($search));
            $conds[] = "(x.xchan_name LIKE '%$s%' OR x.xchan_addr LIKE '%$s%')";
        }

        if ($keywords !== '') {
            $k       = protect_sprintf(dbesc($keywords));
            $conds[] = "p.xprof_keywords LIKE '%$k%'";
        }

        if ($network !== '') {
            $n       = protect_sprintf(dbesc($network));
            $conds[] = "x.xchan_network = '$n'";
        }

        if ($safe) {
            $conds[] = 'x.xchan_censored = 0';
            $conds[] = 'x.xchan_selfcensored = 0';
        }

        if ($pubforums) {
            $conds[] = 'x.xchan_pubforum = 1';
        }

        $where = 'WHERE ' . implode(' AND ', $conds);

        // ── Count ─────────────────────────────────────────────────────────────────
        $cnt   = q("SELECT COUNT(x.xchan_hash) AS total
                    FROM xchan x LEFT JOIN xprof p ON p.xprof_hash = x.xchan_hash
                    $where");
        $total = intval($cnt[0]['total'] ?? 0);

        // ── Paginated query with ORDER BY at query level ───────────────────────────
        // Also JOIN channel + profile so local-channel profile fields are available
        // as fallback when xprof hasn't been populated via federation.
        $rows = q("SELECT
                       x.xchan_hash, x.xchan_name, x.xchan_addr,
                       x.xchan_photo_m, x.xchan_url, x.xchan_network,
                       x.xchan_pubforum, x.xchan_updated,
                       p.xprof_desc, p.xprof_dob,
                       p.xprof_gender, p.xprof_marital,
                       p.xprof_locale, p.xprof_region, p.xprof_country,
                       p.xprof_about, p.xprof_homepage,
                       p.xprof_hometown, p.xprof_keywords,
                       ch.channel_address AS local_nick,
                       ch.channel_id AS local_uid,
                       (SELECT resource_id FROM photo
                        WHERE uid = ch.channel_id AND imgscale = 7 AND photo_usage = 16
                        LIMIT 1) AS cover_resource_id,
                       pr.pdesc      AS local_pdesc,
                       pr.dob        AS local_dob,
                       pr.gender     AS local_gender,
                       pr.marital    AS local_marital,
                       pr.locality   AS local_locale,
                       pr.region     AS local_region,
                       pr.country_name AS local_country,
                       pr.about      AS local_about,
                       pr.homepage   AS local_homepage,
                       pr.hometown   AS local_hometown,
                       pr.keywords   AS local_keywords
                   FROM xchan x
                   LEFT JOIN xprof   p  ON p.xprof_hash    = x.xchan_hash
                   LEFT JOIN channel ch ON ch.channel_hash  = x.xchan_hash AND ch.channel_removed = 0
                   LEFT JOIN profile pr ON pr.uid           = ch.channel_id AND pr.is_default = 1
                   $where
                   ORDER BY $order_sql
                   LIMIT %d OFFSET %d",
                  self::LIMIT, $start);

        Response::send(
            $this->formatRows($rows ?: [], $local_channel),
            [
                'total'     => $total,
                'page'      => intval($start / self::LIMIT) + 1,
                'start'     => $start,
                'limit'     => self::LIMIT,
                'globaldir' => (bool) $globaldir,
                'safe_mode' => 0,
                'suggest'   => false,
                'order'     => $order,
            ]
        );
    }

    // ── Suggest ───────────────────────────────────────────────────────────────────

    private function suggest(int $local_channel, string $observer, string $order): never
    {
        require_once 'include/bbcode.php';
        require_once 'include/html2plain.php';

        $r = suggestion_query($local_channel, $observer, 0, self::LIMIT);
        if (!$r) {
            Response::send([], [
                'total' => 0, 'page' => 1, 'start' => 0, 'limit' => self::LIMIT,
                'globaldir' => true, 'safe_mode' => 0, 'suggest' => true, 'order' => $order,
            ]);
        }

        $entries = $this->formatRows($r, $local_channel, true);

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

    // ── Shared row formatter ───────────────────────────────────────────────────────

    private function formatRows(array $rows, int|false $local_channel, bool $suggest = false): array
    {
        $my_contacts = [];
        if ($local_channel) {
            $cx = q('SELECT abook_xchan FROM abook WHERE abook_channel = %d', intval($local_channel));
            foreach ($cx ?: [] as $c)
                $my_contacts[] = $c['abook_xchan'];
        }

        $entries = [];
        foreach ($rows as $rr) {
            $hash         = $rr['xchan_hash'] ?? '';
            $addr         = $rr['xchan_addr'] ?? '';
            $local_nick   = $rr['local_nick'] ?? '';
            $is_connected = in_array($hash, $my_contacts);
            $connect_url  = ($local_channel && !$is_connected)
                ? z_root() . '/follow?f=&interactive=1&url=' . urlencode($addr)
                : '';

            // For local channels (present in the channel table), prefer the profile
            // table which is always up-to-date over xprof (which is only populated
            // when the channel is synced via federation or directory crawl).
            $desc      = $rr['xprof_desc']      ?: ($rr['local_pdesc']    ?? '');
            $about_raw = $rr['xprof_about']      ?: ($rr['local_about']    ?? '');
            $gender    = $rr['xprof_gender']     ?: ($rr['local_gender']   ?? '');
            $marital   = $rr['xprof_marital']    ?: ($rr['local_marital']  ?? '');
            $locale    = $rr['xprof_locale']     ?: ($rr['local_locale']   ?? '');
            $region    = $rr['xprof_region']     ?: ($rr['local_region']   ?? '');
            $country   = $rr['xprof_country']    ?: ($rr['local_country']  ?? '');
            $homepage  = $rr['xprof_homepage']   ?: ($rr['local_homepage'] ?? '');
            $hometown  = $rr['xprof_hometown']   ?: ($rr['local_hometown'] ?? '');
            $kw_src    = $rr['xprof_keywords']   ?: ($rr['local_keywords'] ?? '');
            $dob_src   = $rr['xprof_dob']        ?: ($rr['local_dob']      ?? '');

            $location_parts = array_filter([$locale, $region, $country]);

            $age = 0;
            if (!empty($dob_src) && ($y = age($dob_src, 'UTC', '')) > 0)
                $age = $y;

            $about = '';
            if (!empty($about_raw)) {
                $about = zidify_links(bbcode($about_raw, ['tryoembed' => false]));
                $about = strip_tags($about, '<br>');
            }

            $kw_raw = str_replace([',', '  '], [' ', ' '], $kw_src);
            $kw_arr = array_values(array_filter(explode(' ', $kw_raw)));

            $profile_url = $local_nick
                ? z_root() . '/channel/' . $local_nick
                : chanlink_url($rr['xchan_url'] ?? '');

            $cover = !empty($rr['cover_resource_id'])
                ? z_root() . '/photo/' . $rr['cover_resource_id'] . '-7'
                : null;

            $entries[] = [
                'hash'         => $hash,
                'name'         => $rr['xchan_name']   ?? '',
                'address'      => $addr,
                'network'      => $rr['xchan_network'] ?? '',
                'photo'        => $rr['xchan_photo_m'] ?? '',
                'description'  => $desc,
                'about'        => $about,
                'location'     => implode(', ', $location_parts),
                'age'          => $age ?: null,
                'gender'       => $gender,
                'marital'      => $marital,
                'homepage'     => html2plain($homepage),
                'hometown'     => html2plain($hometown),
                'keywords'     => $kw_arr,
                'updated'      => $rr['xchan_updated'] ?? '',
                'public_forum' => !empty($rr['xchan_pubforum']),
                'is_connected' => $is_connected,
                'connect_url'  => $connect_url,
                'profile_url'  => $profile_url,
                'cover'        => $cover,
                'common_count' => $suggest ? max(0, intval($rr['total'] ?? 0) - 1) : null,
                'ignore_url'   => $suggest ? z_root() . '/directory?ignore=' . $hash : null,
            ];
        }

        return $entries;
    }
}
