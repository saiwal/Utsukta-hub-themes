<?php

namespace { 

  use Zotlabs\Lib\Config;
  use Zotlabs\Extend\Route;

  function solidified_theme_admin_enable() {
    register_hook('network_mod_content', 'view/theme/solidified/hooks/json_ep.php', 'json_network_content');
		register_hook('settings_display_mod_content', 'addon/json_ep/json_ep.php', 'json_settings_get');
    register_hook('settings_display_mod_post',    'addon/json_ep/json_ep.php', 'json_settings_post');
  }

  function solidified_theme_admin_disable() {
    unregister_hook('network_mod_content', 'view/theme/solidified/hooks/json_ep.php', 'json_network_content');
		unregister_hook('settings_display_mod_content', 'addon/json_ep/json_ep.php', 'json_settings_get');
    unregister_hook('settings_display_mod_post',    'addon/json_ep/json_ep.php', 'json_settings_post');
  }

  function theme_admin(&$a) {
  }

  function theme_admin_post() {
	}
}
