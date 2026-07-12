<?php

namespace {

	use Zotlabs\Lib\Config;

	/**
	 * Hooked on Zotlabs\Lib\Enotify's "enotify_store_end" (registered in
	 * ../php/config.php). Fires for every finalised in-app notification
	 * (comment, like, DM, mention, wall post, etc.) and fans it out to any
	 * Web Push subscriptions the recipient has registered via the SPA's
	 * POST /api/push-subscription (stored in pconfig cat "webpush").
	 *
	 * $datarray fields used here (see Zotlabs\Lib\Enotify::submit()):
	 *   uid (recipient channel_id), xname (sender display name), msg (final
	 *   notification text), photo (sender avatar), link (permalink), hash.
	 */
	function solidified_webpush_send(&$datarray) {

		$uid = intval($datarray['uid'] ?? 0);
		if (!$uid) {
			return;
		}

		$raw = get_pconfig($uid, 'webpush', 'subscriptions');
		if (!$raw) {
			return;
		}

		$subs = json_decode($raw, true);
		if (!is_array($subs) || !$subs) {
			return;
		}

		$publicKey = Config::Get('system', 'webpush_vapid_public');
		$privateKey = Config::Get('system', 'webpush_vapid_private');
		if (!$publicKey || !$privateKey) {
			// No one has ever loaded the subscribe UI (which lazily generates
			// the keypair), so there is nothing valid to sign with yet.
			return;
		}

		require_once __DIR__ . '/../vendor/autoload.php';

		try {
			$webPush = new \Minishlink\WebPush\WebPush([
				'VAPID' => [
					'subject' => z_root(),
					'publicKey' => $publicKey,
					'privateKey' => $privateKey,
				],
			]);
		} catch (\Throwable $e) {
			logger('webpush: failed to init WebPush client: ' . $e->getMessage());
			return;
		}

		$payload = json_encode([
			'title' => $datarray['xname'] ?? 'Hubzilla',
			'body' => strip_tags($datarray['msg'] ?? ''),
			'icon' => $datarray['photo'] ?? '',
			'url' => $datarray['link'] ?? z_root(),
			'tag' => $datarray['hash'] ?? '',
		]);

		foreach ($subs as $endpoint => $sub) {
			try {
				$webPush->queueNotification(\Minishlink\WebPush\Subscription::create($sub), $payload);
			} catch (\Throwable $e) {
				logger('webpush: failed to queue for ' . $endpoint . ': ' . $e->getMessage());
				unset($subs[$endpoint]);
			}
		}

		$stale = [];
		foreach ($webPush->flush() as $report) {
			if ($report->isSuccess()) {
				continue;
			}
			$endpoint = $report->getEndpoint();
			if ($report->isSubscriptionExpired()) {
				$stale[] = $endpoint;
			} else {
				logger('webpush: delivery failed for ' . $endpoint . ': ' . $report->getReason());
			}
		}

		if ($stale) {
			foreach ($stale as $endpoint) {
				unset($subs[$endpoint]);
			}
			set_pconfig($uid, 'webpush', 'subscriptions', json_encode($subs));
		}
	}

}
