<?php

if(!App::$install) {

	// Get the UID of the channel owner
	$uid = get_theme_uid();

	if($uid) {
		// Load the owners pconfig
		load_pconfig($uid, 'adminlte');

		/*$custom_bs = get_pconfig($uid, 'adminlte', 'bootstrap');*/
		/*$nav_bg = get_pconfig($uid, 'adminlte', 'nav_bg');*/
		/*$nav_bg_dark = get_pconfig($uid, 'adminlte', 'nav_bg_dark');*/
    /*$narrow_navbar = get_pconfig($uid,'adminlte','narrow_navbar');*/
    $bgcolor = get_pconfig($uid, 'redbasic', 'background_color');
		$bgcolor_dark = get_pconfig($uid, 'redbasic', 'background_color_dark');
		$bgcolor = get_pconfig($uid, 'adminlte', 'background_color');
		$bgcolor_dark = get_pconfig($uid, 'adminlte', 'background_color_dark');
    $schema = get_pconfig($uid, 'adminlte', 'schema');
    /*var_dump(App::$config[$uid]['adminlte']); // Check the 'adminlte' family*/
    /*var_dump(App::$config[$uid]['adminlte']['schema']); // Check the 'schema' key    */
		/*  $schema = 'journal';*/
		$background_image = get_pconfig($uid, 'adminlte', 'background_image');
		$background_image_dark = get_pconfig($uid, 'adminlte', 'background_image_dark');
		$bg_mode = get_pconfig($uid, 'adminlte', 'bg_mode');
		$dark_mode = get_pconfig($uid, 'adminlte', 'dark_mode');
		/*$converse_width = get_pconfig($uid,'adminlte','converse_width');*/
		/*$top_photo = get_pconfig($uid,'adminlte','top_photo');*/
		/*  $reply_photo = get_pconfig($uid,'adminlte','reply_photo');*/
	}
}


# set some defaults 
$bgcolor = $bgcolor ?: 'var(--bs-body-bg)';
$bgcolor_dark = $bgcolor_dark ?: 'var(--bs-body-bg)';
$background_image = $background_image ?: '';
$background_image_dark = $background_image_dark ?: '';
$dark_mode = 'dark';
$sidebar_mode = 0;
$bg_mode = ($bg_mode == 1) ? 'cover' : '';
$options = array (
  '$bgcolor' => $bgcolor,
  '$bgcolor_dark' => $bgcolor_dark,
  '$background_image' => $background_image,
  '$background_image_dark' => $background_image_dark,
  '$bg_mode' => $bg_mode,
  '$sidebar_mode' => $sidebar_mode,
  '$dark_mode' => $dark_mode,
);
/**/
// Allow layouts to over-ride the schema
if (isset($_REQUEST['schema']) && preg_match('/^[\w_-]+$/i', $_REQUEST['schema'])) {
  $schema = $_REQUEST['schema'];
}
// Apply the settings

$x = file_get_contents('view/theme/adminlte/css/style.css');

$x = strtr($x, $options);

$schemecss = file_get_contents('view/theme/adminlte/schema/' . $schema . '.css');

/*$o = strtr($x, $options);*/

header('Cache-Control: max-age=2592000');

echo $schemecss . $x;

// ! If you change the name of the directory containing the theme, be sure to change this line to match.
/*echo @file_get_contents('/view/theme/adminlte/css/style.css');*/

if(local_channel() && App::$channel && App::$channel['channel_theme'] != 'adminlte')
	set_pconfig(local_channel(), 'adminlte', 'schema', '---');
