<?php

function adminlte_tours(&$b) {
    $uid = local_channel();
    if (!$uid) return;

    logger('hook registered', LOGGER_DEBUG);
    // Only run when the active theme is adminlte
    $current_theme = App::$channel['channel_theme'];
    if ($current_theme !== 'adminlte') {
        return;
    }

    $current_page = App::$module; // e.g., 'hq', 'network', 'connections'
    $done = get_pconfig($uid, 'adminlte', 'tour_'. $current_page);
    if ($done === '1') {
      return;  // tour already done
    }

    head_add_css('/view/theme/adminlte/tours/css/shepherd.min.css');
    head_add_css('/view/theme/adminlte/tours/css/tour.css');

    $lang = App::$language ?: 'en';

    $b .= "<script>const currentHubzillaPage = '$current_page'; const hubzillaLang = '$lang';</script>";
    head_add_js('/view/theme/adminlte/tours/js/shepherd.js');
    head_add_js('/view/theme/adminlte/tours/js/tour.js');
}
