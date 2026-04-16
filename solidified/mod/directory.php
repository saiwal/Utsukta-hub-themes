<?php
namespace Zotlabs\Module;

use App;

class Directory_api extends \Zotlabs\Web\Controller
{
    function get()
    {
        if (($_GET['format'] ?? '') !== 'json')
            return;

        require_once ('include/socgraph.php');
        require_once ('include/bbcode.php');
        require_once ('include/html2plain.php');

        $observer = get_observer_hash();
        $local_channel = local_channel();

        // ── Parameters ────────────────────────────────────────────────────────────
        $search = isset($_GET['search']) ? notags(trim(rawurldecode($_GET['search']))) : '';
        $keywords = $_GET['keywords'] ?? '';
        $order = $_GET['order'] ?? 'date';
        $start = max(0, intval($_GET['start'] ?? 0));
        $page = intval($start / 30) + 1;

        // Safe mode: default 1; admins can pass -1 to see nsfw
        $safe_mode = \Zotlabs\Lib\Libzotdir::get_directory_setting($observer, 'safemode');
        if (array_key_exists('safe', $_GET))
            $safe_mode = intval($_GET['safe']);

        // Global vs local
        $globaldir = \Zotlabs\Lib\Libzotdir::get_directory_setting($observer, 'globaldir');
        if (array_key_exists('global', $_GET))
            $globaldir = intval($_GET['global']);

        $pubforums = \Zotlabs\Lib\Libzotdir::get_directory_setting($observer, 'pubforums');
        if (array_key_exists('pubforums', $_GET))
            $pubforums = intval($_GET['pubforums']);

        $suggest = ($local_channel && !empty($_GET['suggest'])) ? 1 : 0;

        // ── Suggestion mode ───────────────────────────────────────────────────────
        $addresses = [];
        $common = [];
        $advanced = '';

        if ($suggest) {
            $globaldir = 1;
            $safe_mode = 1;

            $r = suggestion_query($local_channel, $observer, 0, 30);
            if (!$r) {
                $arr['replace'] = true;
                json_return_and_die([
                    'entries' => [],
                    'total' => 0,
                    'page' => 1,
                    'globaldir' => $globaldir,
                    'safe_mode' => $safe_mode,
                    'suggest' => true,
                ]);
            }

            $index = 0;
            foreach ($r as $rr) {
                $common[$rr['xchan_addr']] = max(0, intval($rr['total']) - 1);
                $addresses[$rr['xchan_addr']] = $index++;
            }

            foreach (array_keys($addresses) as $address) {
                $advanced .= 'address="' . $address . '" ';
            }
            $advanced = rtrim($advanced);
        }

        // ── Search normalisation ──────────────────────────────────────────────────
        if ($search && str_starts_with($search, '#')) {
            $keywords = substr($search, 1);
            $search = '';
        }

        // Handle advanced syntax (key=value pairs)
        if ($search &&
                str_contains($search, '=') &&
                $local_channel &&
                feature_enabled($local_channel, 'advanced_dirsearch')) {
            $advanced = $search;
            $search = '';
        }

        // ── Resolve directory URL ─────────────────────────────────────────────────
        $dirmode = intval(\Zotlabs\Lib\Config::Get('system', 'directory_mode'));
        $url = '';

        if (in_array($dirmode, [DIRECTORY_MODE_PRIMARY, DIRECTORY_MODE_SECONDARY, DIRECTORY_MODE_STANDALONE])) {
            $url = z_root() . '/dirsearch';
        }

        if (!$url) {
            $directory = \Zotlabs\Lib\Libzotdir::find_upstream_directory($dirmode);
            if ($directory && !empty($directory['url']))
                $url = $directory['url'] . '/dirsearch';
        }

        if (!$url) {
            json_return_and_die(['error' => 'Directory service unavailable']);
        }

        // ── Build upstream query ──────────────────────────────────────────────────
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

        // ── Upstream fetch ────────────────────────────────────────────────────────
        $x = z_fetch_url($query);
        if (!$x['success']) {
            json_return_and_die(['error' => 'Directory fetch failed']);
        }

        $j = json_decode($x['body'], true);
        if (!$j) {
            json_return_and_die(['error' => 'Directory returned invalid data']);
        }

        // ── Gather local contacts (to suppress connect button) ────────────────────
        $my_contacts = [];
        if ($local_channel) {
            $cx = q('SELECT abook_xchan FROM abook WHERE abook_channel = %d', intval($local_channel));
            if ($cx)
                foreach ($cx as $c)
                    $my_contacts[] = $c['abook_xchan'];
        }

        // ── Format results ────────────────────────────────────────────────────────
        $entries = [];
        $results = $j['results'] ?? [];

        // Re-order for suggestions
        if ($suggest && $addresses) {
            $ordered = [];
            foreach ($results as $rr) {
                if (isset($addresses[$rr['address']]))
                    $ordered[intval($addresses[$rr['address']])] = $rr;
            }
            ksort($ordered);
            $results = array_values($ordered);
        }

        foreach ($results as $rr) {
            $is_connected = in_array($rr['hash'] ?? '', $my_contacts);
            $connect_url = ($local_channel && !$is_connected)
                ? z_root() . '/follow?f=&interactive=1&url=' . urlencode($rr['address'])
                : '';

            $location_parts = array_filter([
                $rr['locale'] ?? '',
                $rr['region'] ?? '',
                $rr['country'] ?? '',
            ]);

            $age = 0;
            if (!empty($rr['birthday']) && ($y = age($rr['birthday'], 'UTC', '')) > 0)
                $age = $y;

            // Render about through bbcode but strip unsafe tags in safe mode
            $about = '';
            if (!empty($rr['about'])) {
                $about = zidify_links(bbcode($rr['about'], ['tryoembed' => false]));
                if ($safe_mode > 0)
                    $about = strip_tags($about, '<br>');
            }

            // Keywords as plain array
            $kw_raw = str_replace([',', '  '], [' ', ' '], $rr['keywords'] ?? '');
            $kw_arr = array_values(array_filter(explode(' ', $kw_raw)));

            $entries[] = [
                'hash' => $rr['hash'] ?? '',
                'name' => $rr['name'] ?? '',
                'address' => $rr['address'] ?? '',
                'photo' => $rr['photo'] ?? '',
                'description' => $rr['description'] ?? '',
                'about' => $about,
                'location' => implode(', ', $location_parts),
                'age' => $age ?: null,
                'gender' => $rr['gender'] ?? '',
                'marital' => $rr['marital'] ?? '',
                'homepage' => html2plain($rr['homepage'] ?? ''),
                'hometown' => html2plain($rr['hometown'] ?? ''),
                'keywords' => $kw_arr,
                'public_forum' => !empty($rr['public_forum']),
                'is_connected' => $is_connected,
                'connect_url' => $connect_url,
                'profile_url' => chanlink_url($rr['url'] ?? ''),
                'common_count' => $suggest ? ($common[$rr['address']] ?? 0) : null,
                'ignore_url' => $suggest ? z_root() . '/directory?ignore=' . ($rr['hash'] ?? '') : null,
            ];
        }

        $arr['replace'] = true;
        json_return_and_die([
            'meta' => [
                'total' => intval($j['records'] ?? 0),
                'page' => $page,
                'start' => $start,
                'limit' => 30,
                'globaldir' => (bool) $globaldir,
                'safe_mode' => $safe_mode,
                'suggest' => (bool) $suggest,
                'order' => $order,
            ],
            'entries' => $entries,
        ]);
    }

