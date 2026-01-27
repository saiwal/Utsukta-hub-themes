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

		$arr['primary_color'] = get_pconfig(local_channel(),'lcars', 'primary_color');
		$arr['success_color'] = get_pconfig(local_channel(),'lcars', 'success_color');
		$arr['info_color'] = get_pconfig(local_channel(),'lcars', 'info_color');
		$arr['warning_color'] = get_pconfig(local_channel(),'lcars', 'warning_color');
		$arr['danger_color'] = get_pconfig(local_channel(),'lcars', 'danger_color');
		$arr['bg_mode'] = get_pconfig(local_channel(),'lcars', 'bg_mode');
		$arr['dark_mode'] = get_pconfig(local_channel(),'lcars', 'dark_mode');
		$arr['sidebar_mode'] = get_pconfig(local_channel(),'lcars', 'sidebar_mode');
		/*$arr['narrow_navbar'] = get_pconfig(local_channel(),'lcars', 'narrow_navbar' );*/
		/*$arr['nav_bg'] = get_pconfig(local_channel(),'lcars', 'nav_bg' );*/
		/*$arr['nav_bg_dark'] = get_pconfig(local_channel(),'lcars', 'nav_bg_dark' );*/
		$arr['bgcolor'] = get_pconfig(local_channel(),'lcars', 'background_color' );
		$arr['bgcolor_dark'] = get_pconfig(local_channel(),'lcars', 'background_color_dark' );
		$arr['background_image'] = get_pconfig(local_channel(),'lcars', 'background_image' );
		$arr['background_image_dark'] = get_pconfig(local_channel(),'lcars', 'background_image_dark' );
		$arr['tourhq'] = get_pconfig(local_channel(),'lcars', 'tour_hq' );
		/*$arr['font_size'] = get_pconfig(local_channel(),'lcars', 'font_size' );*/
		/*$arr['radius'] = get_pconfig(local_channel(),'lcars', 'radius' );*/
		/*$arr['converse_width']=get_pconfig(local_channel(),"lcars","converse_width");*/
		/*$arr['top_photo']=get_pconfig(local_channel(),"lcars","top_photo");*/
		/*$arr['reply_photo']=get_pconfig(local_channel(),"lcars","reply_photo");*/
		$arr['advanced_theming'] = get_pconfig(local_channel(), 'lcars', 'advanced_theming');
		return $this->form($arr);
	}

	function post() {
		if(!local_channel()) {
			return;
		}

		set_pconfig(local_channel(), 'lcars', 'schema', $_POST['schema']);
		set_pconfig(local_channel(), 'system', 'style_update', time());
		if (isset($_POST['lcars-settings-submit'])) {
	
			set_pconfig(local_channel(), 'lcars', 'primary_color', $_POST['lcars_primary_color']);
			set_pconfig(local_channel(), 'lcars', 'success_color', $_POST['lcars_success_color']);
			set_pconfig(local_channel(), 'lcars', 'info_color', $_POST['lcars_info_color']);
			set_pconfig(local_channel(), 'lcars', 'warning_color', $_POST['lcars_warning_color']);
			set_pconfig(local_channel(), 'lcars', 'danger_color', $_POST['lcars_danger_color']);
			set_pconfig(local_channel(), 'lcars', 'dark_mode', $_POST['lcars_dark_mode']);
			set_pconfig(local_channel(), 'lcars', 'sidebar_mode', $_POST['lcars_sidebar_mode']);
			set_pconfig(local_channel(), 'lcars', 'bg_mode', $_POST['lcars_bg_mode']);
			set_pconfig(local_channel(), 'lcars', 'background_color', $_POST['lcars_background_color']);
			set_pconfig(local_channel(), 'lcars', 'background_color_dark', $_POST['lcars_background_color_dark']);
			set_pconfig(local_channel(), 'lcars', 'background_image', $_POST['lcars_background_image']);
			set_pconfig(local_channel(), 'lcars', 'background_image_dark', $_POST['lcars_background_image_dark']);
			set_pconfig(local_channel(), 'lcars', 'tour_hq', $_POST['lcars_tourhq']);
			set_pconfig(local_channel(), 'lcars', 'advanced_theming', $_POST['lcars_advanced_theming']);
		
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
      '$expert' => $expert,
			'$title' => t("Theme settings"),
			'$dark' => t('Dark style'),
			'$light' => t('Light style'),
			'$common' => t('Common settings'),
//			'$primary_color' => array('lcars_primary_color', t('Primary theme color'), $arr['primary_color'], '<i class="bi bi-circle-fill text-primary"></i> ' . t('Current color, leave empty for default')),
//			'$success_color' => array('lcars_success_color', t('Success theme color'), $arr['success_color'], '<i class="bi bi-circle-fill text-success"></i> ' . t('Current color, leave empty for default')),
//			'$info_color' => array('lcars_info_color', t('Info theme color'), $arr['info_color'], '<i class="bi bi-circle-fill text-info"></i> ' . t('Current color, leave empty for default')),
//			'$warning_color' => array('lcars_warning_color', t('Warning theme color'), $arr['warning_color'], '<i class="bi bi-circle-fill text-warning"></i> ' . t('Current color, leave empty for default')),
//			'$danger_color' => array('lcars_danger_color', t('Danger theme color'), $arr['danger_color'], '<i class="bi bi-circle-fill text-danger"></i> ' . t('Current color, leave empty for default')),
			'$dark_mode' => array('lcars_dark_mode',t('Default to dark mode'),$arr['dark_mode'], '', array(t('No'),t('Yes'))),
			'$sidebar_mode' => array('lcars_sidebar_mode',t('Choose sidebar mode'),$arr['sidebar_mode'], '', array(t('Expanded'),t('Collapsed'))),
			'$bg_mode' => array('lcars_bg_mode',t('Set background image tile mode'),$arr['bg_mode'], '', array(t('Tiled'),t('Cover'))),
			'$bgcolor' => array('lcars_background_color', t('Set the background color(e.g. #ffffff, blank for default)'), $arr['bgcolor']),
			'$bgcolor_dark' => array('lcars_background_color_dark', t('Set the dark background color(e.g. #000000, blank for default)'), $arr['bgcolor_dark']),
			'$background_image' => array('lcars_background_image', t('Set the background image(url link, blank for none)'), $arr['background_image']),
			'$background_image_dark' => array('lcars_background_image_dark', t('Set the dark background image(url link, blank for none)'), $arr['background_image_dark']),
			'$tourhq' => array('lcars_tourhq', t('HQ tour completed'), $arr['tourhq']),
//			'$converse_width' => array('lcars_converse_width',t('Set maximum width of content region in rem'),$arr['converse_width'], t('Leave empty for default width')),
//			'$advanced_theming' => ['lcars_advanced_theming', t('Show advanced settings'), $arr['advanced_theming'], '', [t('No'), t('Yes')]]
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
    logger('hook init', LOGGER_DEBUG);
    register_hook('page_end', 'view/theme/lcars/hooks/tours.php', 'lcars_tours');
    register_hook('nav', 'view/theme/lcars/hooks/layout.php', 'notification_nav');
    Route::register('view/theme/lcars/mod/Mod_lcars.php', 'lcars');
    Route::register('view/theme/lcars/mod/Mod_lcars.php', 'test');

    $defaults = [
        'schema'              => '---',
        'dark_mode'           => 0,
        'sidebar_mode'        => 0,
        'bg_mode'             => 0,
        'background_color'    => '',
        'background_color_dark' => '',
        'background_image'    => '',
        'background_image_dark' => '',
    ];

    foreach ($defaults as $k => $v) {
        if (Config::Get('theme_lcars', $k) === false) {
            Config::Set('theme_lcars', $k, $v);
        }
    }
  }

  function lcars_theme_admin_disable() {
    unregister_hook('page_end', 'view/theme/lcars/hooks/tours.php', 'lcars_tours');
    unregister_hook('nav', 'view/theme/lcars/hooks/layout.php', 'notification_nav');
    Route::unregister('view/theme/lcars/mod/Mod_lcars.php', 'lcars');
    Route::unregister('view/theme/lcars/mod/Mod_lcars.php', 'test');
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
      $dark_mode   = Config::Get('theme_lcars', 'dark_mode', 0);
      $sidebar     = Config::Get('theme_lcars', 'sidebar_mode', 0);
      $bg_mode     = Config::Get('theme_lcars', 'bg_mode', 0);
      $bgcolor     = Config::Get('theme_lcars', 'background_color', '');
      $bgcolor_dark = Config::Get('theme_lcars', 'background_color_dark', '');
      $bg_image     = Config::Get('theme_lcars', 'background_image', '');
      $logo     = Config::Get('theme_lcars', 'logo', '/view/theme/lcars/img/hz.png');
      $bg_image_dark = Config::Get('theme_lcars', 'background_image_dark', '');
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
      Config::Set('theme_lcars', 'dark_mode', intval($_POST['dark_mode']));
      Config::Set('theme_lcars', 'sidebar_mode', intval($_POST['sidebar_mode']));
      Config::Set('theme_lcars', 'bg_mode', intval($_POST['bg_mode']));
      Config::Set('theme_lcars', 'background_color', trim($_POST['background_color']));
      Config::Set('theme_lcars', 'background_color_dark', trim($_POST['background_color_dark']));
      Config::Set('theme_lcars', 'background_image', trim($_POST['background_image']));
      Config::Set('theme_lcars', 'logo', trim($_POST['logo']));
      Config::Set('theme_lcars', 'background_image_dark', trim($_POST['background_image_dark']));

      Config::Set('theme_lcars', 'schema', $_POST['schema']);
      info(t('Theme settings updated.'));
  }
}
