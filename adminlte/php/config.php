<?php

namespace Zotlabs\Theme;

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
		$arr['tour'] = get_pconfig(local_channel(),'adminlte', 'tour_done' );
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
			set_pconfig(local_channel(), 'adminlte', 'tour_done', $_POST['adminlte_tour']);
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

		$istour = false;
   # dirty way to check if adminlte_tour addon is installed 
    if (file_exists(__DIR__ . '/../../../../addon/adminlte_tour/adminlte_tour.php')) {
        $istour = true;
    } 

	  	$o = replace_macros(get_markup_template('theme_settings.tpl'), array(
			'$submit' => t('Submit'),
			'$baseurl' => z_root(),
			'$theme' => \App::$channel['channel_theme'],
      '$expert' => $expert,
      '$istour' => $istour,
			'$title' => t("Theme settings"),
			'$dark' => t('Dark style'),
			'$light' => t('Light style'),
			'$common' => t('Common settings'),
			'$primary_color' => array('adminlte_primary_color', t('Primary theme color'), $arr['primary_color'], '<i class="bi bi-circle-fill text-primary"></i> ' . t('Current color, leave empty for default')),
			'$success_color' => array('adminlte_success_color', t('Success theme color'), $arr['success_color'], '<i class="bi bi-circle-fill text-success"></i> ' . t('Current color, leave empty for default')),
			'$info_color' => array('adminlte_info_color', t('Info theme color'), $arr['info_color'], '<i class="bi bi-circle-fill text-info"></i> ' . t('Current color, leave empty for default')),
			'$warning_color' => array('adminlte_warning_color', t('Warning theme color'), $arr['warning_color'], '<i class="bi bi-circle-fill text-warning"></i> ' . t('Current color, leave empty for default')),
			'$danger_color' => array('adminlte_danger_color', t('Danger theme color'), $arr['danger_color'], '<i class="bi bi-circle-fill text-danger"></i> ' . t('Current color, leave empty for default')),
			'$dark_mode' => array('adminlte_dark_mode',t('Default to dark mode'),$arr['dark_mode'], '', array(t('No'),t('Yes'))),
			'$sidebar_mode' => array('adminlte_sidebar_mode',t('Choose sidebar mode'),$arr['sidebar_mode'], '', array(t('Expanded'),t('Collapsed'))),
			'$bg_mode' => array('adminlte_bg_mode',t('Set background image tile mode'),$arr['bg_mode'], '', array(t('Tiled'),t('Cover'))),
			'$bgcolor' => array('adminlte_background_color', t('Set the background color(e.g. #ffffff, blank for default)'), $arr['bgcolor']),
			'$bgcolor_dark' => array('adminlte_background_color_dark', t('Set the dark background color(e.g. #000000, blank for default)'), $arr['bgcolor_dark']),
			'$background_image' => array('adminlte_background_image', t('Set the background image(url link, blank for none)'), $arr['background_image']),
			'$background_image_dark' => array('adminlte_background_image_dark', t('Set the dark background image(url link, blank for none)'), $arr['background_image_dark']),
			'$tour' => array('adminlte_tour', t('Welcome tour completed'), $arr['tour']),
			'$converse_width' => array('adminlte_converse_width',t('Set maximum width of content region in rem'),$arr['converse_width'], t('Leave empty for default width')),
			'$advanced_theming' => ['adminlte_advanced_theming', t('Show advanced settings'), $arr['advanced_theming'], '', [t('No'), t('Yes')]]
			));

		return $o;
	}

}

function adminlte_theme_admin_enable() {
	// This function is called once when the theme is being enabled by the admin
	// It can be used to register hooks etc.
}

function adminlte_theme_admin_disable() {
	// This function is called once when the theme is being disabled by the admin
	// It can be used to unregister hooks etc.
}
