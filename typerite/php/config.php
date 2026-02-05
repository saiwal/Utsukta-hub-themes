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

		$arr['primary_color'] = get_pconfig(local_channel(),'typerite', 'primary_color');
		$arr['success_color'] = get_pconfig(local_channel(),'typerite', 'success_color');
		$arr['info_color'] = get_pconfig(local_channel(),'typerite', 'info_color');
		$arr['warning_color'] = get_pconfig(local_channel(),'typerite', 'warning_color');
		$arr['danger_color'] = get_pconfig(local_channel(),'typerite', 'danger_color');
		$arr['dark_mode'] = get_pconfig(local_channel(),'typerite', 'dark_mode');
		$arr['navbar_dark_mode'] = get_pconfig(local_channel(),'typerite', 'navbar_dark_mode');
		$arr['narrow_navbar'] = get_pconfig(local_channel(),'typerite', 'narrow_navbar' );
		$arr['nav_bg'] = get_pconfig(local_channel(),'typerite', 'nav_bg' );
		$arr['nav_bg_dark'] = get_pconfig(local_channel(),'typerite', 'nav_bg_dark' );
		$arr['bgcolor'] = get_pconfig(local_channel(),'typerite', 'background_color' );
		$arr['bgcolor_dark'] = get_pconfig(local_channel(),'typerite', 'background_color_dark' );
		$arr['background_image'] = get_pconfig(local_channel(),'typerite', 'background_image' );
		$arr['background_image_dark'] = get_pconfig(local_channel(),'typerite', 'background_image_dark' );
		$arr['font_size'] = get_pconfig(local_channel(),'typerite', 'font_size' );
		$arr['radius'] = get_pconfig(local_channel(),'typerite', 'radius' );
		$arr['converse_width']=get_pconfig(local_channel(),"typerite","converse_width");
		$arr['top_photo']=get_pconfig(local_channel(),"typerite","top_photo");
		$arr['reply_photo']=get_pconfig(local_channel(),"typerite","reply_photo");
		$arr['advanced_theming'] = get_pconfig(local_channel(), 'typerite', 'advanced_theming');
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
			'$expert' => $expert,
			'$title' => t("Theme settings"),
			'$dark' => t('Dark style'),
			'$light' => t('Light style'),
			'$common' => t('Common settings'),
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
