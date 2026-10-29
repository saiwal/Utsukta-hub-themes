<?php

namespace { 

  use Zotlabs\Lib\Config;

	// /spa + shared hooks go to one enabled spa-core theme at a time (see SpaTheme).
	function solidified_theme_admin_enable() {
		require_once __DIR__ . '/../vendor/autoload.php';
		Utsukta\SpaCore\Api\SpaTheme::enable('solidified');
  }

  function solidified_theme_admin_disable() {
		require_once __DIR__ . '/../vendor/autoload.php';
		Utsukta\SpaCore\Api\SpaTheme::disable('solidified');
  }

  // Hub-wide display defaults (/admin/themes/<this theme>). Core's admin page
  // loads this file by theme name, so it works whatever theme the admin uses.
  // Users who never picked a value get these; their own choice still wins.
  function theme_admin(&$a) {
		require_once __DIR__ . '/../vendor/autoload.php';
		$labels = [
			'color_scheme'  => t('Color scheme'),
			'font_size'     => t('Font size'),
			'font_family'   => t('Font family'),
			'corner_radius' => t('Corner radius'),
			'bg_url'        => t('Background image URL'),
			'bg_fit'        => t('Background image fit'),
			'scroll_style'  => t('Stream scrolling'),
			'post_height'   => t('Collapse long posts at height (px, 0 = never)'),
			'comment_order' => t('Comment order'),
			'composer_mode' => t('Open composers as'),
			'post_mode'     => t('Open posts as'),
			'thread_mode'   => t('Thread display'),
		];
		$o = '<input type="hidden" name="form_security_token" value="' . get_form_security_token('admin_themes') . '" />'
			. '<p>' . t('Hub-wide defaults for every SPA theme. They apply to visitors and to members who have not chosen their own; leave blank for the built-in default.') . '</p>';
		foreach (Utsukta\SpaCore\Api\HubDefaults::CHOICES as $k => $choices) {
			if ($k === 'color_scheme')
				$choices = array_values(array_diff(Utsukta\SpaCore\Api\ColorSchemes::ALL, ['custom']));
			$field = [$k, $labels[$k], Config::Get('spa', $k, ''), t('Built-in: ') . Utsukta\SpaCore\Api\HubDefaults::BUILTIN[$k]];
			if ($choices === null) {
				$o .= replace_macros(get_markup_template('field_input.tpl'), ['$field' => $field]);
			} else {
				$field[] = ['' => t('Built-in default')] + array_combine($choices, $choices);
				$o .= replace_macros(get_markup_template('field_select.tpl'), ['$field' => $field]);
			}
		}
		return $o . '<button type="submit" class="btn btn-primary">' . t('Submit') . '</button>';
  }

  function theme_admin_post() {
		check_form_security_token_redirectOnErr('/admin/themes', 'admin_themes');
		require_once __DIR__ . '/../vendor/autoload.php';
		foreach (array_keys(Utsukta\SpaCore\Api\HubDefaults::CHOICES) as $k) {
			if (!isset($_POST[$k])) continue;
			// Blank or invalid clears the default back to the built-in one.
			Config::Set('spa', $k, Utsukta\SpaCore\Api\HubDefaults::clean($k, $_POST[$k]) ?? '');
		}
	}
}
