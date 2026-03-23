<?php

namespace { 

  use Zotlabs\Lib\Config;
  use Zotlabs\Extend\Route;

	function solidified_theme_admin_enable() {
		/* Network Module Hooks */
    register_hook('network_mod_content', 'view/theme/solidified/hooks/json_ep.php', 'json_network_content');
		/* Settings hook */
		register_hook('settings_mod_content', 'view/theme/solidified/hooks/json_ep.php', 'json_settings_get');
    register_hook('settings_mod_post',    'view/theme/solidified/hooks/json_ep.php', 'json_settings_post');
		/* Directory Hook */
		register_hook('directory_mod_content', 'view/theme/solidified/hooks/json_ep.php', 'json_directory_get');
		/* Connections Hook */
		register_hook('connections_mod_content', 'view/theme/solidified/hooks/json_ep.php', 'json_connections_get');
		/* Channel Hook */
		register_hook('channel_mod_content', 'view/theme/solidified/hooks/json_ep.php', 'json_channel_get');
		/* Files Hook */
		register_hook('cloud_mod_content', 'view/theme/solidified/hooks/json_ep.php', 'json_cloud_get');
		/* Photos Hook */
		register_hook('photos_mod_content', 'view/theme/solidified/hooks/json_ep.php', 'json_photos_get');
		/* Help Hook */
		register_hook('help_mod_content', 'view/theme/solidified/hooks/json_ep.php', 'json_help_get');
  }

  function solidified_theme_admin_disable() {
		/* Network Module Hooks */
    unregister_hook('network_mod_content', 'view/theme/solidified/hooks/json_ep.php', 'json_network_content');
		/* Settings hook */
		unregister_hook('settings_mod_content', 'view/theme/solidified/hooks/json_ep.php', 'json_settings_get');
    unregister_hook('settings_mod_post',    'view/theme/solidified/hooks/json_ep.php', 'json_settings_post');		/* Directory Hook */
		/* Directory Hook */
		unregister_hook('directory_mod_content', 'view/theme/solidified/hooks/json_ep.php', 'json_directory_get');
		/* Connections Hook */
		unregister_hook('connections_mod_content', 'view/theme/solidified/hooks/json_ep.php', 'json_connections_get');
		/* Channel Hook */
		unregister_hook('channel_mod_content', 'view/theme/solidified/hooks/json_ep.php', 'json_channel_get');
		/* Files Hook */
		unregister_hook('cloud_mod_content', 'view/theme/solidified/hooks/json_ep.php', 'json_cloud_get');
		/* Photos Hook */
		unregister_hook('photos_mod_content', 'view/theme/solidified/hooks/json_ep.php', 'json_photos_get');
		/* Help Hook */
		unregister_hook('help_mod_content', 'view/theme/solidified/hooks/json_ep.php', 'json_help_get');
  }

  function theme_admin(&$a) {
  }

  function theme_admin_post() {
	}
}
