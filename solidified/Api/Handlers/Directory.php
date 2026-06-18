<?php
namespace Theme\Solidified\Api\Handlers;

use Theme\Solidified\Api\Response;

class Directory
{
    public function get(): void
    {
        require_once 'include/socgraph.php';
        require_once 'include/bbcode.php';
        require_once 'include/html2plain.php';

        $observer       = get_observer_hash();
        $local_channel  = local_channel();

        $search   = isset($_GET['search'])   ? notags(trim(rawurldecode($_GET['search']))) : '';
        $keywords = $_GET['keywords'] ?? '';
        $order    = $_GET['order']    ?? 'date';
        $start    = max(0, intval($_GET['start'] ?? 0));
        $page     = intval($start / 30) + 1;
        $suggest  = ($local_channel && !empty($_GET['suggest'])) ? 1 : 0;

        $safe_mode = \Zotlabs\Lib\Libzotdir::get_directory_setting($observer, 'safemode');
        if (array_key_exists('safe', $_GET))
            $safe_mode = intval($_GET['safe']);

        $globaldir = \Zotlabs\Lib\Libzotdir::get_directory_setting($observer, 'globaldir');
        if (array_key_exists('global', $_GET))
            $globaldir = intval($_GET['global']);

        $pubforums = \Zotlabs\Lib\Libzotdir::get_directory_setting($observer, 'pubforums');
        if (array_key_exists('pubforums', $_GET))
            $pubforums = intval($_GET['pubforums']);

        // ── Suggestion mode ───────────────────────────────────────────────────
        $addresses = [];
        $common    = [];
        $advanced  = '';

        if ($suggest) {
            $globaldir = 1;
            $safe_mode = 1;

            $r = suggestion_query($local_channel, $observer, 0, 30);
            if (!$r) {
                Response::send([], [
                    'total'     => 0,
                    'page'      => 1,
                    'start'     => 0,
                    'limit'     => 30,
                    'globaldir' => (bool) $globaldir,
                    'safe_mode' => $safe_mode,
                    'suggest'   => true,
                    'order'     => $order,
                ]);
            }

            $index = 0;
            foreach ($r as $rr) {
                $common[$rr['xchan_addr']]    = max(0, intval($rr['total']) - 1);
                $addresses[$rr['xchan_addr']] = $index++;
            }
            foreach (array_keys($addresses) as $address) {
                $advanced .= 'address="' . $address . '" ';
            }
            $advanced = rtrim($advanced);
        }

        // ── Search normalisation ──────────────────────────────────────────────
        if ($search && str_starts_with($search, '#')) {
            $keywords = substr($search, 1);
            $search   = '';
        }

        if ($search &&
            str_contains($search, '=') &&
            $local_channel &&
            feature_enabled($local_channel, 'advanced_dirsearch')) {
            $advanced = $search;
            $search   = '';
        }

        // ── Resolve directory URL ─────────────────────────────────────────────
        $dirmode = intval(\Zotlabs\Lib\Config::Get('system', 'directory_mode'));
        $url     = '';

        if (in_array($dirmode, [DIRECTORY_MODE_PRIMARY, DIRECTORY_MODE_SECONDARY, DIRECTORY_MODE_STANDALONE])) {
            $url = z_root() . '/dirsearch';
        }

        if (!$url) {
            $directory = \Zotlabs\Lib\Libzotdir::find_upstream_directory($dirmode);
            if ($directory && !empty($directory['url']))
                $url = $directory['url'] . '/dirsearch';
        }

        if (!$url) {
            Response::error(503, 'Directory service unavailable');
        }

        // ── Build upstream query ──────────────────────────────────────────────
        $numtags = intval(\Zotlabs\Lib\Config::Get('system', 'directorytags') ?: 50);
        if (\Zotlabs\Lib\Config::Get('system', 'disable_directory_keywords'))
            $numtags = 0;

        $is_admin = is_site_admin();
        if (intval($safe_mode) === 0 && $is_admin)
            $safe_mode = -1;

        $token = \Zotlabs\Lib\Config::Get('system', 'realm_token');

        $query = $url . '?f=&kw=' . $numtags . ($safe_mode < 1 ? '&safe=' . $safe_mode : '');
        if ($token)
            $query .= '&t=' . $token;
        if (!$globaldir)
            $query .= '&hub=' . \App::get_hostname();
        if ($search)
            $query .= '&name=' . urlencode($search) . '&keywords=' . urlencode($search);
        if (str_contains((string) $search, '@'))
            $query .= '&address=' . urlencode($search);
        if ($keywords)
            $query .= '&keywords=' . urlencode($keywords);
        if ($advanced)
            $query .= '&query=' . urlencode($advanced);
        if (!is_null($pubforums))
            $query .= '&pubforums=' . intval($pubforums);
        if ($order)
            $query .= '&order=' . urlencode($order);
        if ($page > 1)
            $query .= '&p=' . $page;

        // ── Upstream fetch ────────────────────────────────────────────────────
        $x = z_fetch_url($query);
        if (!$x['success']) {
            Response::error(502, 'Directory fetch failed');
        }

        $j = json_decode($x['body'], true);
        if (!$j) {
            Response::error(502, 'Directory returned invalid data');
        }

        // ── Gather local contacts ─────────────────────────────────────────────
        $my_contacts = [];
        if ($local_channel) {
            $cx = q('SELECT abook_xchan FROM abook WHERE abook_channel = %d', intval($local_channel));
            if ($cx)
                foreach ($cx as $c)
                    $my_contacts[] = $c['abook_xchan'];
        }

        // ── Format results ────────────────────────────────────────────────────
        $results = $j['results'] ?? [];

        if ($suggest && $addresses) {
            $ordered = [];
            foreach ($results as $rr) {
                if (isset($addresses[$rr['address']]))
                    $ordered[intval($addresses[$rr['address']])] = $rr;
            }
            ksort($ordered);
            $results = array_values($ordered);
        } else {
            switch ($order) {
                case 'alphabetic':
                    usort($results, fn($a, $b) => strcasecmp($a['name'] ?? '', $b['name'] ?? ''));
                    break;
                case 'ralpha':
                    usort($results, fn($a, $b) => strcasecmp($b['name'] ?? '', $a['name'] ?? ''));
                    break;
                case 'date':
                    usort($results, fn($a, $b) => strcmp($b['updated'] ?? '', $a['updated'] ?? ''));
                    break;
                case 'rdate':
                    usort($results, fn($a, $b) => strcmp($a['updated'] ?? '', $b['updated'] ?? ''));
                    break;
            }
        }

        $entries = [];
        foreach ($results as $rr) {
            $is_connected = in_array($rr['hash'] ?? '', $my_contacts);
            $connect_url  = ($local_channel && !$is_connected)
                ? z_root() . '/follow?f=&interactive=1&url=' . urlencode($rr['address'])
                : '';

            $location_parts = array_filter([
                $rr['locale']  ?? '',
                $rr['region']  ?? '',
                $rr['country'] ?? '',
            ]);

            $age = 0;
            if (!empty($rr['birthday']) && ($y = age($rr['birthday'], 'UTC', '')) > 0)
                $age = $y;

            $about = '';
            if (!empty($rr['about'])) {
                $about = zidify_links(bbcode($rr['about'], ['tryoembed' => false]));
                if ($safe_mode > 0)
                    $about = strip_tags($about, '<br>');
            }

            $kw_raw = str_replace([',', '  '], [' ', ' '], $rr['keywords'] ?? '');
            $kw_arr = array_values(array_filter(explode(' ', $kw_raw)));

            $entries[] = [
                'hash'         => $rr['hash']        ?? '',
                'name'         => $rr['name']        ?? '',
                'address'      => $rr['address']     ?? '',
                'photo'        => $rr['photo']        ?? '',
                'description'  => $rr['description'] ?? '',
                'about'        => $about,
                'location'     => implode(', ', $location_parts),
                'age'          => $age ?: null,
                'gender'       => $rr['gender']      ?? '',
                'marital'      => $rr['marital']     ?? '',
                'homepage'     => html2plain($rr['homepage'] ?? ''),
                'hometown'     => html2plain($rr['hometown'] ?? ''),
                'keywords'     => $kw_arr,
                'updated'      => $rr['updated']      ?? '',
                'public_forum' => !empty($rr['public_forum']),
                'is_connected' => $is_connected,
                'connect_url'  => $connect_url,
                'profile_url'  => chanlink_url($rr['url'] ?? ''),
                'common_count' => $suggest ? ($common[$rr['address']] ?? 0) : null,
                'ignore_url'   => $suggest ? z_root() . '/directory?ignore=' . ($rr['hash'] ?? '') : null,
            ];
        }

        Response::send($entries, [
            'total'     => intval($j['records'] ?? 0),
            'page'      => $page,
            'start'     => $start,
            'limit'     => 30,
            'globaldir' => (bool) $globaldir,
            'safe_mode' => $safe_mode,
            'suggest'   => (bool) $suggest,
            'order'     => $order,
        ]);
    }
}
