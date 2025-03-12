<?php

if(!App::$install) {

	// Get the UID of the channel owner
	$uid = get_theme_uid();

	if($uid) {
		// Load the owners pconfig
		load_pconfig($uid, 'redbasic');

		$custom_bs = get_pconfig($uid, 'redbasic', 'bootstrap');
		$nav_bg = get_pconfig($uid, 'redbasic', 'nav_bg');
		$nav_bg_dark = get_pconfig($uid, 'redbasic', 'nav_bg_dark');
		$narrow_navbar = get_pconfig($uid,'redbasic','narrow_navbar');
		$bgcolor = get_pconfig($uid, 'redbasic', 'background_color');
		$bgcolor_dark = get_pconfig($uid, 'redbasic', 'background_color_dark');
		$schema = get_pconfig($uid,'redbasic','schema');
		$background_image = get_pconfig($uid, 'redbasic', 'background_image');
		$background_image_dark = get_pconfig($uid, 'redbasic', 'background_image_dark');
		$font_size = get_pconfig($uid, 'redbasic', 'font_size');
		$converse_width = get_pconfig($uid,'redbasic','converse_width');
		$top_photo = get_pconfig($uid,'redbasic','top_photo');
		$reply_photo = get_pconfig($uid,'redbasic','reply_photo');
	}
}



// ! If you change the name of the directory containing the theme, be sure to change this line to match.
echo @file_get_contents('/view/theme/adminlte/css/style.css');
