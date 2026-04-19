<?php

namespace { 

  use Zotlabs\Lib\Config;
  use Zotlabs\Extend\Route;

	function solidified_theme_admin_enable() {
    register_hook('pconfig_mod_content', 'view/theme/solidified/hooks/json_ep.php', 'json_pconfig_get');
		register_hook('display_mod_content', 'view/theme/solidified/hooks/json_ep.php', 'json_display_get');
    /* register_hook('network_mod_content', 'view/theme/solidified/hooks/json_ep.php', 'json_network_content'); */
		register_hook('settings_mod_content', 'view/theme/solidified/hooks/json_ep.php', 'json_settings_get');
    register_hook('settings_mod_post',    'view/theme/solidified/hooks/json_ep.php', 'json_settings_post');
		register_hook('directory_mod_content', 'view/theme/solidified/hooks/json_ep.php', 'json_directory_get');
		register_hook('articles_mod_content', 'view/theme/solidified/hooks/json_ep.php', 'json_articles_get');
		register_hook('connections_mod_content', 'view/theme/solidified/hooks/json_ep.php', 'json_connections_get');
		/* register_hook('channel_mod_content', 'view/theme/solidified/hooks/json_ep.php', 'json_channel_get'); */
		register_hook('cloud_mod_content', 'view/theme/solidified/hooks/json_ep.php', 'json_cloud_get');
		register_hook('photos_mod_content', 'view/theme/solidified/hooks/json_ep.php', 'json_photos_get');
		register_hook('help_mod_content', 'view/theme/solidified/hooks/json_ep.php', 'json_help_get');
		Route::register('view/theme/solidified/mod/nav.php', 'nav_api');
		Route::register('view/theme/solidified/mod/chat.php', 'chat_api');
		Route::register('view/theme/solidified/mod/manage.php', 'manage_api');
		Route::register('view/theme/solidified/mod/connections.php', 'connections_api');
		Route::register('view/theme/solidified/mod/directory.php', 'directory_api');
		Route::register('view/theme/solidified/mod/weather.php', 'weather');
		Route::register('view/theme/solidified/mod/webpages.php', 'webpages_api');
		Route::register('view/theme/solidified/mod/api.php', 'api');
  }

  function solidified_theme_admin_disable() {
    unregister_hook('pconfig_mod_content', 'view/theme/solidified/hooks/json_ep.php', 'json_pconfig_get');
		unregister_hook('display_mod_content', 'view/theme/solidified/hooks/json_ep.php', 'json_display_get');
    /* unregister_hook('network_mod_content', 'view/theme/solidified/hooks/json_ep.php', 'json_network_content'); */
		unregister_hook('settings_mod_content', 'view/theme/solidified/hooks/json_ep.php', 'json_settings_get');
    unregister_hook('settings_mod_post',    'view/theme/solidified/hooks/json_ep.php', 'json_settings_post');
		unregister_hook('directory_mod_content', 'view/theme/solidified/hooks/json_ep.php', 'json_directory_get');
		unregister_hook('articles_mod_content', 'view/theme/solidified/hooks/json_ep.php', 'json_articles_get');
		unregister_hook('connections_mod_content', 'view/theme/solidified/hooks/json_ep.php', 'json_connections_get');
		/* unregister_hook('channel_mod_content', 'view/theme/solidified/hooks/json_ep.php', 'json_channel_get'); */
		unregister_hook('cloud_mod_content', 'view/theme/solidified/hooks/json_ep.php', 'json_cloud_get');
		unregister_hook('photos_mod_content', 'view/theme/solidified/hooks/json_ep.php', 'json_photos_get');
		unregister_hook('help_mod_content', 'view/theme/solidified/hooks/json_ep.php', 'json_help_get');

		Route::unregister('view/theme/solidified/mod/nav.php', 'nav_api');
		Route::unregister('view/theme/solidified/mod/chat.php', 'chat_api');
		Route::unregister('view/theme/solidified/mod/manage.php', 'manage_api');
		Route::unregister('view/theme/solidified/mod/connections.php', 'connections_api');
		Route::unregister('view/theme/solidified/mod/directory.php', 'directory_api');
		Route::unregister('view/theme/solidified/mod/weather.php', 'weather');
		Route::unregister('view/theme/solidified/mod/webpages.php', 'webpages_api');
		Route::unregister('view/theme/solidified/mod/api.php', 'api');
  }

  function theme_admin(&$a) {
  }

  function theme_admin_post() {
	}
}
