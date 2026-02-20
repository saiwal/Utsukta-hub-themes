<?php

namespace Zotlabs\Theme;

use ScssPhp\ScssPhp\Compiler;
use ScssPhp\ScssPhp\OutputStyle;

class typeriteConfig {

	function get_schemas() {
		$files = glob('view/theme/typerite/schema/*.css');

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

		set_pconfig(local_channel(), 'typerite', 'schema', $_POST['schema']);
		set_pconfig(local_channel(), 'system', 'style_update', time());
	}

	function form($arr) {

		$expert = false;
		if(get_pconfig(local_channel(), 'typerite', 'advanced_theming')) {
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

function typerite_theme_admin_enable() {
	// This function is called once when the theme is being enabled by the admin
	// It can be used to register hooks etc.
}

function typerite_theme_admin_disable() {
	// This function is called once when the theme is being disabled by the admin
	// It can be used to unregister hooks etc.
}
