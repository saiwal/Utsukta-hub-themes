<?php
use Zotlabs\Lib\Config;

require_once('view/php/theme_init.php');
// Add your custom CSS files here.
head_add_css('vendor/twbs/bootstrap-icons/font/bootstrap-icons.min.css');

head_add_css('library/bootstrap-tagsinput/bootstrap-tagsinput.css');
head_add_css('library/bootstrap-colorpicker/dist/css/bootstrap-colorpicker.min.css');
head_add_css('view/theme/picard/style/vendor/fonts/ubuntu-mono/ubuntu-mono.css');
head_add_css('view/theme/picard/style/vendor/fonts/antonio/antonio.css');
head_add_css('view/theme/picard/style/vendor/bootstrap/css/bootstrap.min.css');
/*head_add_css('/vendor/twbs/bootstrap/dist/css/bootstrap.min.css');*/
head_add_css('view/theme/picard/style/vendor/datatables.min.css');
head_add_css('view/theme/picard/style/vendor/datatables_extensions.min.css');
head_add_css('view/theme/picard/style/vendor/daterangepicker.min.css');
head_add_css('view/theme/picard/style/vendor/AdminLTE.min.css');
head_add_css('view/theme/picard/style/vendor/select2.min.css');
head_add_css('view/theme/picard/style/pi-hole.css');
head_add_css('view/theme/picard/style/themes/lcars-picard.css');
head_add_css('view/theme/picard/style/vendor/js-warn.css');

head_add_js('vendor/twbs/bootstrap/dist/js/bootstrap.bundle.min.js');
head_add_js('library/bootbox/bootbox.min.js');
head_add_js('library/bootstrap-tagsinput/bootstrap-tagsinput.js');
head_add_js('library/bootstrap-colorpicker/dist/js/bootstrap-colorpicker.js');

head_add_js('view/theme/picard/scripts/pi-hole/js/footer.js');
/*head_add_js('view/theme/picard/js/style/vendor/js-warn.css');*/
/*head_add_js('view/theme/picard/scripts/vendor/jquery.min.js');*/
/*head_add_js('view/theme/picard/style/vendor/bootstrap/js/bootstrap.min.js');*/
head_add_js('view/theme/picard/scripts/vendor/adminlte.min.js');
head_add_js('view/theme/picard/scripts/vendor/bootstrap-notify.min.js');
head_add_js('view/theme/picard/scripts/vendor/select2.min.js');
head_add_js('view/theme/picard/scripts/vendor/datatables.min.js');
head_add_js('view/theme/picard/scripts/vendor/datatables.select.min.js');
head_add_js('view/theme/picard/scripts/vendor/datatables.buttons.min.js');
head_add_js('view/theme/picard/scripts/vendor/moment.min.js');
head_add_js('view/theme/picard/scripts/vendor/chartjs-adapter-moment.js');
head_add_js('view/theme/picard/style/vendor/font-awesome/js/all.min.js');
head_add_js('view/theme/picard/scripts/pi-hole/js/utils.js');
head_add_js('scripts/pi-hole/js/index.js');
