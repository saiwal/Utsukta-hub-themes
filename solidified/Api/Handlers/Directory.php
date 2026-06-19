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
 *         global (1|0), start, suggest (1|0)
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

        $where = 'WHERE ' . implode(' AND ', $conds);

        // ── Count ─────────────────────────────────────────────────────────────────
        $cnt   = q("SELECT COUNT(x.xchan_hash) AS total
                    FROM xchan x LEFT JOIN xprof p ON p.xprof_hash = x.xchan_hash
                    $where");
        $total = intval($cnt[0]['total'] ?? 0);

        // ── Paginated query with ORDER BY at query level ───────────────────────────
        $rows = q("SELECT
                       x.xchan_hash, x.xchan_name, x.xchan_addr,
                       x.xchan_photo_m, x.xchan_url, x.xchan_network,
                       x.xchan_pubforum, x.xchan_updated,
                       p.xprof_desc, p.xprof_dob,
                       p.xprof_gender, p.xprof_marital,
                       p.xprof_locale, p.xprof_region, p.xprof_country,
                       p.xprof_about, p.xprof_homepage,
                       p.xprof_hometown, p.xprof_keywords
                   FROM xchan x LEFT JOIN xprof p ON p.xprof_hash = x.xchan_hash
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
            $is_connected = in_array($hash, $my_contacts);
            $connect_url  = ($local_channel && !$is_connected)
                ? z_root() . '/follow?f=&interactive=1&url=' . urlencode($addr)
                : '';

            $location_parts = array_filter([
                $rr['xprof_locale']  ?? '',
                $rr['xprof_region']  ?? '',
                $rr['xprof_country'] ?? '',
            ]);

            $age = 0;
            if (!empty($rr['xprof_dob']) && ($y = age($rr['xprof_dob'], 'UTC', '')) > 0)
                $age = $y;

            $about = '';
            if (!empty($rr['xprof_about'])) {
                $about = zidify_links(bbcode($rr['xprof_about'], ['tryoembed' => false]));
                $about = strip_tags($about, '<br>');
            }

            $kw_raw = str_replace([',', '  '], [' ', ' '], $rr['xprof_keywords'] ?? '');
            $kw_arr = array_values(array_filter(explode(' ', $kw_raw)));

            $entries[] = [
                'hash'         => $hash,
                'name'         => $rr['xchan_name']   ?? '',
                'address'      => $addr,
                'photo'        => $rr['xchan_photo_m'] ?? '',
                'description'  => $rr['xprof_desc']   ?? '',
                'about'        => $about,
                'location'     => implode(', ', $location_parts),
                'age'          => $age ?: null,
                'gender'       => $rr['xprof_gender']  ?? '',
                'marital'      => $rr['xprof_marital'] ?? '',
                'homepage'     => html2plain($rr['xprof_homepage'] ?? ''),
                'hometown'     => html2plain($rr['xprof_hometown'] ?? ''),
                'keywords'     => $kw_arr,
                'updated'      => $rr['xchan_updated'] ?? '',
                'public_forum' => !empty($rr['xchan_pubforum']),
                'is_connected' => $is_connected,
                'connect_url'  => $connect_url,
                'profile_url'  => chanlink_url($rr['xchan_url'] ?? ''),
                'common_count' => $suggest ? max(0, intval($rr['total'] ?? 0) - 1) : null,
                'ignore_url'   => $suggest ? z_root() . '/directory?ignore=' . $hash : null,
            ];
        }

        return $entries;
    }
}
