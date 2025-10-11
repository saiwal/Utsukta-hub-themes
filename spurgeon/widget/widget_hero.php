<?php

/**
 * Hero Widget
 * Displays featured posts in a slider format
 * 
 * @param array $args
 * @return string
 */
function widget_hero($args) {
    
    if (!App::$profile['profile_uid']) {
        return '';
    }

    if (!perm_is_allowed(App::$profile['profile_uid'], get_observer_hash(), 'view_stream')) {
        return '';
    }

    // Default arguments
    $defaults = [
        'count' => 3,
        'category' => 'featured',
        'hashtags' => '',
        'title' => t('Featured Posts'),
        'show_categories' => true,
        'show_excerpt' => true
    ];

    $args = array_merge($defaults, $args);

    $uid = App::$profile['profile_uid'];
    
    // Get posts
    $items = widget_hero_get_items($uid, $args);
    
    if (empty($items)) {
        return '';
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
function widget_hero_get_items($uid, $args) {
    
    $item_normal = item_normal();
    $permission_sql = item_permissions_sql($uid);
    
    $sql_extra = '';
    
    // Add category filter
    if (!empty($args['category'])) {
        $sql_extra .= protect_sprintf(term_item_parent_query($uid, 'item', $args['category'], TERM_CATEGORY));
    }
    
    // Add hashtag filter
    if (!empty($args['hashtags'])) {
        $sql_extra .= protect_sprintf(term_item_parent_query($uid, 'item', $args['hashtags'], TERM_HASHTAG, TERM_COMMUNITYTAG));
    }

    // Get only top-level posts (where id = parent) with proper filtering
    $r = q("SELECT item.id, item.mid, item.parent, item.created, item.title, item.body, item.author_xchan, item.owner_xchan, item.plink
            FROM item 
            WHERE item.uid = %d 
            AND item.id = item.parent  // Only top-level posts
            AND item.item_wall = 1 
            $item_normal 
            $permission_sql 
            $sql_extra 
            ORDER BY item.created DESC 
            LIMIT %d",
            intval($uid),
            intval($args['count'])
    );

    if (!$r) {
        return [];
    }

    // We already have the full items, no need for items_by_parent_ids
    $items = $r;
    
    // Enrich the items with additional data
    xchan_query($items);
    $items = fetch_post_tags($items, true);
    
    return widget_hero_format_items($items, $args);
}

/**
 * Format items for display
 */
function widget_hero_format_items($items, $args) {
    $formatted = [];
    
    foreach ($items as $item) {
        // Skip if we don't have basic content
        if (empty($item['body']) && empty($item['title'])) {
            continue;
        }
        
        $title = $item['title'];
        if (empty($title)) {
            // Create title from body
            $body_text = bbcode($item['body'], ['drop_media' => true]);
            $body_text = strip_tags($body_text);
            if (mb_strlen($body_text) > 80) {
                $title = mb_substr($body_text, 0, 80) . '...';
            } else {
                $title = $body_text;
            }
        }
        
        $entry = [
            'id' => $item['id'],
            'title' => $title,
            'body' => $item['body'],
            'excerpt' => $args['show_excerpt'] ? widget_hero_create_excerpt($item['body'], 200) : '',
            'link' => $item['plink'] ?: z_root() . '/channel/' . App::$profile['channel_address'] . '?mid=' . $item['mid'],
            'created' => $item['created'],
            'created_relative' => relative_date($item['created']),
            'author' => $item['author']['xchan_name'] ?? '',
            'categories' => $args['show_categories'] ? widget_hero_get_categories($item) : [],
            'image' => widget_hero_get_image($item)
        ];
        
        $formatted[] = $entry;
    }
    
    return $formatted;
}

/**
 * Create excerpt from post body
 */
function widget_hero_create_excerpt($text, $length = 200) {
    if (empty($text)) return '';
    
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
function widget_hero_get_categories($item) {
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
function widget_hero_get_image($item) {
    // Check for attached images
    if (!empty($item['attach']) && is_array($item['attach'])) {
        foreach ($item['attach'] as $attachment) {
            if (strpos($attachment['filetype'], 'image/') === 0) {
                return z_root() . '/photo/' . $attachment['resource_id'] . '-0';
            }
        }
    }
    
    // Check for embedded images in body
    if (preg_match('/<img[^>]+src=["\']([^"\']+\.(jpg|jpeg|png|gif|webp))["\']/i', $item['body'], $matches)) {
        return $matches[1];
    }
    
    // Check for [img] BBCode
    if (preg_match('/\[img\]([^\[]+\.(jpg|jpeg|png|gif|webp))\[\/img\]/i', $item['body'], $matches)) {
        return $matches[1];
    }
    
    // Default placeholder image
    return z_root() . '/images/placeholder-hero.jpg';
}
