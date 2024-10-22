<?php

namespace Zotlabs\Theme;

class RedbasicConfig {

	function get_schemas() {
		$files = glob('view/theme/utsukta-default/schema/*.php');

		$scheme_choices = [];

		if($files) {

			if(in_array('view/theme/utsukta-default/schema/default.php', $files)) {
				$scheme_choices['---'] = t('Default');
				$scheme_choices['focus'] = t('Focus (Hubzilla default)');
			}
			else {
				$scheme_choices['---'] = t('Focus (Hubzilla default)');
			}

			foreach($files as $file) {
				$f = basename($file, ".php");
				if($f != 'default') {
					$scheme_name = $f;
					$scheme_choices[$f] = $scheme_name;
				}
			}
		}

		return $scheme_choices;
	}
}