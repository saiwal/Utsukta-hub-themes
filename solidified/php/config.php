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
			'widget_shell'  => t('Widget style'),
			'glass_ui'      => t('Translucent surfaces (0 = off, 1 = on)'),
		];
		$o = '<input type="hidden" name="form_security_token" value="' . get_form_security_token('admin_themes') . '" />'
			. '<p>' . t('Hub-wide defaults for every SPA theme. They apply to visitors and to members who have not chosen their own; leave blank for the built-in default.') . '</p>';
		foreach (Utsukta\SpaCore\Api\HubDefaults::CHOICES as $k => $choices) {
			if ($k === 'color_scheme')
				$choices = Utsukta\SpaCore\Api\ColorSchemes::ALL;
			$field = [$k, $labels[$k], Config::Get('spa', $k, ''), t('Built-in: ') . Utsukta\SpaCore\Api\HubDefaults::BUILTIN[$k]];
			if ($choices === null) {
				$o .= replace_macros(get_markup_template('field_input.tpl'), ['$field' => $field]);
			} else {
				$field[] = ['' => t('Built-in default')] + array_combine($choices, $choices);
				$o .= replace_macros(get_markup_template('field_select.tpl'), ['$field' => $field]);
			}
			if ($k === 'color_scheme') $o .= solidified_custom_colors_fields();
		}
		return $o . '<button type="submit" class="btn btn-primary">' . t('Submit') . '</button>';
  }

  // Colour set for the 'custom' scheme — same JSON shape as a user's own
  // pconfig spa/custom_theme_colors (base/txt/accent/isDark).
  function solidified_custom_colors_fields() {
		$c = json_decode((string) Config::Get('spa', 'custom_theme_colors', ''), true) ?: [];
		$c += ['base' => '#1e1e2e', 'txt' => '#cdd6f4', 'accent' => '#cba6f7', 'isDark' => true];
		$o = '<fieldset class="mb-3"><legend class="fs-6">' . t('Custom color scheme (used when the color scheme is "custom")') . '</legend><div class="d-flex flex-wrap gap-3">';
		foreach (['base' => t('Background'), 'txt' => t('Text'), 'accent' => t('Accent')] as $k => $label) {
			$o .= '<label class="d-flex align-items-center gap-2"><input type="color" name="custom_' . $k . '" value="' . htmlspecialchars($c[$k]) . '" /> ' . $label . '</label>';
		}
		return $o . '<label class="d-flex align-items-center gap-2"><input type="checkbox" name="custom_isDark" value="1"' . ($c['isDark'] ? ' checked' : '') . ' /> ' . t('Dark') . '</label></div></fieldset>';
  }

  function theme_admin_post() {
		check_form_security_token_redirectOnErr('/admin/themes', 'admin_themes');
		require_once __DIR__ . '/../vendor/autoload.php';
		// Before the loop: clean('color_scheme', 'custom') needs a saved colour set.
		if (isset($_POST['custom_base'])) {
			$json = json_encode([
				'base' => $_POST['custom_base'], 'txt' => $_POST['custom_txt'] ?? '',
				'accent' => $_POST['custom_accent'] ?? '', 'isDark' => !empty($_POST['custom_isDark']),
			]);
			if (($json = Utsukta\SpaCore\Api\HubDefaults::cleanCustomColors($json)) !== null)
				Config::Set('spa', 'custom_theme_colors', $json);
		}
		foreach (array_keys(Utsukta\SpaCore\Api\HubDefaults::CHOICES) as $k) {
			if (!isset($_POST[$k])) continue;
			// Blank or invalid clears the default back to the built-in one.
			Config::Set('spa', $k, Utsukta\SpaCore\Api\HubDefaults::clean($k, $_POST[$k]) ?? '');
		}
	}
}
