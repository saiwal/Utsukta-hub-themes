<?php

use Zotlabs\Lib\Config;
// Add your custom CSS files here.
//head_add_css('/vendor/twbs/bootstrap/dist/css/bootstrap.min.css');
//head_add_css('/library/bootstrap-tagsinput/bootstrap-tagsinput.css');
//head_add_css('/view/css/bootstrap-red.css');
head_add_css('/library/datetimepicker/jquery.datetimepicker.css');
//head_add_css('/library/bootstrap-colorpicker/dist/css/bootstrap-colorpicker.min.css');  
head_add_css('/vendor/twbs/bootstrap-icons/font/bootstrap-icons.min.css');
head_add_css('/view/css/default.css');
head_add_css('/view/theme/adminlte/css/adminlte.min.css');
head_add_css('/view/theme/adminlte/css/style.css');

require_once('view/php/theme_init.php');

head_add_js('/library/bootbox/bootbox.min.js');
head_add_js('/library/bootstrap-tagsinput/bootstrap-tagsinput.js');
head_add_js('/library/datetimepicker/jquery.datetimepicker.js');
head_add_js('/library/bootstrap-colorpicker/dist/js/bootstrap-colorpicker.js');
//head_add_js('/view/theme/redbasic/js/redbasic.js');
head_add_js('/view/theme/adminlte/js/custom.js');
head_add_js('/vendor/twbs/bootstrap/dist/js/bootstrap.bundle.min.js');
head_add_js('view/theme/adminlte/js/adminlte.min.js');
