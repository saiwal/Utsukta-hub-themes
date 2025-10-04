<?php
use \Zotlabs\Lib\Apps;
use \Zotlabs\Lib\Chatroom;
use \Zotlabs\Lib\Config;

require_once('include/security.php');
require_once('include/menu.php');

function widget_hero($args) {
  $html = file_get_contents(__DIR__ . '/test.html'); // adjust path as needed
  return $html;
}
