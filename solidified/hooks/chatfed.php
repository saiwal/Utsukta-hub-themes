<?php

namespace {

	/**
	 * Chat notices across hubs — see spa-core Api/Handlers/ChatFed.php.
	 * Registered in ../php/config.php; both stay thin so the logic lives in the
	 * shared package, which can't name a theme-slug function itself.
	 */

	/** chat_post (core /chatsvc and the SPA's Chat::sendMessage). */
	function solidified_chatfed_chat_post(&$arr) {
		require_once __DIR__ . '/../vendor/autoload.php';
		\Utsukta\SpaCore\Api\Handlers\ChatFed::onChatPost($arr);
	}

	/** daemon_addon — background jobs queued by ChatFed. */
	function solidified_chatfed_daemon(&$argv) {
		require_once __DIR__ . '/../vendor/autoload.php';
		\Utsukta\SpaCore\Api\Handlers\ChatFed::onDaemon($argv);
	}
}
