<?php
use Zotlabs\Lib\Config;
require_once('include/plugin.php');

head_add_css('/library/jRange/jquery.range.css');

head_add_css('/view/theme/spurgeon/css/conversation.css'); 
head_add_css('/view/css/widgets.css');
head_add_css('/view/css/colorbox.css');
head_add_css('/library/justifiedGallery/justifiedGallery.min.css');
head_add_css('/library/datetimepicker/jquery.datetimepicker.css');

head_add_js('jquery.js');
head_add_js('/library/datetimepicker/jquery.datetimepicker.js');

head_add_js('/library/justifiedGallery/jquery.justifiedGallery.min.js');
/* head_add_js('/library/sprintf.js/dist/sprintf.min.js'); */

head_add_js('/library/textcomplete/textcomplete.min.js');
head_add_js('autocomplete.js');

head_add_js('/library/readmore.js/readmore.js');

/* head_add_js('/library/sjcl/sjcl.js'); */
/* head_add_js('/library/sodium-plus/dist/sodium-plus.min.js'); */

head_add_js('/library/libsodium/browsers/sodium.js');
head_add_js('acl.js');
head_add_js('webtoolkit.base64.js');
/* head_add_js('main.js'); */
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
head_add_css('/view/theme/spurgeon/css/bootstrap.css');
head_add_css('/view/theme/spurgeon/css/vendor.css');
head_add_css('/view/theme/spurgeon/css/styles.css');

head_add_css('/library/bootstrap-tagsinput/bootstrap-tagsinput.css');
head_add_css('/library/bootstrap-colorpicker/dist/css/bootstrap-colorpicker.min.css');


head_add_js('/vendor/twbs/bootstrap/dist/js/bootstrap.bundle.min.js');
head_add_js('/library/bootstrap-tagsinput/bootstrap-tagsinput.js');
head_add_js('/library/bootstrap-colorpicker/dist/js/bootstrap-colorpicker.js');

