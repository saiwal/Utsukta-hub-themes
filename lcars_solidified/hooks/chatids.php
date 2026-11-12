<?php

namespace {

	/**
	 * Matrix / Delta Chat addresses as ActivityPub actor fields — see spa-core
	 * Api/ChatAddresses.php. Registered by SpaTheme::HOOKS on theme enable.
	 */
	function lcars_solidified_chatids_encode_person(&$arr) {
		require_once __DIR__ . '/../vendor/autoload.php';
		\Utsukta\SpaCore\Api\ChatAddresses::encodePerson($arr);
	}
}
