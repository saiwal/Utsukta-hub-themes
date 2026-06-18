<?php

namespace { 

  use Zotlabs\Lib\Config;
  use Zotlabs\Extend\Route;

	function solidified_theme_admin_enable() {
		Route::register('view/theme/solidified/mod/api.php', 'api');
  }

  function solidified_theme_admin_disable() {
		Route::unregister('view/theme/solidified/mod/api.php', 'api');
  }

  function theme_admin(&$a) {
  }

  function theme_admin_post() {
	}
}
