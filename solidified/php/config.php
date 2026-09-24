<?php

namespace { 

  use Zotlabs\Lib\Config;
  use Zotlabs\Extend\Route;

	function solidified_theme_admin_enable() {
		Route::register('view/theme/solidified/mod/spa.php', 'spa');
		register_hook('enotify_store_end', 'view/theme/solidified/hooks/webpush.php', 'solidified_webpush_send');
		register_hook('spa_webpush', 'view/theme/solidified/hooks/webpush.php', 'solidified_webpush_send');
		register_hook('chat_post', 'view/theme/solidified/hooks/chatfed.php', 'solidified_chatfed_chat_post');
		register_hook('daemon_addon', 'view/theme/solidified/hooks/chatfed.php', 'solidified_chatfed_daemon');
  }

  function solidified_theme_admin_disable() {
		Route::unregister('view/theme/solidified/mod/spa.php', 'spa');
		unregister_hook('enotify_store_end', 'view/theme/solidified/hooks/webpush.php', 'solidified_webpush_send');
		unregister_hook('spa_webpush', 'view/theme/solidified/hooks/webpush.php', 'solidified_webpush_send');
		unregister_hook('chat_post', 'view/theme/solidified/hooks/chatfed.php', 'solidified_chatfed_chat_post');
		unregister_hook('daemon_addon', 'view/theme/solidified/hooks/chatfed.php', 'solidified_chatfed_daemon');
  }

  function theme_admin(&$a) {
  }

  function theme_admin_post() {
	}
}
