<?php

namespace {

	/**
	 * enotify_store_end — drop the blank twin a concurrent Enotify::submit()
	 * leaves behind. Core inserts with msg '' and then re-finds "its" row by
	 * hash+link+ntype LIMIT 1, so when one activity is delivered twice at once
	 * both racers fill in the *first* row and the second stays empty forever
	 * (a blank entry in /sse_bs/notify). Any blank sibling of the row being
	 * finalised here is such an orphan: nothing will ever fill it.
	 * Registered in ../php/config.php.
	 */
	function solidified_notify_race_cleanup(&$datarray) {
		if (empty($datarray['id'])) {
			return;
		}
		q("delete from notify where uid = %d and hash = '%s' and link = '%s' and ntype = %d and id != %d and msg = ''",
			intval($datarray['uid']),
			dbesc($datarray['hash']),
			dbesc($datarray['link']),
			intval($datarray['ntype']),
			intval($datarray['id'])
		);
	}
}
