<?php

function spurgeon_article_layout(&$arr) {

    // Only run when the active theme is spurgeon
    $current_theme = App::$channel['channel_theme'];
    if ($current_theme !== 'spurgeon') {
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
