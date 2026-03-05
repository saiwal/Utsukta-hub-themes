<?php

use Zotlabs\Lib\Config;
if(!App::$install) {

	// Get the UID of the channel owner
	$uid = get_theme_uid();

	if($uid) {
		// Load the owners pconfig
		load_pconfig($uid, 'lcars');

    $schema = get_pconfig($uid, 'lcars', 'schema');
	}
}

$sys = [
    'schema'                => Config::Get('theme_lcars','schema', '---'),
];
# set some defaults

$schema = $schema ?: $sys['schema'] ?: 'classic';
$options = array (
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


header('Cache-Control: max-age=2592000');

echo $schemecss . $x;

// ! If you change the name of the directory containing the theme, be sure to change this line to match.
/*echo @file_get_contents('/view/theme/lcars/css/style.css');*/

if(local_channel() && App::$channel && App::$channel['channel_theme'] != 'lcars')
	set_pconfig(local_channel(), 'lcars', 'schema', '---');
