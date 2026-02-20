<?php

namespace Zotlabs\Theme {

use ScssPhp\ScssPhp\Compiler;
use ScssPhp\ScssPhp\OutputStyle;

class LcarsConfig {

	function get_schemas() {
		$files = glob('view/theme/lcars/schema/*.css');

		$scheme_choices = [];

		if($files) {

			$scheme_choices['---'] = t('default');
      
      foreach($files as $file) {
				$f = basename($file, ".css");
			  $scheme_name = $f;
				$scheme_choices[$f] = $scheme_name;
			}
		}

		return $scheme_choices;
  }

  function get() {
		if(! local_channel()) {
			return;
		}

		return $this->form($arr);
	}

	function post() {
		if(!local_channel()) {
			return;
		}

		set_pconfig(local_channel(), 'lcars', 'schema', $_POST['schema']);
		set_pconfig(local_channel(), 'system', 'style_update', time());
		if (isset($_POST['lcars-settings-submit'])) {
		
			// This is used to refresh the cache
			set_pconfig(local_channel(), 'system', 'style_update', time());
		}
	}

	function form($arr) {

		$expert = false;
		if(get_pconfig(local_channel(), 'lcars', 'advanced_theming')) {
			$expert = true;
		}

	  	$o = replace_macros(get_markup_template('theme_settings.tpl'), array(
			'$submit' => t('Submit'),
			'$baseurl' => z_root(),
			'$theme' => \App::$channel['channel_theme'],
			));

		return $o;
	}

}
}
//////////////////////////////////////////////
// THEME ADMIN FUNCTIONS (GLOBAL NAMESPACE)
//////////////////////////////////////////////
namespace { 

  use Zotlabs\Lib\Config;
  use Zotlabs\Extend\Route;

  function lcars_theme_admin_enable() {

    $defaults = [
        'schema'              => '---',
    ];

    foreach ($defaults as $k => $v) {
        if (Config::Get('theme_lcars', $k) === false) {
            Config::Set('theme_lcars', $k, $v);
        }
    }
  }

  function lcars_theme_admin_disable() {
  }

  function lcars_get_schemas() {
      $files = glob('view/theme/lcars/schema/*.css');
      $scheme_choices = [];

      if($files) {
          $scheme_choices['---'] = t('default');
          foreach($files as $file) {
              $f = basename($file, ".css");
              $scheme_choices[$f] = $f;
          }
      }

      return $scheme_choices;
  }

  function theme_admin() {
      $schema   = Config::Get('theme_lcars', 'schema', '---');
      $schemas  = lcars_get_schemas();
      // Load system-level (admin) theme config
      $tpl = get_markup_template('theme_settings_admin.tpl');

      return replace_macros($tpl, [

          '$title' => t('lcars Theme Settings (System Defaults)'),
          '$submit' => t('Submit'),
          '$form_security_token' => get_form_security_token('admin_themes'),
      ]);
  }

  function theme_admin_post() {
      check_form_security_token_redirectOnErr('/admin/themes/lcars', 'admin_themes');

      // Save all system admin settings

      Config::Set('theme_lcars', 'schema', $_POST['schema']);
      info(t('Theme settings updated.'));
  }
}
