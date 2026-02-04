<?php

use Zotlabs\Lib\Config;
if(!App::$install) {

	// Get the UID of the channel owner
	$uid = get_theme_uid();

	if($uid) {
		// Load the owners pconfig
		load_pconfig($uid, 'lcars');

		/*$custom_bs = get_pconfig($uid, 'lcars', 'bootstrap');*/
		/*$nav_bg = get_pconfig($uid, 'lcars', 'nav_bg');*/
		/*$nav_bg_dark = get_pconfig($uid, 'lcars', 'nav_bg_dark');*/
    /*$narrow_navbar = get_pconfig($uid,'lcars','narrow_navbar');*/
    $bgcolor = get_pconfig($uid, 'redbasic', 'background_color');
		$bgcolor_dark = get_pconfig($uid, 'redbasic', 'background_color_dark');
		$bgcolor = get_pconfig($uid, 'lcars', 'background_color');
		$bgcolor_dark = get_pconfig($uid, 'lcars', 'background_color_dark');
    $schema = get_pconfig($uid, 'lcars', 'schema');
    /*var_dump(App::$config[$uid]['lcars']); // Check the 'lcars' family*/
    /*var_dump(App::$config[$uid]['lcars']['schema']); // Check the 'schema' key    */
		/*  $schema = 'journal';*/
		$background_image = get_pconfig($uid, 'lcars', 'background_image');
		$background_image_dark = get_pconfig($uid, 'lcars', 'background_image_dark');
		$bg_mode = get_pconfig($uid, 'lcars', 'bg_mode');
    $dark_mode = get_pconfig($uid, 'lcars', 'dark_mode');
		$tour = get_pconfig($uid, 'lcars', 'tour_done');
		/*$converse_width = get_pconfig($uid,'lcars','converse_width');*/
		/*$top_photo = get_pconfig($uid,'lcars','top_photo');*/
		/*  $reply_photo = get_pconfig($uid,'lcars','reply_photo');*/
	}
}

$sys = [
    'schema'                => Config::Get('theme_lcars','schema', '---'),
    'dark_mode'             => Config::Get('theme_lcars','dark_mode', 0),
    'sidebar_mode'          => Config::Get('theme_lcars','sidebar_mode', 0),
    'bg_mode'               => Config::Get('theme_lcars','bg_mode', 0),
    'background_color'      => Config::Get('theme_lcars','background_color', ''),
    'background_color_dark' => Config::Get('theme_lcars','background_color_dark', ''),
    'logo'      => Config::Get('theme_lcars','logo', '/view/theme/lcars/img/hz.png'),
    'background_image'      => Config::Get('theme_lcars','background_image', ''),
    'background_image_dark' => Config::Get('theme_lcars','background_image_dark', ''),
];
# set some defaults

$schema = $schema ?: $sys['schema'] ?: 'classic';
$bgcolor = $bgcolor ?: $sys['background_color'] ?: 'var(--bs-body-bg)';
$bgcolor_dark = $bgcolor_dark ?: $sys['background_color_dark'] ?: 'var(--bs-body-bg)';
$background_image = $background_image ?: $sys['background_image'] ?: '';
$logo = $sys['logo'];
$background_image_dark = $background_image_dark ?: $sys['background_image_dark'] ?: '';
$bg_val =
    (is_numeric($bg_mode) ? intval($bg_mode) :
    (is_numeric($sys['bg_mode'] ?? null) ? intval($sys['bg_mode']) : 0));
$bg_mode = ($bg_val == 1) ? 'cover' : '';
$options = array (
  '$bgcolor' => $bgcolor,
  '$bgcolor_dark' => $bgcolor_dark,
  '$background_image' => $background_image,
  '$logo' => $logo,
  '$background_image_dark' => $background_image_dark,
  '$bg_mode' => $bg_mode,
);
/**/
// Allow layouts to over-ride the schema
if (isset($_REQUEST['schema']) && preg_match('/^[\w_-]+$/i', $_REQUEST['schema'])) {
  $schema = $_REQUEST['schema'];
}
// Apply the settings

$x = file_get_contents('view/theme/lcars/css/style.css');

$x = strtr($x, $options);

$schemecss = file_get_contents('view/theme/lcars/schema/' . $schema . '.css');

/*$o = strtr($x, $options);*/

header('Cache-Control: max-age=2592000');

echo $schemecss . $x;

// ! If you change the name of the directory containing the theme, be sure to change this line to match.
/*echo @file_get_contents('/view/theme/lcars/css/style.css');*/

if(local_channel() && App::$channel && App::$channel['channel_theme'] != 'lcars')
	set_pconfig(local_channel(), 'lcars', 'schema', '---');
