<?php

function spurgeon_article_layout(&$arr) {
    if (empty(App::$channel) || !is_array(App::$channel)) {
        return;
    }
    $current_theme = App::$channel['channel_theme'] ?? null;
    if (!str_starts_with((string)$current_theme, 'spurgeon')) {
        return;
    }
    // Check mode from $arr['output']
    $mode = $arr['output']['mode'] ?? null;

    if ($mode === 'hq') {
        // Only template *filename*
        $arr['output']['template'] = 'conv_display.tpl';
    }
    else {
        $arr['output']['template'] = 'conv_item.tpl';
    }
}
