<?php
use Zotlabs\Lib\Config;
require_once('include/plugin.php');

head_add_css('/library/jRange/jquery.range.css');

head_add_css('/view/css/conversation.css');
/* head_add_css('/view/css/widgets.css'); */
head_add_css('/view/theme/adminlte/css/widgets.css');
head_add_css('/view/css/colorbox.css');
head_add_css('/library/justifiedGallery/justifiedGallery.min.css');
head_add_css('/library/datetimepicker/jquery.datetimepicker.css');

head_add_js('jquery.js');
head_add_js('/library/datetimepicker/jquery.datetimepicker.js');

head_add_js('/library/justifiedGallery/jquery.justifiedGallery.min.js');

head_add_js('/view/theme/adminlte/js/textcomplete.js');
head_add_js('/view/theme/adminlte/js/autocomplete.js');

head_add_js('/library/readmore.js/readmore.js');

head_add_js('/library/sodium-plus/dist/sodium-plus.min.js');

head_add_js('acl.js');
head_add_js('webtoolkit.base64.js');
head_add_js('main.js');
head_add_js('crypto.js');
head_add_js('/library/jRange/jquery.range.js');
head_add_js('/library/colorbox/jquery.colorbox-min.js');

head_add_js('/library/jquery.AreYouSure/jquery.are-you-sure.js');
head_add_js('/library/tableofcontents/jquery.toc.js');
head_add_js('/library/Sortable/Sortable.min.js');

/**
 * Those who require this feature will know what to do with it.
 * Those who don't, won't.
 * Eventually this functionality needs to be provided by a module
 * such that permissions can be enforced. At the moment it's
 * more of a proof of concept; but sufficient for our immediate needs.
 */

$channel = App::get_channel();
if($channel && file_exists($channel['channel_address'] . '.js'))
	head_add_js('/' . $channel['channel_address'] . '.js');

// Add your custom CSS files here.
head_add_css('/vendor/twbs/bootstrap-icons/font/bootstrap-icons.min.css');

head_add_css('/view/theme/adminlte/css/bootstrap-tagsinput.css');
head_add_css('/library/bootstrap-colorpicker/dist/css/bootstrap-colorpicker.min.css');

head_add_css('/view/theme/adminlte/css/adminlte.css');
head_add_css('/view/theme/adminlte/css/overlayscrollbar.min.css');
/*head_add_css('/view/theme/adminlte/css/style.css');*/

head_add_js('/vendor/twbs/bootstrap/dist/js/bootstrap.bundle.min.js');
head_add_js('/view/theme/adminlte/js/bootstrap-tagsinput.js');
head_add_js('/library/bootstrap-colorpicker/dist/js/bootstrap-colorpicker.js');

head_add_js('/view/theme/adminlte/js/adminlte.min.js');
head_add_js('/view/theme/adminlte/js/overlayscrollbar.min.js');


$adminlte_mode = '';
$adminlte_sidebar_mode = '';
$sys = \App::$config['theme_adminlte'] ?? [];

if (local_channel()) {
	$adminlte_mode = ((get_pconfig(local_channel(), 'adminlte', 'dark_mode')) ? 'dark' : 'light');
	$adminlte_sidebar_mode = ((get_pconfig(local_channel(), 'adminlte', 'sidebar_mode')) ? 'sidebar-mini sidebar-collapse' : 'sidebar-mini');
}

if (App::$profile_uid) {
	$adminlte_mode = ((get_pconfig(App::$profile_uid, 'adminlte', 'dark_mode')) ? 'dark' : 'light');
	$adminlte_sidebar_mode = ((get_pconfig(App::$profile_uid, 'adminlte', 'sidebar_mode')) ? 'sidebar-mini sidebar-collapse' : 'sidebar-mini');
}

if (!$adminlte_mode) {
	$adminlte_mode = ((Config::Get('adminlte', 'dark_mode')) ? 'dark' : 'light');
	$adminlte_sidebar_mode = ((Config::Get('adminlte', 'sidebar_mode')) ? 'sidebar-mini sidebar-collapse' : 'sidebar-mini');
}

App::$page['color_mode'] =  $adminlte_mode ?: ($sys['dark_mode'] ? 'dark' : 'light');
App::$page['sidebar_mode'] = $adminlte_sidebar_mode ?: ($sys['sidebar_mode'] ? 'sidebar-mini sidebar-collapse' : 'sidebar-mini');
