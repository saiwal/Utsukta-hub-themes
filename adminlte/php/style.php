<?php

if(!App::$install) {

	// Get the UID of the channel owner
	$uid = get_theme_uid();

	if($uid) {
		// Load the owners pconfig
		load_pconfig($uid, 'adminlte');

		$custom_bs = get_pconfig($uid, 'adminlte', 'bootstrap');
		$nav_bg = get_pconfig($uid, 'adminlte', 'nav_bg');
		$nav_bg_dark = get_pconfig($uid, 'adminlte', 'nav_bg_dark');
		$narrow_navbar = get_pconfig($uid,'adminlte','narrow_navbar');
		$bgcolor = get_pconfig($uid, 'adminlte', 'background_color');
		$bgcolor_dark = get_pconfig($uid, 'adminlte', 'background_color_dark');
		$schema = get_pconfig($uid,'adminlte','schema');
		$background_image = get_pconfig($uid, 'adminlte', 'background_image');
		$background_image_dark = get_pconfig($uid, 'adminlte', 'background_image_dark');
		$font_size = get_pconfig($uid, 'adminlte', 'font_size');
		$converse_width = get_pconfig($uid,'adminlte','converse_width');
		$top_photo = get_pconfig($uid,'adminlte','top_photo');
		$reply_photo = get_pconfig($uid,'adminlte','reply_photo');
	}
}

$site_bs_path = 'view/theme/adminlte/css/bootstrap.min.css';

// Apply the settings

$x = file_get_contents('view/theme/adminlte/css/style.css');

if(file_exists('view/theme/adminlte/schema/' . $schema . '.min.css')) {
	$schemecss = file_get_contents('view/theme/adminlte/schema/' . $schema . '.min.css');
}

if($schemecss) {
  $x .= $schemecss;
}

/*$left_aside_width = 21; //unit: rem*/
/*$right_aside_width = 21; //unit: rem*/

/*$main_width = $left_aside_width + $right_aside_width + intval($converse_width);*/

// prevent main_width smaller than 768px
/*$main_width = (($main_width < 30) ? 30 : $main_width);*/

$options = array (
  '$nav_bg' => $nav_bg,
  '$nav_bg_dark' => $nav_bg_dark,
  '$bgcolor' => $bgcolor,
  '$bgcolor_dark' => $bgcolor_dark,
  '$background_image' => $background_image,
  '$background_image_dark' => $background_image_dark,
  '$font_size' => $font_size,
  '$converse_width' => $converse_width,
  '$top_photo' => $top_photo,
  '$reply_photo' => $reply_photo,
  '$main_width' => $main_width,
  '$left_aside_width' => $left_aside_width,
  '$right_aside_width' => $right_aside_width
);


/*$o = strtr($x, $options);*/

header('Cache-Control: max-age=2592000');

echo $x;

// ! If you change the name of the directory containing the theme, be sure to change this line to match.
/*echo @file_get_contents('/view/theme/adminlte/css/style.css');*/
