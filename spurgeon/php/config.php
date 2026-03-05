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

	$uid = local_channel();

	$terms = tagadelic($uid,0,'','', 0,0,TERM_CATEGORY);

	$options = ['' => t('None')];

	if ($terms) {
		foreach ($terms as $term) {
			$options[$term[0]] = $term[0];
		}
	}

	$arr = [];
	$arr['hero_category'] = get_pconfig($uid, 'spurgeon', 'hero_category', '');
	$arr['hero_category_options'] = $options;

	return $this->form($arr);
}
  

	function post() {
		if(!local_channel()) {
			return;
		}

		if (isset($_POST['spurgeon-settings-submit'])) {
			set_pconfig(local_channel(), 'spurgeon', 'hero_category', $_POST['spurgeon_hero_category']);
			// This is used to refresh the cache
			set_pconfig(local_channel(), 'system', 'style_update', time());
		}

	}

function form($arr) {

	$expert = false;
	if(get_pconfig(local_channel(), 'spurgeon', 'advanced_theming')) {
		$expert = true;
	}

	$o = replace_macros(
		get_markup_template('theme_settings.tpl'),
		[
			'$submit' => t('Submit'),
			'$baseurl' => z_root(),
			'$theme' => \App::$channel['channel_theme'],
			'$expert' => $expert,
			'$title' => t("Theme settings"),
			'$dark' => t('Dark style'),
			'$light' => t('Light style'),

			'$hero_category' => [
				'spurgeon_hero_category',
				t('Hero category'),
				$arr['hero_category'],
				t('Select the category to feature in the hero widget.'),
				$arr['hero_category_options']
			],

			'$common' => t('Common settings'),
		]
	);

	return $o;
}


}
}

namespace { 

  function spurgeon_theme_admin_enable() {
      register_hook('display_item', 'view/theme/spurgeon/hooks/article_layout.php', 'spurgeon_article_layout');
      register_hook('construct_page', 'view/theme/spurgeon/hooks/hero.php', 'channel_hero');
  }

  function spurgeon_theme_admin_disable() {
      unregister_hook('display_item', 'view/theme/spurgeon/hooks/article_layout.php', 'spurgeon_article_layout');
      unregister_hook('construct_page', 'view/theme/spurgeon/hooks/hero.php', 'channel_hero');
  }

}
