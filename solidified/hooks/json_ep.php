<?php

function json_network_content(&$arr)
{
    if (($_GET['format'] ?? '') !== 'json')
        return;

    require_once ('include/items.php');
    require_once ('include/conversation.php');
    require_once ('include/acl_selectors.php');

    if (!local_channel()) {
        json_return_and_die(['error' => 'Permission denied']);
    }

    $item_normal = item_normal();
    $abook_uids = ' and abook.abook_channel = ' . local_channel() . ' ';
    $uids = ' and item.uid = ' . local_channel() . ' ';
    $observer_xchan = get_observer_hash();

    // ── Pagination ──────────────────────────────────────────────────────────
    $itemspage = get_pconfig(local_channel(), 'system', 'itemspage') ?: 10;
    $offset = intval($_GET['start'] ?? 0);
    $pager_sql = " LIMIT $itemspage OFFSET $offset ";

    // ── Ordering ─────────────────────────────────────────────────────────────
    $order = get_pconfig(local_channel(), 'mod_network', 'order', 'created');
    $nouveau = false;
    switch ($order) {
        case 'commented':
            $ordering = 'commented';
            break;
        case 'unthreaded':
            $nouveau = true;
            $ordering = 'created';
            break;
        default:
            $ordering = 'created';
    }
    // allow override via GET
    if (isset($_GET['order'])) {
        if ($_GET['order'] === 'commented')
            $ordering = 'commented';
        if ($_GET['order'] === 'created')
            $ordering = 'created';
        if ($_GET['order'] === 'unthreaded') {
            $nouveau = true;
            $ordering = 'created';
        }
    }

    // ── Filters ──────────────────────────────────────────────────────────────
    $star = intval($_GET['star'] ?? 0);
    $conv = intval($_GET['conv'] ?? 0);
    $dm = intval($_GET['dm'] ?? 0);
    $gid = intval($_GET['gid'] ?? 0);
    $cid = intval($_GET['cid'] ?? 0);
    $xchan = $_GET['xchan'] ?? '';
    $net = $_GET['net'] ?? '';
    $search = $_GET['search'] ?? '';
    $hashtags = $_GET['tag'] ?? '';
    $category = $_GET['cat'] ?? '';
    $verb = $_GET['verb'] ?? '';
    $file = $_GET['file'] ?? '';
    $datequery = (isset($_GET['dend']) && is_a_date_arg($_GET['dend'])) ? notags($_GET['dend']) : '';
    $datequery2 = (isset($_GET['dbegin']) && is_a_date_arg($_GET['dbegin'])) ? notags($_GET['dbegin']) : '';
    $cmin = array_key_exists('cmin', $_GET) ? intval($_GET['cmin']) : -1;
    $cmax = array_key_exists('cmax', $_GET) ? intval($_GET['cmax']) : -1;

    // search with # prefix becomes hashtag
    if ($search && str_starts_with($search, '#')) {
        $hashtags = substr($search, 1);
        $search = '';
    }

    if ($search || $file || $cid || $hashtags || $verb || $category || $conv) {
        $nouveau = true;
    }

    $sql_options = $star ? ' and item_starred = 1 ' : '';
    $sql_extra = '';
    $item_thread_top = ' AND item_thread_top = 1 ';

    // ── Privacy group ─────────────────────────────────────────────────────────
    if ($gid) {
        $r = q('SELECT * FROM pgrp WHERE id = %d AND uid = %d LIMIT 1',
            intval($gid), intval(local_channel()));
        if ($r) {
            $group_hash = $r[0]['hash'];
            $contacts = \Zotlabs\Lib\AccessList::members(local_channel(), $gid);
            $contact_str = $contacts ? ids_to_querystr($contacts, 'xchan', true) : " '0' ";
            $item_thread_top = '';
            $sql_extra .= " AND item.parent IN (
                SELECT DISTINCT parent FROM item
                WHERE true $sql_options
                AND ((author_xchan IN ($contact_str) OR owner_xchan IN ($contact_str))
                     OR allow_gid LIKE '" . protect_sprintf('%<' . dbesc($group_hash) . '>%') . "')
                AND id = parent $item_normal
            )";
        }
    }

    // ── Specific channel ──────────────────────────────────────────────────────
    if ($cid) {
        $cid_r = q('SELECT abook_xchan FROM abook WHERE abook_id = %d AND abook_channel = %d AND abook_blocked = 0 LIMIT 1',
            intval($cid), intval(local_channel()));
        if ($cid_r) {
            $item_thread_top = '';
            $sql_extra .= ' AND item.parent IN (
                SELECT DISTINCT parent FROM item
                WHERE uid = ' . intval(local_channel()) . "
                AND (author_xchan = '" . dbesc($cid_r[0]['abook_xchan']) . "'
                     OR owner_xchan = '" . dbesc($cid_r[0]['abook_xchan']) . "')
                $item_normal
            )";
        }
    }

    // ── Specific xchan ────────────────────────────────────────────────────────
    if ($xchan) {
        $item_thread_top = '';
        $sql_extra .= " AND item.parent IN (
            SELECT DISTINCT parent FROM item
            WHERE true $sql_options AND uid = " . intval(local_channel()) . "
            AND (author_xchan = '" . dbesc($xchan) . "' OR owner_xchan = '" . dbesc($xchan) . "')
            $item_normal
        )";
    }

    // ── Category / hashtag ────────────────────────────────────────────────────
    if ($category)
        $sql_extra .= protect_sprintf(term_query('item', $category, TERM_CATEGORY));
    if ($hashtags)
        $sql_extra .= protect_sprintf(term_query('item', $hashtags, TERM_HASHTAG, TERM_COMMUNITYTAG));

    // ── Full-text search ──────────────────────────────────────────────────────
    if ($search) {
        $sql_extra .= sprintf(
            " AND (item.body LIKE '%s' OR item.title LIKE '%s') ",
            dbesc(protect_sprintf('%' . $search . '%')),
            dbesc(protect_sprintf('%' . $search . '%'))
        );
    }

    // ── Verb filter ───────────────────────────────────────────────────────────
    if ($verb) {
        if (str_starts_with($verb, '.')) {
            $sql_extra .= sprintf(
                " AND item.obj_type = '%s' AND item.verb IN ('Create','Update','Invite') ",
                dbesc(protect_sprintf(substr($verb, 1)))
            );
        } else {
            $sql_extra .= sprintf(" AND item.verb = '%s' ", dbesc(protect_sprintf($verb)));
        }
    }

    // ── File filter ───────────────────────────────────────────────────────────
    if ($file)
        $sql_extra .= term_query('item', $file, TERM_FILE);

    // ── Direct messages / privacy ─────────────────────────────────────────────
    if ($dm) {
        $sql_extra .= ' AND item.item_private = 2 ';
    } else {
        $sql_extra .= ' AND item.item_private IN (0, 1) ';
    }

    // ── Conversation (mentions) ───────────────────────────────────────────────
    if ($conv) {
        $channel = App::get_channel();
        $item_thread_top = '';
        $sql_extra .= " AND (author_xchan = '" . dbesc($channel['channel_hash']) . "' OR item_mentionsme = 1) ";
    }

    // ── Date range ────────────────────────────────────────────────────────────
    $sql_date = '';
    if ($datequery)
        $sql_date .= " AND item.created <= '" . dbesc(datetime_convert(date_default_timezone_get(), '', $datequery)) . "' ";
    if ($datequery2)
        $sql_date .= " AND item.created >= '" . dbesc(datetime_convert(date_default_timezone_get(), '', $datequery2)) . "' ";

    // ── Affinity ──────────────────────────────────────────────────────────────
    $sql_nets = '';
    if ($cmin !== -1 || $cmax !== -1) {
        $sql_nets .= ' AND ';
        if ($cmax === 99)
            $sql_nets .= ' ( ';
        $sql_nets .= "( abook.abook_closeness >= $cmin AND abook.abook_closeness <= $cmax ) ";
        if ($cmax === 99)
            $sql_nets .= ' OR abook.abook_closeness IS NULL ) ';
    }

    // ── Network filter ────────────────────────────────────────────────────────
    $net_query = $net ? ' left join xchan on xchan_hash = author_xchan ' : '';
    $net_query2 = $net ? " and xchan_network = '" . protect_sprintf(dbesc($net)) . "' " : '';

    // ── Fetch parent ids ──────────────────────────────────────────────────────
    $r = dbq("SELECT item.parent AS item_id FROM item
        left join abook on ( item.owner_xchan = abook.abook_xchan $abook_uids )
        $net_query
        WHERE true $uids $item_thread_top $item_normal
        AND item.mid = item.parent_mid
        and (abook.abook_blocked = 0 or abook.abook_flags is null)
        $sql_extra $sql_options $sql_nets $sql_date
        $net_query2
        ORDER BY $ordering DESC $pager_sql");

    $items = [];
    if ($r) {
        $ids = ids_to_querystr($r, 'item_id');

        $items = dbq("SELECT item.*,
            (SELECT COUNT(*) FROM item r WHERE r.parent = item.parent AND r.thr_parent = item.mid AND r.verb = 'Like' AND r.item_deleted = 0) as like_count,
            (SELECT COUNT(*) FROM item r WHERE r.parent = item.parent AND r.thr_parent = item.mid AND r.verb = 'Dislike' AND r.item_deleted = 0) as dislike_count,
            (SELECT COUNT(*) FROM item r WHERE r.parent = item.parent AND r.thr_parent = item.mid AND r.verb = '" . ACTIVITY_SHARE . "' AND r.item_deleted = 0) as announce_count,
            (SELECT COUNT(*) FROM item r WHERE r.parent = item.id AND r.item_thread_top = 0 AND r.item_deleted = 0) as comment_count,
            (SELECT GROUP_CONCAT(verb, ':', author_xchan SEPARATOR '|')
             FROM item r
             WHERE r.parent = item.parent
             AND r.thr_parent = item.mid
             AND r.verb IN ('Like','Dislike','Announce')
             AND r.item_deleted = 0) as reaction_verbs
            FROM item
            WHERE item.id IN ($ids)
            OR (item.parent IN ($ids)
                AND item.verb IN ('Create', 'Update', 'EmojiReact')
                AND item.obj_type NOT IN ('Answer')
                AND item.item_thread_top = 0
                $item_normal)
            ORDER BY item.created ASC");

        xchan_query($items, true);
        $items = fetch_post_tags($items, true);

        usort($items, function ($a, $b) use ($ordering) {
            if ($a['item_thread_top'] && $b['item_thread_top']) {
                $key = $ordering === 'commented' ? 'commented' : 'created';
                return strtotime($b[$key]) - strtotime($a[$key]);
            }
            return strtotime($a['created']) - strtotime($b['created']);
        });
    }

    $out = [];
    foreach ($items as $item) {
        $out[] = format_item($item, $observer_xchan);
    }

    $arr['replace'] = true;
    json_return_and_die($out);
}

function format_item($item, $observer_xchan = '')
{
    $liked = $disliked = $repeated = false;
    if ($observer_xchan && !empty($item['reaction_verbs'])) {
        foreach (explode('|', $item['reaction_verbs']) as $rv) {
            [$verb, $xchan] = explode(':', $rv, 2);
            if ($xchan !== $observer_xchan)
                continue;
            if ($verb === 'Like')
                $liked = true;
            if ($verb === 'Dislike')
                $disliked = true;
            if ($verb === 'Announce')
                $repeated = true;
        }
    }
    return [
        'uuid' => $item['uuid'],
        'mid' => $item['mid'],
        'parent_mid' => $item['parent_mid'],
        'thr_parent' => $item['thr_parent'],
        'message_top' => intval($item['item_thread_top']) ? $item['mid'] : $item['thr_parent'],
        'created' => $item['created'],
        'edited' => $item['edited'],
        'commented' => $item['commented'],
        'title' => $item['title'],
        'body' => $item['body'],
        'verb' => $item['verb'],
        'obj_type' => $item['obj_type'],
        'like_count' => intval($item['like_count'] ?? 0),
        'dislike_count' => intval($item['dislike_count'] ?? 0),
        'announce_count' => intval($item['announce_count'] ?? 0),
        'comment_count' => intval($item['comment_count'] ?? 0),
        'item_private' => intval($item['item_private']),
        'item_thread_top' => intval($item['item_thread_top']),
        'iid' => intval($item['id']),
        'profile_uid' => intval($item['uid']),
        'flags' => array_values(array_filter([
            intval($item['item_thread_top']) ? 'thread_parent' : null,
            intval($item['item_private']) ? 'private' : null,
            intval($item['item_starred']) ? 'starred' : null,
            intval($item['item_notshown']) ? 'notshown' : null,
        ])),
        'author' => [
            'name' => $item['author']['xchan_name'] ?? '',
            'address' => $item['author']['xchan_addr'] ?? '',
            'url' => $item['author']['xchan_url'] ?? '',
            'photo' => [
                'src' => $item['author']['xchan_photo_m'] ?? '',
                'mimetype' => $item['author']['xchan_photo_mimetype'] ?? '',
            ],
        ],
        'permalink' => $item['plink'] ?? '',
        'viewer_liked' => $liked,
        'viewer_disliked' => $disliked,
        'viewer_repeated' => $repeated,
    ];
}

function json_settings_get(&$arr)
{
    if (($_GET['format'] ?? '') !== 'json')
        return;
    if ((\App::$argv[1] ?? '') !== 'display')
        return;
    if (!local_channel()) {
        json_return_and_die(['error' => 'Permission denied']);
    }
    $default_theme = \Zotlabs\Lib\Config::Get('system', 'theme');
    if (!$default_theme)
        $default_theme = 'redbasic';

    $themespec = explode(':', \App::$channel['channel_theme']);
    $existing_theme = $themespec[0] ?? '';
    $existing_schema = $themespec[1] ?? '';

    $theme = (($existing_theme) ? $existing_theme : $default_theme);
    $allowed_themes_str = \Zotlabs\Lib\Config::Get('system', 'allowed_themes');
    $allowed_themes_raw = explode(',', $allowed_themes_str);
    $allowed_themes = array();
    if (count($allowed_themes_raw))
        foreach ($allowed_themes_raw as $x)
            if (strlen(trim($x)) && is_dir("view/theme/$x"))
                $allowed_themes[] = trim($x);
    $uid = local_channel();
    $settings = [
        'theme' => \App::$channel['channel_theme'] ?? '',
        'thread_allow' => intval(get_pconfig($uid, 'system', 'thread_allow', 1)),
        'update_interval' => intval(get_pconfig($uid, 'system', 'update_interval', 80000)) / 1000,
        'itemspage' => intval(get_pconfig($uid, 'system', 'itemspage', 10)),
        'no_smilies' => intval(get_pconfig($uid, 'system', 'no_smilies', 0)),
        'title_tosource' => intval(get_pconfig($uid, 'system', 'title_tosource', 0)),
        'start_menu' => intval(get_pconfig($uid, 'system', 'start_menu', 0)),
        'user_scalable' => intval(get_pconfig($uid, 'system', 'user_scalable', 0)),
        'theme' => $themespec[0] ?? '',
        'themes' => array_values($allowed_themes),  // build $allowed_themes the same way Display::get() does
    ];

    $arr['content'] = '';
    json_return_and_die($settings);
}

function json_settings_post(&$arr)
{
    if (($_GET['format'] ?? '') !== 'json')
        return;
    if ((\App::$argv[1] ?? '') !== 'display')
        return;

    $uid = local_channel();
    $data = json_decode(file_get_contents('php://input'), true);

    if (!$data) {
        http_response_code(400);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'invalid json body']);
        exit;
    }

    $themespec = explode(':', \App::$channel['channel_theme']);
    $existing_theme = $themespec[0];
    $existing_schema = $themespec[1];

    $theme = ((x($_POST, 'theme')) ? notags(trim($_POST['theme'])) : $existing_theme);

    if (!$theme)
        $theme = 'redbasic';

    if (isset($data['thread_allow']))
        set_pconfig($uid, 'system', 'thread_allow', intval($data['thread_allow']));
    if (isset($data['update_interval']))
        set_pconfig($uid, 'system', 'update_interval', intval($data['update_interval']) * 1000);
    if (isset($data['itemspage'])) {
        $itemspage = max(1, min(30, intval($data['itemspage'])));
        set_pconfig($uid, 'system', 'itemspage', $itemspage);
    }
    if (isset($data['no_smilies']))
        set_pconfig($uid, 'system', 'no_smilies', intval($data['no_smilies']));
    if (isset($data['title_tosource']))
        set_pconfig($uid, 'system', 'title_tosource', intval($data['title_tosource']));
    if (isset($data['start_menu']))
        set_pconfig($uid, 'system', 'start_menu', intval($data['start_menu']));
    if (isset($data['user_scalable']))
        set_pconfig($uid, 'system', 'user_scalable', intval($data['user_scalable']));
    if (isset($data['theme'])) {
        // preserve existing schema if theme unchanged
        $themespec = explode(':', \App::$channel['channel_theme']);
        $newschema = ($themespec[0] === $data['theme']) ? ($themespec[1] ?? '') : '';
        $theme_val = $data['theme'] . ($newschema ? ':' . $newschema : '');
        q("UPDATE channel SET channel_theme = '%s' WHERE channel_id = %d",
            dbesc($theme_val), intval($uid));
        $_SESSION['theme'] = $theme_val;
    }
    header('Content-Type: application/json');
    echo json_encode(['status' => 'ok']);
    exit;
}

