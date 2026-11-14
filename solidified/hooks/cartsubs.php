<?php

namespace {

	/**
	 * cron — ends cart subscriptions whose paid period has run out (taking
	 * back the access they granted) and deletes abandoned carts.
	 * Logic: spa-core Api/Handlers/Cart.php.
	 */
	function solidified_cartsubs_cron(&$arr) {
		require_once __DIR__ . '/../vendor/autoload.php';
		\Utsukta\SpaCore\Api\Handlers\Cart::onCron();
	}
}
