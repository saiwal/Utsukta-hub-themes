<?php

namespace { 

  use Zotlabs\Lib\Config;
  use Zotlabs\Extend\Route;

	function solidified_theme_admin_enable() {
		Route::register('view/theme/solidified/mod/spa.php', 'spa');
		register_hook('enotify_store_end', 'view/theme/solidified/hooks/webpush.php', 'solidified_webpush_send');
  }

  function solidified_theme_admin_disable() {
		Route::unregister('view/theme/solidified/mod/spa.php', 'spa');
		unregister_hook('enotify_store_end', 'view/theme/solidified/hooks/webpush.php', 'solidified_webpush_send');
  }

  function theme_admin(&$a) {
  }

  function theme_admin_post() {
	}
}
