<?php

namespace Zotlabs\Theme {

use ScssPhp\ScssPhp\Compiler;
use ScssPhp\ScssPhp\OutputStyle;

class AdminlteConfig {

	function get_schemas() {
		$files = glob('view/theme/adminlte/schema/*.css');

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

		$arr['primary_color'] = get_pconfig(local_channel(),'adminlte', 'primary_color');
		$arr['success_color'] = get_pconfig(local_channel(),'adminlte', 'success_color');
		$arr['info_color'] = get_pconfig(local_channel(),'adminlte', 'info_color');
		$arr['warning_color'] = get_pconfig(local_channel(),'adminlte', 'warning_color');
		$arr['danger_color'] = get_pconfig(local_channel(),'adminlte', 'danger_color');
		$arr['bg_mode'] = get_pconfig(local_channel(),'adminlte', 'bg_mode');
		$arr['dark_mode'] = get_pconfig(local_channel(),'adminlte', 'dark_mode');
		$arr['sidebar_mode'] = get_pconfig(local_channel(),'adminlte', 'sidebar_mode');
		/*$arr['narrow_navbar'] = get_pconfig(local_channel(),'adminlte', 'narrow_navbar' );*/
		/*$arr['nav_bg'] = get_pconfig(local_channel(),'adminlte', 'nav_bg' );*/
		/*$arr['nav_bg_dark'] = get_pconfig(local_channel(),'adminlte', 'nav_bg_dark' );*/
		$arr['bgcolor'] = get_pconfig(local_channel(),'adminlte', 'background_color' );
		$arr['bgcolor_dark'] = get_pconfig(local_channel(),'adminlte', 'background_color_dark' );
		$arr['background_image'] = get_pconfig(local_channel(),'adminlte', 'background_image' );
		$arr['background_image_dark'] = get_pconfig(local_channel(),'adminlte', 'background_image_dark' );
		$arr['tourhq'] = get_pconfig(local_channel(),'adminlte', 'tour_hq' );
		/*$arr['font_size'] = get_pconfig(local_channel(),'adminlte', 'font_size' );*/
		/*$arr['radius'] = get_pconfig(local_channel(),'adminlte', 'radius' );*/
		/*$arr['converse_width']=get_pconfig(local_channel(),"adminlte","converse_width");*/
		/*$arr['top_photo']=get_pconfig(local_channel(),"adminlte","top_photo");*/
		/*$arr['reply_photo']=get_pconfig(local_channel(),"adminlte","reply_photo");*/
		$arr['advanced_theming'] = get_pconfig(local_channel(), 'adminlte', 'advanced_theming');
		return $this->form($arr);
	}

	function post() {
		if(!local_channel()) {
			return;
		}

		set_pconfig(local_channel(), 'adminlte', 'schema', $_POST['schema']);
		set_pconfig(local_channel(), 'system', 'style_update', time());
		if (isset($_POST['adminlte-settings-submit'])) {
	
			set_pconfig(local_channel(), 'adminlte', 'primary_color', $_POST['adminlte_primary_color']);
			set_pconfig(local_channel(), 'adminlte', 'success_color', $_POST['adminlte_success_color']);
			set_pconfig(local_channel(), 'adminlte', 'info_color', $_POST['adminlte_info_color']);
			set_pconfig(local_channel(), 'adminlte', 'warning_color', $_POST['adminlte_warning_color']);
			set_pconfig(local_channel(), 'adminlte', 'danger_color', $_POST['adminlte_danger_color']);
			set_pconfig(local_channel(), 'adminlte', 'dark_mode', $_POST['adminlte_dark_mode']);
			set_pconfig(local_channel(), 'adminlte', 'sidebar_mode', $_POST['adminlte_sidebar_mode']);
			set_pconfig(local_channel(), 'adminlte', 'bg_mode', $_POST['adminlte_bg_mode']);
			set_pconfig(local_channel(), 'adminlte', 'background_color', $_POST['adminlte_background_color']);
			set_pconfig(local_channel(), 'adminlte', 'background_color_dark', $_POST['adminlte_background_color_dark']);
			set_pconfig(local_channel(), 'adminlte', 'background_image', $_POST['adminlte_background_image']);
			set_pconfig(local_channel(), 'adminlte', 'background_image_dark', $_POST['adminlte_background_image_dark']);
			set_pconfig(local_channel(), 'adminlte', 'tour_hq', $_POST['adminlte_tourhq']);
			set_pconfig(local_channel(), 'adminlte', 'advanced_theming', $_POST['adminlte_advanced_theming']);
		
			// This is used to refresh the cache
			set_pconfig(local_channel(), 'system', 'style_update', time());
		}
	}

