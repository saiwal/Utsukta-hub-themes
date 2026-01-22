<?php

use Zotlabs\Lib\Config;
if(!App::$install) {

	// Get the UID of the channel owner
	$uid = get_theme_uid();

	if($uid) {
		// Load the owners pconfig
		load_pconfig($uid, 'keepitsimple');
		$banner_image = get_pconfig($uid, 'keepitsimple', 'banner_image');
	}
}

$sys = [
	'banner_image'		=> Config::Get('theme_keepitsimple','banner_image','/view/theme/keepitsimple/img/header-content-bg.png'),
];
$banner_image = $banner_image ?: $sys['banner_image'] ?: '';
$options = array (
  '$banner_image' => $banner_image,
);
/**/
// Allow layouts to over-ride the schema
if (isset($_REQUEST['schema']) && preg_match('/^[\w_-]+$/i', $_REQUEST['schema'])) {
  $schema = $_REQUEST['schema'];
}
// Apply the settings

$x = file_get_contents('view/theme/keepitsimple/css/style.css');

$schemecss = file_get_contents('view/theme/keepitsimple/schema/' . $schema . '.css');

$x = strtr($x, $options);

header('Cache-Control: max-age=2592000');

echo $schemecss . $x;

// ! If you change the name of the directory containing the theme, be sure to change this line to match.
/*echo @file_get_contents('/view/theme/adminlte/css/style.css');*/

if(local_channel() && App::$channel && App::$channel['channel_theme'] != 'keepitsimple')
	set_pconfig(local_channel(), 'keepitsimple', 'schema', '---');
