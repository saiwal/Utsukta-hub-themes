<?php

namespace Zotlabs\Theme;

use ScssPhp\ScssPhp\Compiler;
use ScssPhp\ScssPhp\OutputStyle;


class RedbasicConfig {

	function get_schemas() {
		$files = glob('view/theme/adminlte/schema/*.php');

		$scheme_choices = [];

		if($files) {

			if(in_array('view/theme/adminlte/schema/default.php', $files)) {
				$scheme_choices['---'] = t('Default');
				$scheme_choices['schema1'] = t('Schem1');
			}
			else {
				$scheme_choices['---'] = t('Schema1');
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
