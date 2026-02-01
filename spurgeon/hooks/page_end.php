
<?php

function spurgeon_test_page_end(&$o) {
    logger('spurgeon_test_page_end fired', LOGGER_DEBUG);
    $o .= "<div style='padding:10px;background:#eee;border:1px solid #ccc;'>Test hook output from Spurgeon theme</div>";
}