	function form($arr) {

		$expert = false;
		if(get_pconfig(local_channel(), 'adminlte', 'advanced_theming')) {
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
//			'$primary_color' => array('adminlte_primary_color', t('Primary theme color'), $arr['primary_color'], '<i class="bi bi-circle-fill text-primary"></i> ' . t('Current color, leave empty for default')),
//			'$success_color' => array('adminlte_success_color', t('Success theme color'), $arr['success_color'], '<i class="bi bi-circle-fill text-success"></i> ' . t('Current color, leave empty for default')),
//			'$info_color' => array('adminlte_info_color', t('Info theme color'), $arr['info_color'], '<i class="bi bi-circle-fill text-info"></i> ' . t('Current color, leave empty for default')),
//			'$warning_color' => array('adminlte_warning_color', t('Warning theme color'), $arr['warning_color'], '<i class="bi bi-circle-fill text-warning"></i> ' . t('Current color, leave empty for default')),
//			'$danger_color' => array('adminlte_danger_color', t('Danger theme color'), $arr['danger_color'], '<i class="bi bi-circle-fill text-danger"></i> ' . t('Current color, leave empty for default')),
			'$dark_mode' => array('adminlte_dark_mode',t('Default to dark mode'),$arr['dark_mode'], '', array(t('No'),t('Yes'))),
			'$sidebar_mode' => array('adminlte_sidebar_mode',t('Choose sidebar mode'),$arr['sidebar_mode'], '', array(t('Expanded'),t('Collapsed'))),
			'$bg_mode' => array('adminlte_bg_mode',t('Set background image tile mode'),$arr['bg_mode'], '', array(t('Tiled'),t('Cover'))),
			'$bgcolor' => array('adminlte_background_color', t('Set the background color(e.g. #ffffff, blank for default)'), $arr['bgcolor']),
			'$bgcolor_dark' => array('adminlte_background_color_dark', t('Set the dark background color(e.g. #000000, blank for default)'), $arr['bgcolor_dark']),
			'$background_image' => array('adminlte_background_image', t('Set the background image(url link, blank for none)'), $arr['background_image']),
			'$background_image_dark' => array('adminlte_background_image_dark', t('Set the dark background image(url link, blank for none)'), $arr['background_image_dark']),
			'$tourhq' => array('adminlte_tourhq', t('HQ tour completed'), $arr['tourhq']),
//			'$converse_width' => array('adminlte_converse_width',t('Set maximum width of content region in rem'),$arr['converse_width'], t('Leave empty for default width')),
//			'$advanced_theming' => ['adminlte_advanced_theming', t('Show advanced settings'), $arr['advanced_theming'], '', [t('No'), t('Yes')]]
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

  function adminlte_theme_admin_enable() {
    register_hook('page_end', 'view/theme/adminlte/hooks/tours.php', 'adminlte_tours');
    register_hook('nav', 'view/theme/adminlte/hooks/layout.php', 'notification_nav');
    Route::register('view/theme/adminlte/mod/Mod_adminlte.php', 'adminlte');
    Route::register('view/theme/adminlte/mod/Mod_adminlte.php', 'test');

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
        if (Config::Get('theme_adminlte', $k) === false) {
            Config::Set('theme_adminlte', $k, $v);
        }
    }
  }

  function adminlte_theme_admin_disable() {
    unregister_hook('page_end', 'view/theme/adminlte/hooks/tours.php', 'adminlte_tours');
    unregister_hook('nav', 'view/theme/adminlte/hooks/layout.php', 'notification_nav');
    Route::unregister('view/theme/adminlte/mod/Mod_adminlte.php', 'adminlte');
    Route::unregister('view/theme/adminlte/mod/Mod_adminlte.php', 'test');
  }

  function adminlte_get_schemas() {
      $files = glob('view/theme/adminlte/schema/*.css');
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

  function theme_admin(&$a) {
      $schema   = Config::Get('theme_adminlte', 'schema', '---');
      $schemas  = adminlte_get_schemas();
      // Load system-level (admin) theme config
      $dark_mode   = Config::Get('theme_adminlte', 'dark_mode', 0);
      $sidebar     = Config::Get('theme_adminlte', 'sidebar_mode', 0);
      $bg_mode     = Config::Get('theme_adminlte', 'bg_mode', 0);
      $bgcolor     = Config::Get('theme_adminlte', 'background_color', '');
      $bgcolor_dark = Config::Get('theme_adminlte', 'background_color_dark', '');
      $bg_image     = Config::Get('theme_adminlte', 'background_image', '');
      $logo     = Config::Get('theme_adminlte', 'logo', '/view/theme/adminlte/img/hz.png');
      $bg_image_dark = Config::Get('theme_adminlte', 'background_image_dark', '');

      /* $t = get_markup_template('adminlte_admin.tpl'); */
			$t = file_get_contents(__DIR__ . '/../tpl/theme_settings_admin.tpl');

      return replace_macros($t, [

          '$title' => t('AdminLTE Theme Settings (System Defaults)'),
          '$schema' => [
              'schema',
              t('Default scheme'),
              $schema,
              '',
              $schemas
          ],
          '$dark_mode' => [
              'dark_mode',
              t('Default color mode'),
              $dark_mode,
              '',
              [0 => t('Light'), 1 => t('Dark')]
          ],

          '$sidebar_mode' => [
              'sidebar_mode',
              t('Default sidebar mode'),
              $sidebar,
              '',
              [0 => t('Expanded'), 1 => t('Collapsed')]
          ],

          '$bg_mode' => [
              'bg_mode',
              t('Background image mode'),
              $bg_mode,
              '',
              [0 => t('Tile'), 1 => t('Cover')]
          ],

          '$background_color' => [
              'background_color',
              t('Background color (light mode)'),
              $bgcolor,
              t('Leave empty for default')
          ],

          '$background_color_dark' => [
              'background_color_dark',
              t('Background color (dark mode)'),
              $bgcolor_dark,
              t('Leave empty for default')
          ],
          '$logo' => [
              'logo',
              t('Logo image URL'),
              $logo,
              t('Leave empty for none')
          ],

          '$background_image' => [
              'background_image',
              t('Background image URL (light mode)'),
              $bg_image,
              t('Leave empty for none')
          ],

          '$background_image_dark' => [
              'background_image_dark',
              t('Background image URL (dark mode)'),
              $bg_image_dark,
              t('Leave empty for none')
          ],

          '$submit' => t('Submit'),
          '$form_security_token' => get_form_security_token('admin_themes'),
      ]);
  }

  function theme_admin_post() {
      check_form_security_token_redirectOnErr('/admin/themes/adminlte', 'admin_themes');

      // Save all system admin settings
      Config::Set('theme_adminlte', 'dark_mode', intval($_POST['dark_mode']));
      Config::Set('theme_adminlte', 'sidebar_mode', intval($_POST['sidebar_mode']));
      Config::Set('theme_adminlte', 'bg_mode', intval($_POST['bg_mode']));
      Config::Set('theme_adminlte', 'background_color', trim($_POST['background_color']));
      Config::Set('theme_adminlte', 'background_color_dark', trim($_POST['background_color_dark']));
      Config::Set('theme_adminlte', 'background_image', trim($_POST['background_image']));
      Config::Set('theme_adminlte', 'logo', trim($_POST['logo']));
      Config::Set('theme_adminlte', 'background_image_dark', trim($_POST['background_image_dark']));

      Config::Set('theme_adminlte', 'schema', $_POST['schema']);
      info(t('Theme settings updated.'));
  }
}
