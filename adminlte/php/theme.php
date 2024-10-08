<?php

/**
 *   * Name: AdminLTE
 *   * Description: AdminLTE theme adapted  for Utsukta Hub
 *   * Version: 0.1 alpha
 *   * MinVersion: 7.2
 *   * MaxVersion: 10.0
 *   * Author: SK
 *   * Maintainer: SK
 *   * Compat: Hubzilla [*]
 *
 */

// When you create a new theme, don't forget to edit the information above.
// If you change the name of the theme to `yournewname` change `redbasicchild_init` to `yournewname_init` so it has a unique name.
// You will also need to edit the style.php file if you change the directory name.

function adminlte_init() {

    App::$theme_info['extends'] = 'redbasic';

}
