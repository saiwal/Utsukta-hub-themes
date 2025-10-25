<?php

/**
 * Hero Widget
 * Displays featured posts in a slider format
 *
 * @param array $args
 * @return string
 */
function widget_hero($args)
{
    // Minimal styles if viewing a specific post
    if (argc() >= 2 && argv(0) === 'channel' && isset($_GET['mid']) && $_GET['mid']) {
        return <<<EOT
            <style>
            .ss-home .s-header__branding a { color: black; }
            .ss-home .s-header__nav-wrap { margin-left: 0%; }
            </style>
            EOT;
    }
    if (!App::$profile['profile_uid']) {
        return '';
    }

    if (!perm_is_allowed(App::$profile['profile_uid'], get_observer_hash(), 'view_stream')) {
        return '';
    }

    $cat = x($_REQUEST, 'cat') ? notags(trim($_REQUEST['cat'])) : '';
    $tag = x($_REQUEST, 'tag') ? notags(trim($_REQUEST['tag'])) : '';

    // Render category/tag header if present
    if ($cat || $tag) {
        $label_type = $cat ? t('Category:') : t('Tag:');
        $label_value = $cat ? htmlspecialchars($cat) : htmlspecialchars($tag);
        return <<<EOT
            <div class="s-pageheader">
              <div class="row">
                <div class="column large-12">
                  <h1 class="page-title">
                    <span class="page-title__small-type">$label_type</span>
                    $label_value
                  </h1>
                </div>
              </div>
            </div>
            <style>
            .s-pageheader {  padding-top: calc(6.5 * var(--space)); }
            .s-content--page {  padding-top: 0px;}
            </style>
            EOT;
    }
    // Default arguments
    $defaults = [
        'count' => 8,
        'category' => 'featured',  // Default to featured category
        'hashtags' => '',
        'title' => t('Featured Posts'),
        'show_categories' => true,
        'show_excerpt' => true
    ];

    $args = array_merge($defaults, $args);

    $uid = App::$profile['profile_uid'];

    // Get posts
    $items = widget_hero_get_items($uid, $args);
    if (!$items) {
        return <<<EOT
            <style>
            .ss-home .s-header__branding a { color: black; }
            .ss-home .s-header__nav-wrap { margin-left: 0%; }
            </style>
            EOT;
    }

    // Prepare template variables
    $tpl_vars = [
        '$items' => $items,
        '$swiper_id' => 'hero-swiper-' . mt_rand(1000, 9999),
        '$title' => $args['title'],
        '$read_more' => t('Read More'),
        '$scroll_text' => t('Scroll')
    ];

    $tpl = get_markup_template('hero_widget.tpl');
    return replace_macros($tpl, $tpl_vars);
}

/**
 * Get items for hero widget
 */
function widget_hero_get_items($uid, $args = [])
{
    $observer = App::get_observer();
    $ob_hash = $observer['xchan_hash'] ?? '';

    logger('hero widget: starting with uid=' . $uid . ' args=' . print_r($args, true));

    // Normal items (exclude deleted, pending remove, blocked)
    $item_normal = item_normal();

    // Permissions SQL respects viewer
    $permission_sql = item_permissions_sql($uid, $ob_hash);

    // Extra SQL filters
    $sql_extra = '';

    // Category filter
    if (!empty($args['category'])) {
        $sql_extra .= ' ' . protect_sprintf(term_item_parent_query($uid, 'item', $args['category'], TERM_CATEGORY));
        logger('hero widget: category filter added: ' . $args['category']);
    }

    // Hashtag filter
    if (!empty($args['hashtags'])) {
        $sql_extra .= ' ' . protect_sprintf(term_item_parent_query($uid, 'item', $args['hashtags'], TERM_HASHTAG, TERM_COMMUNITYTAG));
        logger('hero widget: hashtag filter added: ' . $args['hashtags']);
    }

    // Limit number of posts
    $limit = intval($args['count'] ?? 10);

    // Fetch top-level items
    $sql = "SELECT item.parent AS item_id, item.created 
            FROM item 
            WHERE item.uid = %d
              AND item.id = item.parent
              AND item.item_wall = 1
              $item_normal
              $permission_sql
              $sql_extra
            ORDER BY item.created DESC
            LIMIT $limit";

    logger('hero widget: executing query: ' . $sql);

    $r = q($sql, intval($uid));

    if (!$r) {
        logger('hero widget: no items found');
        return [];
    }

    logger('hero widget: found ' . count($r) . ' parent items');

    // Fetch full item data with permissions considered
    $items = items_by_parent_ids($r, null, $permission_sql, false, true);
    // Resolve authors and tags
    xchan_query($items);
    $items = fetch_post_tags($items, true);

    usort($items, function ($a, $b) {
        return strtotime($b['created']) <=> strtotime($a['created']);
    });
    logger('hero widget: returning ' . count($items) . ' formatted items');

    return widget_hero_format_items($items, $args);
}