    /**
     * ══════════════════════════════════════════════════════════════════════════════
     * APPS MODULE ENDPOINT
     * Hook: appsmanage_content_init  (or equivalent)
     *
     * GET /apps?format=json
     *   view        pinned | featured | system | all  (default: all)
     *
     * POST /apps?format=json
     *   Body JSON:
     *     action    "pin" | "unpin" | "feature" | "unfeature" | "order"
     *     app_id    (string) app id  — for pin/unpin/feature/unfeature
     *     order     (array of app ids in desired order) — for "order"
     *     list      "pinned" | "featured"              — for "order"
     * ══════════════════════════════════════════════════════════════════════════════
     */
    function json_apps_get(&$arr)
    {
        if (($_GET['format'] ?? '') !== 'json')
            return;

        if (!local_channel()) {
            json_return_and_die(['error' => 'Permission denied']);
        }

        $uid = local_channel();
        $view = $_GET['view'] ?? 'all';

        // Keep system apps current (same guard as pconfig endpoint)
        if (get_pconfig($uid, 'system', 'import_system_apps') !== datetime_convert('UTC', 'UTC', 'now', 'Y-m-d')) {
            \Zotlabs\Lib\Apps::import_system_apps();
            set_pconfig($uid, 'system', 'import_system_apps', datetime_convert('UTC', 'UTC', 'now', 'Y-m-d'));
        }
        if (get_pconfig($uid, 'system', 'force_import_system_apps') !== STD_VERSION) {
            \Zotlabs\Lib\Apps::import_system_apps();
            set_pconfig($uid, 'system', 'force_import_system_apps', STD_VERSION);
        }

        $baseurl = z_root();

        // ── Helper: encode + resolve $baseurl placeholders ────────────────────────
        $encode_list = function (array $list) use ($baseurl): array {
            $out = [];
            foreach ($list as $li) {
                $enc = \Zotlabs\Lib\Apps::app_encode($li);
                // Resolve $baseurl placeholder that get_system_apps() leaves in urls
                if (isset($enc['url']))
                    $enc['url'] = str_replace('$baseurl', $baseurl, $enc['url']);
                $out[] = $enc;
            }
            return $out;
        };

        // ── Fetch lists ───────────────────────────────────────────────────────────
        $pinned = [];
        $featured = [];
        $system = [];

        if ($view === 'pinned' || $view === 'all') {
            $list = \Zotlabs\Lib\Apps::app_list($uid, false, ['nav_pinned_app']);
            if ($list) {
                $pinned = $encode_list($list);
                \Zotlabs\Lib\Apps::translate_system_apps($pinned);
                usort($pinned, 'Zotlabs\Lib\Apps::app_name_compare');
                $pinned = \Zotlabs\Lib\Apps::app_order($uid, $pinned, 'nav_pinned_app');
            }
        }

        if ($view === 'featured' || $view === 'all') {
            $list = \Zotlabs\Lib\Apps::app_list($uid, false, ['nav_featured_app']);
            if ($list) {
                $featured = $encode_list($list);
                \Zotlabs\Lib\Apps::translate_system_apps($featured);
                usort($featured, 'Zotlabs\Lib\Apps::app_name_compare');
                $featured = \Zotlabs\Lib\Apps::app_order($uid, $featured, 'nav_featured_app');
            }
        }

        if ($view === 'system' || $view === 'all') {
            $raw = \Zotlabs\Lib\Apps::get_system_apps(true);
            \Zotlabs\Lib\Apps::translate_system_apps($raw);
            usort($raw, 'Zotlabs\Lib\Apps::app_name_compare');

            // Resolve $baseurl in system apps
            foreach ($raw as &$app) {
                if (isset($app['url']))
                    $app['url'] = str_replace('$baseurl', $baseurl, $app['url']);
            }
            unset($app);

            $system = \Zotlabs\Lib\Apps::app_order($uid, $raw, 'nav_featured_app');
        }

        // ── Build pinned/featured id sets for is_pinned / is_featured flags ───────
        $pinned_ids = array_column($pinned, 'id');
        $featured_ids = array_column($featured, 'id');

        $format_app = function (array $app) use ($pinned_ids, $featured_ids): array {
            return [
                'id' => $app['id'] ?? '',
                'name' => $app['name'] ?? '',
                'url' => $app['url'] ?? '',
                'photo' => $app['photo'] ?? '',
                'description' => $app['desc'] ?? ($app['description'] ?? ''),
                'bi_icon' => $app['icon'] ?? '',
                'requires' => $app['requires'] ?? '',
                'is_pinned' => in_array($app['id'] ?? '', $pinned_ids),
                'is_featured' => in_array($app['id'] ?? '', $featured_ids),
                'categories' => !empty($app['categories'])
                    ? array_values(array_filter(explode(',', $app['categories'])))
                    : [],
            ];
        };

        $arr['replace'] = true;
        json_return_and_die([
            'pinned' => array_map($format_app, $pinned),
            'featured' => array_map($format_app, $featured),
            'system' => array_map($format_app, $system),
        ]);
    }

