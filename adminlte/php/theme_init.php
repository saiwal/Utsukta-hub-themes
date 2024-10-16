<?php

use Zotlabs\Lib\Config;

require_once('view/php/theme_init.php');

// Add your custom CSS files here.
head_add_css('/vendor/twbs/bootstrap-icons/font/bootstrap-icons.min.css');
head_add_css('/library/bootstrap-tagsinput/bootstrap-tagsinput.css');
head_add_css('/library/bootstrap-colorpicker/dist/css/bootstrap-colorpicker.min.css');

head_add_css('/view/theme/adminlte/css/adminlte.min.css');
/*head_add_css('/view/theme/adminlte/css/style.css');*/

head_add_js('/vendor/twbs/bootstrap/dist/js/bootstrap.bundle.min.js');
head_add_js('/library/bootbox/bootbox.min.js');
head_add_js('/library/bootstrap-tagsinput/bootstrap-tagsinput.js');
head_add_js('/library/bootstrap-colorpicker/dist/js/bootstrap-colorpicker.js');

head_add_js('view/theme/adminlte/js/adminlte.min.js');
head_add_js('/view/theme/adminlte/js/custom.js');
