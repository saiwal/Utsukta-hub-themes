<?php 

function json_network_content(&$arr) {
    if (($_GET['format'] ?? '') !== 'json') return;

    require_once('include/items.php');
    require_once('include/conversation.php');

    $item_normal = item_normal();
    $abook_uids  = ' and abook.abook_channel = ' . local_channel() . ' ';
    $uids        = ' and item.uid = ' . local_channel() . ' ';
    $ordering    = 'created';
    $itemspage   = intval($_GET['records'] ?? 10);
    $offset      = intval($_GET['start']   ?? 0);
    $pager_sql   = " LIMIT $itemspage OFFSET $offset ";

    $r = dbq("SELECT item.parent AS item_id FROM item
        left join abook on ( item.owner_xchan = abook.abook_xchan $abook_uids )
        WHERE true $uids AND item_thread_top = 1 $item_normal
        AND item.mid = item.parent_mid
        and (abook.abook_blocked = 0 or abook.abook_flags is null)
        AND item.item_private IN (0, 1)
        ORDER BY $ordering DESC $pager_sql"
    );

    $items = [];
    if ($r) {
        $items = items_by_parent_ids($r);
        xchan_query($items, true);
        $items = fetch_post_tags($items, true);
        $items = conv_sort($items, $ordering);
    }

    $out = [];
    foreach ($items as $item) {
        $out[] = [
            'uuid'            => $item['uuid'],
            'mid'             => $item['mid'],
            'parent_mid'      => $item['parent_mid'],
            'thr_parent'      => $item['thr_parent'],
            'message_top'     => $item['parent_mid'],
            'created'         => $item['created'],
            'edited'          => $item['edited'],
            'commented'       => $item['commented'],
            'title'           => $item['title'],
            'body'            => $item['body'],
            'verb'            => $item['verb'],
            'obj_type'        => $item['obj_type'],
            'like_count'      => intval($item['like_count']      ?? 0),
            'dislike_count'   => intval($item['dislike_count']   ?? 0),
            'announce_count'  => intval($item['announce_count']  ?? 0),
            'comment_count'   => intval($item['comment_count']   ?? 0),
            'item_private'    => intval($item['item_private']),
            'item_thread_top' => intval($item['item_thread_top']),
            'flags'           => array_values(array_filter([
                intval($item['item_thread_top']) ? 'thread_parent' : null,
                intval($item['item_private'])    ? 'private'       : null,
                intval($item['item_starred'])    ? 'starred'       : null,
                intval($item['item_notshown'])   ? 'notshown'      : null,
            ])),
            'author' => [
                'name'    => $item['author']['xchan_name']          ?? '',
                'address' => $item['author']['xchan_addr']          ?? '',
                'url'     => $item['author']['xchan_url']           ?? '',
                'photo'   => [
                    'src'      => $item['author']['xchan_photo_m']        ?? '',
                    'mimetype' => $item['author']['xchan_photo_mimetype'] ?? '',
                ],
            ],
            'permalink' => $item['plink'] ?? '',
        ];
    }

    $arr['replace'] = true;
    json_return_and_die($out);
}

function json_settings_get(&$arr) {
		if (($_GET['format'] ?? '') !== 'json') return;
		if ((\App::$argv[1] ?? '') !== 'display') return;

    $settings = [
        'theme'          => \App::$channel['channel_theme'] ?? '',
        'thread_allow'   => intval(get_pconfig($uid, 'system', 'thread_allow', 1)),
        'update_interval'=> intval(get_pconfig($uid, 'system', 'update_interval', 80000)) / 1000,
        'itemspage'      => intval(get_pconfig($uid, 'system', 'itemspage', 10)),
        'no_smilies'     => intval(get_pconfig($uid, 'system', 'no_smilies', 0)),
        'title_tosource' => intval(get_pconfig($uid, 'system', 'title_tosource', 0)),
        'start_menu'     => intval(get_pconfig($uid, 'system', 'start_menu', 0)),
        'user_scalable'  => intval(get_pconfig($uid, 'system', 'user_scalable', 0)),
    ];

    $arr['content'] = '';
    json_return_and_die($settings);
}

function json_settings_post(&$arr) {
    if (($_SERVER['HTTP_ACCEPT'] ?? '') !== 'application/json' &&
        ($_GET['format'] ?? '') !== 'json') return;

    $uid = local_channel();
    $data = json_decode(file_get_contents('php://input'), true);

    if (!$data) {
        http_status(400);
        json_return_and_die(['error' => 'invalid json body']);
    }

    if (isset($data['thread_allow']))
        set_pconfig($uid, 'system', 'thread_allow', intval($data['thread_allow']));

    if (isset($data['update_interval']))
        set_pconfig($uid, 'system', 'update_interval', intval($data['update_interval']) * 1000);

    if (isset($data['itemspage'])) {
        $itemspage = intval($data['itemspage']);
        if ($itemspage > 30) $itemspage = 30;
        if ($itemspage < 1) $itemspage = 1;
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

    json_return_and_die(['status' => 'ok']);
}
