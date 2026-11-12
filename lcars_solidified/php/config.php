<?php

namespace { 

  use Zotlabs\Lib\Config;

	// /spa + shared hooks go to one enabled spa-core theme at a time (see SpaTheme).
	function lcars_solidified_theme_admin_enable() {
		require_once __DIR__ . '/../vendor/autoload.php';
		Utsukta\SpaCore\Api\SpaTheme::enable('lcars_solidified');
  }

  function lcars_solidified_theme_admin_disable() {
		require_once __DIR__ . '/../vendor/autoload.php';
		Utsukta\SpaCore\Api\SpaTheme::disable('lcars_solidified');
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
			if ($k === 'color_scheme') $o .= lcars_solidified_custom_colors_fields();
		}
		return $o . '<button type="submit" class="btn btn-primary">' . t('Submit') . '</button>';
  }

  // Colour set for the 'custom' scheme — same JSON shape as a user's own
  // pconfig spa/custom_theme_colors. The pickers cover the three base colours;
  // the full set (every token override) comes from copying the admin's own
  // scheme, designed in the SPA's editor.
  function lcars_solidified_custom_colors_fields() {
		$c = json_decode((string) Config::Get('spa', 'custom_theme_colors', ''), true) ?: [];
		$c += ['base' => '#1e1e2e', 'txt' => '#cdd6f4', 'accent' => '#cba6f7', 'isDark' => true];
		$overrides = count(array_diff_key($c, array_flip(['base', 'txt', 'accent', 'isDark'])));
		$o = '<fieldset class="spa-custom-colors mb-3"><legend class="fs-6 mb-0">' . t('Custom color scheme') . '</legend>'
			. '<small class="form-text">' . t('Used when the color scheme is "custom".') . '</small>'
			. '<div class="spa-swatches d-flex flex-wrap align-items-center gap-3 mt-2">';
		foreach (['base' => t('Background'), 'txt' => t('Text'), 'accent' => t('Accent')] as $k => $label) {
			$o .= '<label class="spa-swatch d-flex align-items-center gap-2"><input type="color" class="form-control form-control-color" name="custom_' . $k . '" value="' . htmlspecialchars($c[$k]) . '" /> ' . $label . '</label>';
		}
		$o .= '<label class="spa-swatch d-flex align-items-center gap-2"><input type="checkbox" name="custom_isDark" value="1"' . ($c['isDark'] ? ' checked' : '') . ' /> ' . t('Dark') . '</label></div>';
		if ($overrides)
			$o .= '<small class="form-text">' . sprintf(t('Plus %d fine-tuned colors. Changing a color above replaces them with derived ones.'), $overrides) . '</small>';
		$mine = Utsukta\SpaCore\Api\HubDefaults::cleanCustomColors(get_pconfig(local_channel(), 'spa', 'custom_theme_colors', ''));
		$o .= '<div class="spa-copy mt-3"><button type="submit" name="custom_copy_mine" value="1" class="btn btn-sm btn-outline-secondary"' . ($mine ? '' : ' disabled') . '>'
			. t('Use my custom color scheme') . '</button><small class="form-text d-block">'
			. ($mine ? t('Copies every color from your own scheme (Settings → Display) and makes it the hub default.')
				: t('Set up a custom scheme in Settings → Display first.')) . '</small></div>';
		return $o . '</fieldset>';
  }

  function theme_admin_post() {
		check_form_security_token_redirectOnErr('/admin/themes', 'admin_themes');
		require_once __DIR__ . '/../vendor/autoload.php';
		$H = Utsukta\SpaCore\Api\HubDefaults::class;
		// Colours first: clean('color_scheme', 'custom') needs a saved colour set.
		if (!empty($_POST['custom_copy_mine'])) {
			$mine = $H::cleanCustomColors(get_pconfig(local_channel(), 'spa', 'custom_theme_colors', ''));
			if ($mine !== null) {
				Config::Set('spa', 'custom_theme_colors', $mine);
				$_POST['color_scheme'] = 'custom';
			}
		} elseif (isset($_POST['custom_base'])) {
			$picked = [
				'base' => $_POST['custom_base'], 'txt' => $_POST['custom_txt'] ?? '',
				'accent' => $_POST['custom_accent'] ?? '', 'isDark' => !empty($_POST['custom_isDark']),
			];
			$old = json_decode((string) Config::Get('spa', 'custom_theme_colors', ''), true) ?: [];
			// Untouched pickers keep the stored set, copied overrides included;
			// a changed base colour drops overrides derived from the old one.
			$lc = fn($a) => array_map(fn($v) => is_string($v) ? strtolower($v) : $v, $a);
			$same = $lc(array_intersect_key($old, $picked)) === $lc($picked);
			if (!$same && ($json = $H::cleanCustomColors(json_encode($picked))) !== null)
				Config::Set('spa', 'custom_theme_colors', $json);
		}
		foreach (array_keys(Utsukta\SpaCore\Api\HubDefaults::CHOICES) as $k) {
			if (!isset($_POST[$k])) continue;
			// Blank or invalid clears the default back to the built-in one.
			Config::Set('spa', $k, Utsukta\SpaCore\Api\HubDefaults::clean($k, $_POST[$k]) ?? '');
		}
	}
}
