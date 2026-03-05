<?php

use Zotlabs\Lib\Config;
if(!App::$install) {

	// Get the UID of the channel owner
	$uid = get_theme_uid();

	if($uid) {
		// Load the owners pconfig
		load_pconfig($uid, 'adminlte');

    $bgcolor = get_pconfig($uid, 'redbasic', 'background_color');
		$bgcolor_dark = get_pconfig($uid, 'redbasic', 'background_color_dark');
		$bgcolor = get_pconfig($uid, 'adminlte', 'background_color');
		$bgcolor_dark = get_pconfig($uid, 'adminlte', 'background_color_dark');
    $schema = get_pconfig($uid, 'adminlte', 'schema');
		$background_image = get_pconfig($uid, 'adminlte', 'background_image');
		$background_image_dark = get_pconfig($uid, 'adminlte', 'background_image_dark');
		$bg_mode = get_pconfig($uid, 'adminlte', 'bg_mode');
    $dark_mode = get_pconfig($uid, 'adminlte', 'dark_mode');
		$tour = get_pconfig($uid, 'adminlte', 'tour_done');
	}
}

$sys = [
    'schema'                => Config::Get('theme_adminlte','schema', '---'),
    'dark_mode'             => Config::Get('theme_adminlte','dark_mode', 0),
    'sidebar_mode'          => Config::Get('theme_adminlte','sidebar_mode', 0),
    'bg_mode'               => Config::Get('theme_adminlte','bg_mode', 0),
    'background_color'      => Config::Get('theme_adminlte','background_color', ''),
    'background_color_dark' => Config::Get('theme_adminlte','background_color_dark', ''),
    'logo'      => Config::Get('theme_adminlte','logo', '/view/theme/adminlte/img/hz.png'),
    'background_image'      => Config::Get('theme_adminlte','background_image', ''),
    'background_image_dark' => Config::Get('theme_adminlte','background_image_dark', ''),
];
# set some defaults

$schema = $schema ?: $sys['schema'] ?: 'default';
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

$x = file_get_contents('view/theme/adminlte/css/style.css');

$x = strtr($x, $options);

$schemecss = file_get_contents('view/theme/adminlte/schema/' . $schema . '.css');


header('Cache-Control: max-age=2592000');

echo $schemecss . $x;


if(local_channel() && App::$channel && App::$channel['channel_theme'] != 'adminlte')
	set_pconfig(local_channel(), 'adminlte', 'schema', '---');
