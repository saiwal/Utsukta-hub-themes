<?php

namespace Zotlabs\Theme{

use ScssPhp\ScssPhp\Compiler;
use ScssPhp\ScssPhp\OutputStyle;

class spurgeonConfig {

	function get_schemas() {
		$files = glob('view/theme/spurgeon/schema/*.css');

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

		$arr['primary_color'] = get_pconfig(local_channel(),'spurgeon', 'primary_color');
		$arr['success_color'] = get_pconfig(local_channel(),'spurgeon', 'success_color');
		$arr['info_color'] = get_pconfig(local_channel(),'spurgeon', 'info_color');
		$arr['warning_color'] = get_pconfig(local_channel(),'spurgeon', 'warning_color');
		$arr['danger_color'] = get_pconfig(local_channel(),'spurgeon', 'danger_color');
		$arr['dark_mode'] = get_pconfig(local_channel(),'spurgeon', 'dark_mode');
		$arr['navbar_dark_mode'] = get_pconfig(local_channel(),'spurgeon', 'navbar_dark_mode');
		$arr['narrow_navbar'] = get_pconfig(local_channel(),'spurgeon', 'narrow_navbar' );
		$arr['nav_bg'] = get_pconfig(local_channel(),'spurgeon', 'nav_bg' );
		$arr['nav_bg_dark'] = get_pconfig(local_channel(),'spurgeon', 'nav_bg_dark' );
		$arr['bgcolor'] = get_pconfig(local_channel(),'spurgeon', 'background_color' );
		$arr['bgcolor_dark'] = get_pconfig(local_channel(),'spurgeon', 'background_color_dark' );
		$arr['background_image'] = get_pconfig(local_channel(),'spurgeon', 'background_image' );
		$arr['background_image_dark'] = get_pconfig(local_channel(),'spurgeon', 'background_image_dark' );
		$arr['font_size'] = get_pconfig(local_channel(),'spurgeon', 'font_size' );
		$arr['radius'] = get_pconfig(local_channel(),'spurgeon', 'radius' );
		$arr['converse_width']=get_pconfig(local_channel(),"spurgeon","converse_width");
		$arr['top_photo']=get_pconfig(local_channel(),"spurgeon","top_photo");
		$arr['reply_photo']=get_pconfig(local_channel(),"spurgeon","reply_photo");
		$arr['advanced_theming'] = get_pconfig(local_channel(), 'spurgeon', 'advanced_theming');
		return $this->form($arr);
	}

	function post() {
		if(!local_channel()) {
			return;
		}

		set_pconfig(local_channel(), 'spurgeon', 'schema', $_POST['schema']);
		set_pconfig(local_channel(), 'system', 'style_update', time());
	}

	function form($arr) {

		$expert = false;
		if(get_pconfig(local_channel(), 'spurgeon', 'advanced_theming')) {
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
}

namespace { 

  function spurgeon_theme_admin_enable() {
      register_hook('display_item', 'view/theme/spurgeon/hooks/article_layout.php', 'spurgeon_article_layout');
  }

  function spurgeon_theme_admin_disable() {
      unregister_hook('display_item', 'view/theme/spurgeon/hooks/article_layout.php', 'spurgeon_article_layout');
  }

}
