<?php

/**
 *   * Name: Vapor
 *   * Description: A cyberpunk aesthetic
 *   * Version: 1.0
 *   * MinVersion: 7.2
 *   * MaxVersion: 11.0
 *   * Author: SK
 *   * Maintainer: SK
 *   * Compat: Hubzilla [*]
 *
 */

// When you create a new theme, don't forget to edit the information above.
// If you change the name of the theme to `yournewname` change `redbasicchild_init` to `yournewname_init` so it has a unique name.
// You will also need to edit the style.php file if you change the directory name.

function vapor_init() {

    App::$theme_info['extends'] = 'redbasic';

}
