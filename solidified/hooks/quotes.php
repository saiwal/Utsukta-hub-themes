<?php

namespace {

	/**
	 * Zot quote-posts — see spa-core Api/QuoteIngest.php. Registered in
	 * ../php/config.php for post_remote and post_remote_update.
	 */
	function solidified_quote_post_remote(&$arr) {
		require_once __DIR__ . '/../vendor/autoload.php';
		\Utsukta\SpaCore\Api\QuoteIngest::onPostRemote($arr);
	}
}