    function json_apps_post(&$arr)
    {
        if (($_GET['format'] ?? '') !== 'json')
            return;

        if (!local_channel()) {
            json_return_and_die(['error' => 'Permission denied']);
        }

        $uid = local_channel();
        $body = json_decode(file_get_contents('php://input'), true);

        if (!$body) {
            http_response_code(400);
            json_return_and_die(['error' => 'Invalid JSON body']);
        }

        $action = $body['action'] ?? '';

        switch ($action) {
            // ── Pin / unpin ───────────────────────────────────────────────────────
            case 'pin':
            case 'unpin':
                {
                    $app_id = $body['app_id'] ?? '';
                    if (!$app_id)
                        json_return_and_die(['error' => 'app_id required']);

                    $app = \Zotlabs\Lib\Apps::get_app($uid, $app_id);
                    if (!$app)
                        json_return_and_die(['error' => 'App not found']);

                    $categories = array_values(array_filter(
                        explode(',', $app['app_categories'] ?? '')
                    ));

                    if ($action === 'pin') {
                        if (!in_array('nav_pinned_app', $categories))
                            $categories[] = 'nav_pinned_app';
                    } else {
                        $categories = array_values(array_diff($categories, ['nav_pinned_app']));
                    }

                    $app['app_categories'] = implode(',', $categories);
                    \Zotlabs\Lib\Apps::update_app($uid, $app);

                    json_return_and_die(['status' => 'ok', 'action' => $action, 'app_id' => $app_id]);
                }

            // ── Feature / unfeature ───────────────────────────────────────────────
            case 'feature':
            case 'unfeature':
                {
                    $app_id = $body['app_id'] ?? '';
                    if (!$app_id)
                        json_return_and_die(['error' => 'app_id required']);

                    $app = \Zotlabs\Lib\Apps::get_app($uid, $app_id);
                    if (!$app)
                        json_return_and_die(['error' => 'App not found']);

                    $categories = array_values(array_filter(
                        explode(',', $app['app_categories'] ?? '')
                    ));

                    if ($action === 'feature') {
                        if (!in_array('nav_featured_app', $categories))
                            $categories[] = 'nav_featured_app';
                    } else {
                        $categories = array_values(array_diff($categories, ['nav_featured_app']));
                    }

                    $app['app_categories'] = implode(',', $categories);
                    \Zotlabs\Lib\Apps::update_app($uid, $app);

                    json_return_and_die(['status' => 'ok', 'action' => $action, 'app_id' => $app_id]);
                }

            // ── Reorder ───────────────────────────────────────────────────────────
            case 'order':
                {
                    $ids = $body['order'] ?? [];
                    $list = $body['list'] ?? 'pinned';  // "pinned" | "featured"

                    if (!is_array($ids) || empty($ids))
                        json_return_and_die(['error' => 'order array required']);

                    $pref_key = ($list === 'featured') ? 'nav_featured_app' : 'nav_pinned_app';

                    // Hubzilla stores order as pconfig cat=apps, key={pref_key}_order, value=comma-separated ids
                    set_pconfig($uid, 'apps', $pref_key . '_order', implode(',', $ids));

                    json_return_and_die(['status' => 'ok', 'action' => 'order', 'list' => $list]);
                }

            default:
                json_return_and_die(['error' => 'Unknown action: ' . $action]);
        }
    }
}