function json_channel_get()
{
    if (($_GET['format'] ?? '') !== 'json')
        return;

    require_once ('include/items.php');
    require_once ('include/conversation.php');
    require_once ('include/acl_selectors.php');

    // ── Resolve channel from URL (mirrors Channel::init() fallback) ──────────
    $nick = argv(1);
    if (!$nick) {
        if (!local_channel()) {
            json_return_and_die(['error' => 'Not logged in']);
        }
        $c = App::get_channel();
        $nick = $c['channel_address'] ?? '';
        if (!$nick) {
            json_return_and_die(['error' => 'Channel not specified']);
        }
    }

    $channel = channelx_by_nick($nick, true);
    if (!$channel || $channel['channel_removed']) {
        json_return_and_die(['error' => 'Channel not found']);
    }

    $profile_uid = intval($channel['channel_id']);
    $observer = App::get_observer();
    $observer_xchan = $observer ? $observer['xchan_hash'] : '';

    // ── Permission check ──────────────────────────────────────────────────────
    $perms = get_all_perms($profile_uid, $observer_xchan);
    if (!$perms['view_stream']) {
        json_return_and_die(['error' => 'Permission denied']);
    }

    $permission_sql = item_permissions_sql($profile_uid);
    $item_normal = item_normal();

    // ── Pagination ────────────────────────────────────────────────────────────
    $itemspage = get_pconfig(local_channel(), 'system', 'itemspage') ?: 10;
    $offset = intval($_GET['start'] ?? 0);
    $pager_sql = " LIMIT $itemspage OFFSET $offset ";

    // ── Ordering ──────────────────────────────────────────────────────────────
    $order = $_GET['order'] ?? 'post';
    $ordering = ($order === 'commented') ? 'commented' : 'created';

    // ── Optional filters (subset of network — wall posts only) ────────────────
    $search = $_GET['search'] ?? '';
    $hashtags = $_GET['tag'] ?? '';
    $category = $_GET['cat'] ?? '';
    $datequery = (isset($_GET['dend']) && is_a_date_arg($_GET['dend'])) ? notags($_GET['dend']) : '';
    $datequery2 = (isset($_GET['dbegin']) && is_a_date_arg($_GET['dbegin'])) ? notags($_GET['dbegin']) : '';

    if ($search && str_starts_with($search, '#')) {
        $hashtags = substr($search, 1);
        $search = '';
    }

    $sql_extra = '';

    // ── Single item / thread ──────────────────────────────────────────────────
    $mid = $_GET['mid'] ?? '';
    $identifier = 'uuid';
    if (str_starts_with($mid, 'b64.')) {
        $mid = unpack_link_id($mid);
        $identifier = 'mid';
    }

    // ── Category / hashtag ────────────────────────────────────────────────────
    if ($category)
        $sql_extra .= protect_sprintf(term_query('item', $category, TERM_CATEGORY));
    if ($hashtags)
        $sql_extra .= protect_sprintf(term_query('item', $hashtags, TERM_HASHTAG, TERM_COMMUNITYTAG));

    // ── Full-text search ──────────────────────────────────────────────────────
    if ($search) {
        $sql_extra .= sprintf(
            " AND (item.body LIKE '%s' OR item.title LIKE '%s') ",
            dbesc(protect_sprintf('%' . $search . '%')),
            dbesc(protect_sprintf('%' . $search . '%'))
        );
    }

    // ── Date range ────────────────────────────────────────────────────────────
    if ($datequery)
        $sql_extra .= " AND item.created <= '" . dbesc(datetime_convert(date_default_timezone_get(), '', $datequery)) . "' ";
    if ($datequery2)
        $sql_extra .= " AND item.created >= '" . dbesc(datetime_convert(date_default_timezone_get(), '', $datequery2)) . "' ";

    // ── Fetch parent ids ──────────────────────────────────────────────────────
    if ($mid) {
        // Single thread: find the thread root for this mid
        $r = dbq("SELECT item.parent AS item_id FROM item
            WHERE item.$identifier = '" . dbesc($mid) . "'
            AND item.uid = $profile_uid
            AND item.item_wall = 1
            $item_normal
            $permission_sql
            LIMIT 1");
    } else {
        $r = dbq("SELECT item.parent AS item_id FROM item
            WHERE item.uid = $profile_uid
            AND item.id = item.parent
            AND item.item_wall = 1
            AND item.item_thread_top = 1
            $item_normal
            $permission_sql
            $sql_extra
            ORDER BY $ordering DESC
            $pager_sql");
    }

    $items = [];
    if ($r) {
        $ids = ids_to_querystr($r, 'item_id');

        $items = dbq("SELECT item.*,
            (SELECT COUNT(*) FROM item r WHERE r.parent = item.parent AND r.thr_parent = item.mid AND r.verb = 'Like'    AND r.item_deleted = 0) AS like_count,
            (SELECT COUNT(*) FROM item r WHERE r.parent = item.parent AND r.thr_parent = item.mid AND r.verb = 'Dislike' AND r.item_deleted = 0) AS dislike_count,
            (SELECT COUNT(*) FROM item r WHERE r.parent = item.parent AND r.thr_parent = item.mid AND r.verb = '" . ACTIVITY_SHARE . "' AND r.item_deleted = 0) AS announce_count,
            (SELECT COUNT(*) FROM item r WHERE r.parent = item.id    AND r.item_thread_top = 0    AND r.item_deleted = 0) AS comment_count,
            (SELECT GROUP_CONCAT(verb, ':', author_xchan SEPARATOR '|')
             FROM item r
             WHERE r.parent = item.parent
             AND r.thr_parent = item.mid
             AND r.verb IN ('Like','Dislike','Announce')
             AND r.item_deleted = 0) AS reaction_verbs
            FROM item
            WHERE item.id IN ($ids)
            OR (item.parent IN ($ids)
                AND item.verb IN ('Create', 'Update', 'EmojiReact')
                AND item.obj_type NOT IN ('Answer')
                AND item.item_thread_top = 0
                $item_normal)
            ORDER BY item.created ASC");

        xchan_query($items, true);
        $items = fetch_post_tags($items, true);

        usort($items, function ($a, $b) use ($ordering) {
            if ($a['item_thread_top'] && $b['item_thread_top']) {
                $key = $ordering === 'commented' ? 'commented' : 'created';
                return strtotime($b[$key]) - strtotime($a[$key]);
            }
            return strtotime($a['created']) - strtotime($b['created']);
        });
    }

    $out = [];
    foreach ($items as $item) {
        $out[] = channel_json_format_item($item, $observer_xchan);
    }

    $arr['replace'] = true;
    json_return_and_die($out);
}

function channel_json_format_item($item, $observer_xchan = '')
{
    $liked = $disliked = $repeated = false;
    if ($observer_xchan && !empty($item['reaction_verbs'])) {
        foreach (explode('|', $item['reaction_verbs']) as $rv) {
            [$verb, $xchan] = explode(':', $rv, 2);
            if ($xchan !== $observer_xchan)
                continue;
            if ($verb === 'Like')
                $liked = true;
            if ($verb === 'Dislike')
                $disliked = true;
            if ($verb === 'Announce')
                $repeated = true;
        }
    }

    return [
        'uuid' => $item['uuid'],
        'mid' => $item['mid'],
        'parent_mid' => $item['parent_mid'],
        'thr_parent' => $item['thr_parent'],
        'message_top' => intval($item['item_thread_top']) ? $item['mid'] : $item['thr_parent'],
        'created' => $item['created'],
        'edited' => $item['edited'],
        'commented' => $item['commented'],
        'title' => $item['title'],
        'body' => $item['body'],
        'verb' => $item['verb'],
        'obj_type' => $item['obj_type'],
        'like_count' => intval($item['like_count'] ?? 0),
        'dislike_count' => intval($item['dislike_count'] ?? 0),
        'announce_count' => intval($item['announce_count'] ?? 0),
        'comment_count' => intval($item['comment_count'] ?? 0),
        'item_private' => intval($item['item_private']),
        'item_thread_top' => intval($item['item_thread_top']),
        'iid' => intval($item['id']),
        'profile_uid' => intval($item['uid']),
        'flags' => array_values(array_filter([
            intval($item['item_thread_top']) ? 'thread_parent' : null,
            intval($item['item_private']) ? 'private' : null,
            intval($item['item_starred']) ? 'starred' : null,
            intval($item['item_notshown']) ? 'notshown' : null,
        ])),
        'author' => [
            'name' => $item['author']['xchan_name'] ?? '',
            'address' => $item['author']['xchan_addr'] ?? '',
            'url' => $item['author']['xchan_url'] ?? '',
            'photo' => [
                'src' => $item['author']['xchan_photo_m'] ?? '',
                'mimetype' => $item['author']['xchan_photo_mimetype'] ?? '',
            ],
        ],
        'permalink' => $item['plink'] ?? '',
        'viewer_liked' => $liked,
        'viewer_disliked' => $disliked,
        'viewer_repeated' => $repeated,
    ];
}

function json_photos_get(&$arr)
{
    if (($_GET['format'] ?? '') !== 'json')
        return;

    require_once ('include/bbcode.php');
    require_once ('include/security.php');
    require_once ('include/items.php');
    require_once ('include/conversation.php');

    // /photos with no nick → fall back to the logged-in user's channel,
    // mirroring what Photos::init() does when argc() < 2.
    if (!array_key_exists('channel', \App::$data)) {
        if (!local_channel()) {
            json_return_and_die(['error' => 'Not logged in']);
        }
        \App::$data['channel'] = \App::get_channel();
        if (!\App::$data['channel']) {
            json_return_and_die(['error' => 'Channel not found']);
        }
    }

    $channel = \App::$data['channel'];
    $owner_uid = intval($channel['channel_id']);
    $observer = \App::get_observer();
    $ob_hash = $observer ? $observer['xchan_hash'] : '';

    // ── Permission check ──────────────────────────────────────────────────────
    if (!perm_is_allowed($owner_uid, $ob_hash, 'view_storage')) {
        json_return_and_die(['error' => 'Permission denied']);
    }

    $sql_extra = permissions_sql($owner_uid, $ob_hash, 'photo');
    $sql_attach = permissions_sql($owner_uid, $ob_hash, 'attach');
    $sql_item = item_permissions_sql($owner_uid, $ob_hash);
    $unsafe = (array_key_exists('unsafe', $_REQUEST) && $_REQUEST['unsafe']) ? 1 : 0;

    $ph_drv = photo_factory('');
    $phototypes = $ph_drv->supportedTypes();

    // ── Dispatch on URL shape ─────────────────────────────────────────────────
    // /photos/{nick}                  → argc=2, datatype='summary'
    // /photos/{nick}/album/{hash}     → argc=4, datatype='album'
    // /photos/{nick}/image/{hash}     → argc=4, datatype='image'

    $datatype = (argc() > 2) ? argv(2) : 'summary';
    $datum = (argc() > 3) ? argv(3) : '';

    // ── Pagination ────────────────────────────────────────────────────────────
    $itemspage = 30;
    $offset = intval($_GET['start'] ?? 0);

    // =========================================================================
    // SUMMARY — recent photos
    // =========================================================================
    if ($datatype === 'summary') {
        $r = dbq("SELECT p.resource_id, p.id, p.filename, p.mimetype, p.album,
                         p.imgscale, p.created, p.display_path
                  FROM photo p
                  INNER JOIN (
                      SELECT resource_id, max(imgscale) imgscale
                      FROM photo
                      WHERE photo.uid = $owner_uid
                        AND photo_usage IN (" . PHOTO_NORMAL . ', ' . PHOTO_PROFILE . ")
                        AND is_nsfw = $unsafe
                        $sql_extra
                      GROUP BY resource_id
                  ) ph ON (p.resource_id = ph.resource_id AND p.imgscale = ph.imgscale)
                  ORDER BY p.created DESC
                  LIMIT $itemspage OFFSET $offset");

        $photos = [];
        foreach (($r ?: []) as $row) {
            if (!attach_can_view_folder($owner_uid, $ob_hash, $row['resource_id']))
                continue;
            $ext = $phototypes[$row['mimetype']] ?? 'jpg';
            $photos[] = photos_json_format_photo($row, $ext, $channel['channel_address']);
        }

        $arr['replace'] = true;
        json_return_and_die([
            'type' => 'summary',
            'photos' => $photos,
        ]);
    }

    // =========================================================================
    // ALBUM — photos in a single album
    // =========================================================================
    if ($datatype === 'album') {
        if (!$datum) {
            json_return_and_die(['error' => 'Album not specified']);
        }

        $album_row = photos_album_exists($owner_uid, $ob_hash, $datum);
        if (!$album_row) {
            json_return_and_die(['error' => 'Album not found']);
        }

        $folder_hash = $album_row['hash'];
        $display_path = $album_row['display_path'];

        $order = (isset($_GET['order']) && $_GET['order'] === 'posted') ? 'ASC' : 'DESC';

        $r = dbq("SELECT p.resource_id, p.id, p.filename, p.mimetype,
                         p.imgscale, p.description, p.created
                  FROM photo p
                  INNER JOIN (
                      SELECT resource_id, max(imgscale) imgscale
                      FROM photo
                      LEFT JOIN attach
                          ON folder = '" . dbesc($folder_hash) . "'
                         AND photo.resource_id = attach.hash
                      WHERE attach.uid = $owner_uid
                        AND imgscale <= 4
                        AND photo_usage IN (" . PHOTO_NORMAL . ', ' . PHOTO_PROFILE . ")
                        AND is_nsfw = $unsafe
                        $sql_extra
                      GROUP BY resource_id
                  ) ph ON (p.resource_id = ph.resource_id AND p.imgscale = ph.imgscale)
                  ORDER BY created $order
                  LIMIT $itemspage OFFSET $offset");

        $photos = [];
        foreach (($r ?: []) as $row) {
            $ext = $phototypes[$row['mimetype']] ?? 'jpg';
            $photos[] = photos_json_format_photo($row, $ext, $channel['channel_address']);
        }

        $arr['replace'] = true;
        json_return_and_die([
            'type' => 'album',
            'album_hash' => $datum,
            'album_name' => $display_path,
            'photos' => $photos,
        ]);
    }

    // =========================================================================
    // IMAGE — single photo with comments
    // =========================================================================
    if ($datatype === 'image') {
        if (!$datum) {
            json_return_and_die(['error' => 'Photo not specified']);
        }

        // Verify attach visibility
        $x = dbq("SELECT folder FROM attach
                  WHERE hash = '" . dbesc($datum) . "'
                    AND uid = $owner_uid
                    $sql_attach
                  LIMIT 1");

        $ph = dbq("SELECT id, aid, uid, xchan, resource_id, created, edited,
                          title, description, album, filename, mimetype,
                          height, width, filesize, imgscale, photo_usage,
                          is_nsfw, allow_cid, allow_gid, deny_cid, deny_gid
                   FROM photo
                   WHERE uid = $owner_uid
                     AND resource_id = '" . dbesc($datum) . "'
                     $sql_extra
                   ORDER BY imgscale ASC");

        if (!$ph || !$x) {
            json_return_and_die(['error' => 'Photo not found or permission denied']);
        }

        $ext = $phototypes[$ph[0]['mimetype']] ?? 'jpg';
        $hires = $ph[0];
        $lores = isset($ph[1]) ? $ph[1] : $ph[0];

        $is_private = (strlen($ph[0]['allow_cid']) ||
            strlen($ph[0]['allow_gid']) ||
            strlen($ph[0]['deny_cid']) ||
            strlen($ph[0]['deny_gid']));

        // ── Linked item (for reactions + comments) ────────────────────────────
        $linked_items = dbq("SELECT * FROM item
                             WHERE resource_id = '" . dbesc($datum) . "'
                               AND resource_type = 'photo'
                               $sql_item
                             LIMIT 1");

        $link_item = null;
        $comments = [];
        $like_count = 0;
        $dislike_count = 0;
        $viewer_liked = false;
        $viewer_disliked = false;

        if ($linked_items) {
            xchan_query($linked_items);
            $linked_items = fetch_post_tags($linked_items, true);
            $link_item = $linked_items[0];
            $item_normal = item_normal();

            // Reaction counts + viewer state
            $reactions = dbq("SELECT verb, author_xchan FROM item
                              WHERE parent_mid = '" . dbesc($link_item['mid']) . "'
                                AND verb IN ('Like','Dislike')
                                AND item_deleted = 0
                                $item_normal
                                AND uid = $owner_uid");

            foreach (($reactions ?: []) as $react) {
                if ($react['verb'] === 'Like')
                    $like_count++;
                if ($react['verb'] === 'Dislike')
                    $dislike_count++;
                if ($ob_hash && $react['author_xchan'] === $ob_hash) {
                    if ($react['verb'] === 'Like')
                        $viewer_liked = true;
                    if ($react['verb'] === 'Dislike')
                        $viewer_disliked = true;
                }
            }

            // Comments
            $comment_rows = dbq("SELECT * FROM item
                                 WHERE parent_mid = '" . dbesc($link_item['mid']) . "'
                                   AND verb NOT IN ('Like','Dislike')
                                   $item_normal
                                   AND uid = $owner_uid
                                   $sql_item
                                 ORDER BY created ASC");

            if ($comment_rows) {
                xchan_query($comment_rows);
                $comment_rows = fetch_post_tags($comment_rows, true);
                foreach ($comment_rows as $c) {
                    $comments[] = [
                        'id' => intval($c['id']),
                        'mid' => $c['mid'],
                        'iid' => intval($c['id']),
                        'body' => $c['body'],
                        'created' => $c['created'],
                        'author' => [
                            'name' => $c['author']['xchan_name'] ?? '',
                            'url' => $c['author']['xchan_url'] ?? '',
                            'photo' => $c['author']['xchan_photo_m'] ?? '',
                        ],
                    ];
                }
            }
        }

        // ── Prev / next within same album ─────────────────────────────────────
        $prevlink = null;
        $nextlink = null;

        $order_dir = (isset($_GET['order']) && $_GET['order'] === 'posted') ? 'ASC' : 'DESC';
        $siblings = dbq("SELECT hash FROM attach
                          WHERE folder = '" . dbesc($x[0]['folder']) . "'
                            AND uid = $owner_uid
                            AND is_photo = 1
                            $sql_attach
                          ORDER BY created $order_dir");

        if ($siblings) {
            $hashes = array_column($siblings, 'hash');
            $pos = array_search($datum, $hashes);
            if ($pos !== false) {
                $prv = ($pos - 1 + count($hashes)) % count($hashes);
                $nxt = ($pos + 1) % count($hashes);
                $base = z_root() . '/photos/' . $channel['channel_address'] . '/image/';
                $prevlink = $base . $hashes[$prv];
                $nextlink = $base . $hashes[$nxt];
            }
        }

        $arr['replace'] = true;
        json_return_and_die([
            'type' => 'image',
            'resource_id' => $ph[0]['resource_id'],
            'filename' => $ph[0]['filename'],
            'description' => $ph[0]['description'],
            'album' => $ph[0]['album'],
            'album_link' => z_root() . '/photos/' . $channel['channel_address'] . '/album/' . $x[0]['folder'],
            'created' => $ph[0]['created'],
            'width' => intval($ph[0]['width']),
            'height' => intval($ph[0]['height']),
            'is_nsfw' => intval($ph[0]['is_nsfw']),
            'is_private' => intval($is_private),
            'src' => z_root() . '/photo/' . $lores['resource_id'] . '-' . $lores['imgscale'] . '.' . $ext,
            'src_full' => z_root() . '/photo/' . $hires['resource_id'] . '-' . $hires['imgscale'] . '.' . $ext,
            'prevlink' => $prevlink,
            'nextlink' => $nextlink,
            'like_count' => $like_count,
            'dislike_count' => $dislike_count,
            'viewer_liked' => $viewer_liked,
            'viewer_disliked' => $viewer_disliked,
            'item_id' => $link_item ? intval($link_item['id']) : null,
            'item_mid' => $link_item ? $link_item['mid'] : null,
            'comments' => $comments,
        ]);
    }

    // Unknown datatype
    json_return_and_die(['error' => 'Unknown datatype: ' . $datatype]);
}

function photos_json_format_photo($row, $ext, $channel_address)
{
    return [
        'id' => intval($row['id']),
        'resource_id' => $row['resource_id'],
        'filename' => $row['filename'],
        'description' => $row['description'] ?? '',
        'album' => $row['album'] ?? '',
        'mimetype' => $row['mimetype'],
        'imgscale' => intval($row['imgscale']),
        'created' => $row['created'],
        'src' => z_root() . '/photo/' . $row['resource_id'] . '-' . $row['imgscale'] . '.' . $ext,
        'link' => z_root() . '/photos/' . $channel_address . '/image/' . $row['resource_id'],
    ];
}

function json_pconfig_get(&$data)
{
    if (($_GET['format'] ?? '') !== 'json') {
        return;
    }

    $is_local = false;
    $nick = null;
    $data = [];

    // Case 1: local logged-in channel
    if (local_channel()) {
        $channel = \App::get_channel();

        if ($channel) {
            $nick = $channel['channel_address'];  // already clean
            $is_local = true;
        }

        $r = q('select * from pconfig where uid = ' . local_channel());

        foreach ($r as $rr) {
            $data[$rr['cat']][$rr['k']] = $rr['v'];
        }
    }

    // Case 2: visitor (observer / remote)
    if (!$nick) {
        $observer = \App::get_observer();

        if ($observer && isset($observer['xchan_addr'])) {
            // john@hub.com → john
            $nick = strstr($observer['xchan_addr'], '@', true) ?: $observer['xchan_addr'];
        }
    }

    // fallback (optional)
    if (!$nick) {
        $nick = '';
    }

    $data['channel_nick'] = $nick;
    $data['is_local'] = $is_local;

    json_return_and_die($data);
}

function json_pconfig_post(&$data)
{
    // $data contains:
    // [uid, cat, k, v]

    $uid = $data['uid'];
    $cat = $data['cat'];
    $key = $data['k'];
    $val = $data['v'];

    // Example: log or modify
    if ($cat === 'photos' && $key === 'some_setting') {
        // modify value before save
        $data['v'] = strtoupper($val);

        logger("Modified pconfig before save: $val → " . $data['v']);
    }

    // Example: block something
    if ($key === 'forbidden_key') {
        notice('This key is not allowed.');
        $data['v'] = '';  // or unset
    }
}
