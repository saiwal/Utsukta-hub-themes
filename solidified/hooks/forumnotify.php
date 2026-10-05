<?php

namespace {

	/**
	 * Forum DM senders hear about comments on the forum's repost — see
	 * spa-core Api/ForumNotify.php. Registered in ../php/config.php.
	 */
	function solidified_forum_item_stored(&$arr) {
		require_once __DIR__ . '/../vendor/autoload.php';
		\Utsukta\SpaCore\Api\ForumNotify::onItemStored($arr);
	}
}
