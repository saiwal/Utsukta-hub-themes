<?php

if(!App::$install) {

	// Get the UID of the channel owner
	$uid = get_theme_uid();

	if($uid) {
		// Load the owners pconfig
		load_pconfig($uid, 'spurgeon');

	}
}
// Allow layouts to over-ride the schema
if (isset($_REQUEST['schema']) && preg_match('/^[\w_-]+$/i', $_REQUEST['schema'])) {
  $schema = $_REQUEST['schema'];
}
// Apply the settings

header('Cache-Control: max-age=2592000');


if(local_channel() && App::$channel && App::$channel['channel_theme'] != 'spurgeon')
	set_pconfig(local_channel(), 'spurgeon', 'schema', '---');