/**
 * Format items for display
 */
function widget_hero_format_items($items, $args)
{
    $formatted = [];

    foreach ($items as $item) {
        // Skip empty posts
        if (empty($item['body']) && empty($item['title'])) {
            continue;
        }

        // Render full post body (Markdown or BBCode)
        $rendered_body = prepare_text($item['body'], $item['mime_type']);
        // If title is empty, create from rendered body

        $title = trim($item['title'] ?? '');

        if (empty($title)) {
            // Create a title from body if none exists
            $body_text = bbcode($item['body'], ['drop_media' => true]);
            $body_text = strip_tags($body_text);
            $title = trim($body_text);
        }

        // Always trim title to 45 characters max
        if (mb_strlen($title) > 45) {
            $title = mb_substr($title, 0, 45) . '…';
        }
        $entry = [
            'id' => $item['id'],
            'title' => $title,
            'body' => $rendered_body,
            'excerpt' => $args['show_excerpt']
                ? widget_hero_create_excerpt($rendered_body, 200)
                : '',
            'link' => $item['plink']
                ?: z_root() . '/channel/' . App::$profile['channel_address'] . '?mid=' . $item['mid'],
            'created' => $item['created'],
            'created_relative' => relative_date($item['created']),
            'author' => $item['author']['xchan_name'] ?? '',
            'categories' => $args['show_categories']
                ? widget_hero_get_categories($item)
                : [],
            'image' => widget_hero_get_image($item)
        ];

        $formatted[] = $entry;
    }

    return $formatted;
}

/**
 * Create excerpt from post body
 */
function widget_hero_create_excerpt($text, $length = 200)
{
    if (empty($text))
        return '';

    $text = bbcode($text, ['drop_media' => true]);
    $text = strip_tags($text);
    $text = str_replace(["\n", "\r", '<br>', '<br/>', '<br />'], ' ', $text);
    $text = preg_replace('/\s+/', ' ', $text);
    $text = trim($text);

    if (mb_strlen($text) > $length) {
        $text = mb_substr($text, 0, $length) . '...';
    }

    return $text;
}

/**
 * Get categories from item
 */
function widget_hero_get_categories($item)
{
    $categories = [];

    if (!empty($item['term']) && is_array($item['term'])) {
        foreach ($item['term'] as $term) {
            if ($term['ttype'] == TERM_CATEGORY) {
                $categories[] = [
                    'name' => $term['term'],
                    'link' => z_root() . '/channel/' . App::$profile['channel_address'] . '?cat=' . urlencode($term['term'])
                ];
            }
        }
    }

    return $categories;
}

/**
 * Get hero image from item
 */
function widget_hero_get_image($item)
{
    $mid = $item['mid'];

    if (preg_match('#/item/([a-f0-9\-]+)$#', $mid, $matches)) {
        $mid = $matches[1];
    }

    $plink = z_root() . '/item/' . $mid;
    $title = htmlspecialchars($item['title'] ?: '(No title)', ENT_QUOTES, 'UTF-8');
    $desc = htmlspecialchars(trim(substr(strip_tags(bbcode($item['body'])), 0, 200)), ENT_QUOTES, 'UTF-8');

    // Extract image
    $img = '';
    if (preg_match_all('/\[zmg=(https?:\/\/[^\]]+)\]/i', $item['body'], $matches)) {
        $img = $matches[1][0];
    }
    if (!$img && preg_match('/(https?:\/\/[^\s"\'<>]+\.(?:jpg|jpeg|png|gif))(?:\?[^\s"\'<>]*)?/i', $item['body'], $m)) {
        $img = $m[1];
    }
    if (!$img)
        $img = z_root() . '/images/default_featured.jpg';
    $img = htmlspecialchars($img, ENT_QUOTES, 'UTF-8');
    if ($img) {
        return $img;
    } else {
        return z_root() . '/images/placeholder-hero.jpg';
    }
}
