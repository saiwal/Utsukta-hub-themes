<?php

namespace Zotlabs\Theme{

use ScssPhp\ScssPhp\Compiler;
use ScssPhp\ScssPhp\OutputStyle;

class keepitsimpleConfig {

	function get_schemas() {
		$files = glob('view/theme/keepitsimple/schema/*.css');

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

		$arr['banner_image'] = get_pconfig(local_channel(),'keepitsimple', 'banner_image' );
		$arr['subtitle'] = get_pconfig(local_channel(),'keepitsimple', 'subtitle' );
		return $this->form($arr);
	}

	function post() {
		if(!local_channel()) {
			return;
		}

		set_pconfig(local_channel(), 'keepitsimple', 'schema', $_POST['schema']);
		set_pconfig(local_channel(), 'system', 'style_update', time());
		if (isset($_POST['keepitsimple-settings-submit'])) {
			set_pconfig(local_channel(), 'keepitsimple', 'banner_image', $_POST['keepitsimple_banner_image']);
			set_pconfig(local_channel(), 'keepitsimple', 'subtitle', $_POST['kis_subtitle']);
			// This is used to refresh the cache
			set_pconfig(local_channel(), 'system', 'style_update', time());
		}
	}

	function form($arr) {


	  	$o = replace_macros(get_markup_template('theme_settings.tpl'), array(
			'$submit' => t('Submit'),
			'$baseurl' => z_root(),
			'$theme' => \App::$channel['channel_theme'],
			'$title' => t("Theme settings"),
			'$dark' => t('Dark style'),
			'$subtitle' => array('kis_subtitle', t('Set subtitle for your channel'), $arr['subtitle']),
			'$banner_image' => array('keepitsimple_banner_image', t('Set the banner image(url link, blank for none)'), $arr['banner_image']),
			'$common' => t('Common settings'),
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


	function keepitsimple_theme_admin_enable() {
		// This function is called once when the theme is being enabled by the admin
		// It can be used to register hooks etc.
			$defaults = [
				'banner_image'    => '',
				'subtitle'        => '',
			];

			foreach ($defaults as $k => $v) {
					if (Config::Get('theme_keepitsimple', $k) === false) {
							Config::Set('theme_keepitsimple', $k, $v);
					}
			}

	}

	function keepitsimple_theme_admin_disable() {
		// This function is called once when the theme is being disabled by the admin
		// It can be used to unregister hooks etc.
	}

  function theme_admin() {
      // Load system-level (admin) theme config
      $banner_image     = Config::Get('theme_keepitsimple', 'banner_image', '');

			$t = file_get_contents(__DIR__ . '/../tpl/theme_settings_admin.tpl');

      return replace_macros($t, [

          '$banner_image' => [
              'banner_image',
              t('banner image url'),
              $banner_image,
              t('leave empty for none')
					],



          '$submit' => t('Submit'),
          '$form_security_token' => get_form_security_token('admin_themes'),
      ]);
	}

  function theme_admin_post() {
      check_form_security_token_redirectOnErr('/admin/themes/keepitsimple', 'admin_themes');

      // Save all system admin settings
      Config::Set('theme_keepitsimple', 'banner_image', trim($_POST['banner_image']));

      info(t('Theme settings updated.'));
  }

}
