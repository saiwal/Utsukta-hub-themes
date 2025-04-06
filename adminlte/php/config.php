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
		$arr['dark_mode'] = get_pconfig(local_channel(),'adminlte', 'dark_mode');
		$arr['navbar_dark_mode'] = get_pconfig(local_channel(),'adminlte', 'navbar_dark_mode');
		$arr['narrow_navbar'] = get_pconfig(local_channel(),'adminlte', 'narrow_navbar' );
		$arr['nav_bg'] = get_pconfig(local_channel(),'adminlte', 'nav_bg' );
		$arr['nav_bg_dark'] = get_pconfig(local_channel(),'adminlte', 'nav_bg_dark' );
		$arr['bgcolor'] = get_pconfig(local_channel(),'adminlte', 'background_color' );
		$arr['bgcolor_dark'] = get_pconfig(local_channel(),'adminlte', 'background_color_dark' );
		$arr['background_image'] = get_pconfig(local_channel(),'adminlte', 'background_image' );
		$arr['background_image_dark'] = get_pconfig(local_channel(),'adminlte', 'background_image_dark' );
		$arr['font_size'] = get_pconfig(local_channel(),'adminlte', 'font_size' );
		$arr['radius'] = get_pconfig(local_channel(),'adminlte', 'radius' );
		$arr['converse_width']=get_pconfig(local_channel(),"adminlte","converse_width");
		$arr['top_photo']=get_pconfig(local_channel(),"adminlte","top_photo");
		$arr['reply_photo']=get_pconfig(local_channel(),"adminlte","reply_photo");
		$arr['advanced_theming'] = get_pconfig(local_channel(), 'adminlte', 'advanced_theming');
		return $this->form($arr);
	}

	function post() {
		if(!local_channel()) {
			return;
		}

		set_pconfig(local_channel(), 'adminlte', 'schema', $_POST['schema']);
		set_pconfig(local_channel(), 'system', 'style_update', time());
		/*if (isset($_POST['adminlte-settings-submit'])) {*/
		/*	if (isset($_POST['adminlte_primary_color']) || isset($_POST['adminlte_radius'])) {*/
		/**/
		/*		$primary_color = '';*/
		/*		$success_color = '';*/
		/*		$info_color = '';*/
		/*		$warning_color = '';*/
		/*		$danger_color = '';*/
		/*		$radius = floatval($_POST['adminlte_radius']);*/
		/**/
		/*		if (preg_match('/^#([A-Fa-f0-9]{3}){1,2}$/', $_POST['adminlte_primary_color'])) {*/
		/*			$primary_color = $_POST['adminlte_primary_color'];*/
		/*		}*/
		/*		if (preg_match('/^#([A-Fa-f0-9]{3}){1,2}$/', $_POST['adminlte_success_color'])) {*/
		/*			$success_color = $_POST['adminlte_success_color'];*/
		/*		}*/
		/*		if (preg_match('/^#([A-Fa-f0-9]{3}){1,2}$/', $_POST['adminlte_info_color'])) {*/
		/*			$info_color = $_POST['adminlte_info_color'];*/
		/*		}*/
		/*		if (preg_match('/^#([A-Fa-f0-9]{3}){1,2}$/', $_POST['adminlte_warning_color'])) {*/
		/*			$warning_color = $_POST['adminlte_warning_color'];*/
		/*		}*/
		/*		if (preg_match('/^#([A-Fa-f0-9]{3}){1,2}$/', $_POST['adminlte_danger_color'])) {*/
		/*			$danger_color = $_POST['adminlte_danger_color'];*/
		/*		}*/
		/**/
		/*		if ($primary_color || $success_color || $info_color || $warning_color || $danger_color || $radius) {*/
		/**/
		/*			try {*/
		/*				$cache_dir = 'store/[data]/[scss]/';*/
		/*				if(!is_dir($cache_dir)) {*/
		/*					os_mkdir($cache_dir, STORAGE_DEFAULT_PERMISSIONS, true);*/
		/*				}*/
		/**/
		/*				$options = [*/
		/*					'cacheDir' => $cache_dir,*/
		/*					'prefix' => 'adminlte_',*/
		/*					'forceRefresh' => false*/
		/*				];*/
		/**/
		/*				$compiler = new Compiler($options);*/
		/*				$compiler->setImportPaths('vendor/twbs/bootstrap/scss');*/
		/*				$compiler->setOutputStyle(OutputStyle::COMPRESSED);*/
		/**/
		/*				// Set Variables*/
		/*				if ($primary_color) {*/
		/*					$variables['$primary'] = $primary_color;*/
		/*				}*/
		/*				if ($success_color) {*/
		/*					$variables['$success'] = $success_color;*/
		/*				}*/
		/*				if ($info_color) {*/
		/*					$variables['$info'] = $info_color;*/
		/*				}*/
		/*				if ($warning_color) {*/
		/*					$variables['$warning'] = $warning_color;*/
		/*				}*/
		/*				if ($danger_color) {*/
		/*					$variables['$danger'] = $danger_color;*/
		/*				}*/
		/*				if ($radius) {*/
		/*					$variables['$border-radius'] = $radius . 'rem';*/
		/*					$variables['$border-radius-sm'] = $radius/1.5 . 'rem';*/
		/*					$variables['$border-radius-lg'] = $radius*1.333 . 'rem';*/
		/*					$variables['$border-radius-xl'] = $radius*2.666 . 'rem';*/
		/*					$variables['$border-radius-xxl'] = $radius*5.333 . 'rem';*/
		/*				}*/
		/**/
		/*				// Replace Bootstrap Variables with Customizer Variables*/
		/*				$compiler->replaceVariables($variables);*/
		/**/
		/*				$bs = $compiler->compileString('@import "bootstrap.scss";');*/
		/**/
		/*				set_pconfig(local_channel(), 'adminlte', 'bootstrap', $bs->getCss());*/
		/*			} catch (\Exception $e) {*/
		/*				logger('scssphp: Unable to compile content');*/
		/*			}*/
		/*		}*/
		/*		else {*/
		/*			set_pconfig(local_channel(), 'adminlte', 'bootstrap', '');*/
		/*		}*/
		/*	}*/
		/**/
		/*	set_pconfig(local_channel(), 'adminlte', 'primary_color', $_POST['adminlte_primary_color']);*/
		/*	set_pconfig(local_channel(), 'adminlte', 'success_color', $_POST['adminlte_success_color']);*/
		/*	set_pconfig(local_channel(), 'adminlte', 'info_color', $_POST['adminlte_info_color']);*/
		/*	set_pconfig(local_channel(), 'adminlte', 'warning_color', $_POST['adminlte_warning_color']);*/
		/*	set_pconfig(local_channel(), 'adminlte', 'danger_color', $_POST['adminlte_danger_color']);*/
		/**/
		/*	set_pconfig(local_channel(), 'adminlte', 'narrow_navbar', $_POST['adminlte_narrow_navbar']);*/
		/*	set_pconfig(local_channel(), 'adminlte', 'navbar_dark_mode', $_POST['adminlte_navbar_dark_mode']);*/
		/*	set_pconfig(local_channel(), 'adminlte', 'dark_mode', $_POST['adminlte_dark_mode']);*/
		/*	set_pconfig(local_channel(), 'adminlte', 'nav_bg', $_POST['adminlte_nav_bg']);*/
		/*	set_pconfig(local_channel(), 'adminlte', 'nav_bg_dark', $_POST['adminlte_nav_bg_dark']);*/
		/*	set_pconfig(local_channel(), 'adminlte', 'background_color', $_POST['adminlte_background_color']);*/
		/*	set_pconfig(local_channel(), 'adminlte', 'background_color_dark', $_POST['adminlte_background_color_dark']);*/
		/*	set_pconfig(local_channel(), 'adminlte', 'background_image', $_POST['adminlte_background_image']);*/
		/*	set_pconfig(local_channel(), 'adminlte', 'background_image_dark', $_POST['adminlte_background_image_dark']);*/
		/*	set_pconfig(local_channel(), 'adminlte', 'font_size', $_POST['adminlte_font_size']);*/
		/*	set_pconfig(local_channel(), 'adminlte', 'radius', $_POST['adminlte_radius']);*/
		/*	set_pconfig(local_channel(), 'adminlte', 'converse_width', $_POST['adminlte_converse_width']);*/
		/*	set_pconfig(local_channel(), 'adminlte', 'top_photo', $_POST['adminlte_top_photo']);*/
		/*	set_pconfig(local_channel(), 'adminlte', 'reply_photo', $_POST['adminlte_reply_photo']);*/
		/*	set_pconfig(local_channel(), 'adminlte', 'advanced_theming', $_POST['adminlte_advanced_theming']);*/
		/**/
			// This is used to refresh the cache
			/*set_pconfig(local_channel(), 'system', 'style_update', time());*/
		/*}*/
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
			'$primary_color' => array('adminlte_primary_color', t('Primary theme color'), $arr['primary_color'], '<i class="bi bi-circle-fill text-primary"></i> ' . t('Current color, leave empty for default')),
			'$success_color' => array('adminlte_success_color', t('Success theme color'), $arr['success_color'], '<i class="bi bi-circle-fill text-success"></i> ' . t('Current color, leave empty for default')),
			'$info_color' => array('adminlte_info_color', t('Info theme color'), $arr['info_color'], '<i class="bi bi-circle-fill text-info"></i> ' . t('Current color, leave empty for default')),
			'$warning_color' => array('adminlte_warning_color', t('Warning theme color'), $arr['warning_color'], '<i class="bi bi-circle-fill text-warning"></i> ' . t('Current color, leave empty for default')),
			'$danger_color' => array('adminlte_danger_color', t('Danger theme color'), $arr['danger_color'], '<i class="bi bi-circle-fill text-danger"></i> ' . t('Current color, leave empty for default')),
			'$dark_mode' => array('adminlte_dark_mode',t('Default to dark mode'),$arr['dark_mode'], '', array(t('No'),t('Yes'))),
			'$bgcolor' => array('adminlte_background_color', t('Set the background color'), $arr['bgcolor']),
			'$bgcolor_dark' => array('adminlte_background_color_dark', t('Set the dark background color'), $arr['bgcolor_dark']),
			'$background_image' => array('adminlte_background_image', t('Set the background image'), $arr['background_image']),
			'$background_image_dark' => array('adminlte_background_image_dark', t('Set the dark background image'), $arr['background_image_dark']),
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
