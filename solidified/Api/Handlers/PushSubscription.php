<?php
namespace Theme\Solidified\Api\Handlers;

use Theme\Solidified\Api\Auth;
use Theme\Solidified\Api\Response;
use Zotlabs\Lib\Config;

/**
 * GET    /api/push-subscription → { publicKey } — VAPID public key, generated
 *        (and persisted site-wide via Config) on first request if missing.
 * POST   /api/push-subscription → body { subscription: PushSubscriptionJSON }
 *        stores/updates one browser's subscription for the local user.
 * DELETE /api/push-subscription → body { endpoint } — drops one subscription
 *        (e.g. on opt-out, or when the browser reports it as expired).
 *
 * Subscriptions are stored per user as a JSON object keyed by endpoint URL in
 * pconfig cat "webpush" key "subscriptions" — same pattern as WidgetLayout.php.
 * Sent via minishlink/web-push from the theme's own hooks/webpush.php, which
 * hooks Zotlabs\Lib\Enotify's "enotify_store_end" (see php/config.php).
 */
class PushSubscription
{
    private const MAX_SUBS_PER_USER = 12;

    public function get(): void
    {
        Auth::requireLocalGet();
        Response::send(['publicKey' => self::vapidPublicKey()]);
    }

    public function post(): void
    {
        $uid = Auth::requireLocalJson();
        $sub = Auth::$parsedBody['subscription'] ?? null;

        if (!self::isValidSubscription($sub)) {
            Response::error(400, 'Invalid subscription');
        }

        $subs = self::loadSubs($uid);
        $subs[$sub['endpoint']] = $sub;
        if (count($subs) > self::MAX_SUBS_PER_USER) {
            array_shift($subs);
        }
        set_pconfig($uid, 'webpush', 'subscriptions', json_encode($subs));
        Response::send(['status' => 'ok']);
    }

    public function delete(): void
    {
        $uid = Auth::requireLocalJson();
        $endpoint = Auth::$parsedBody['endpoint'] ?? null;

        if (!is_string($endpoint) || $endpoint === '') {
            Response::error(400, 'Missing endpoint');
        }

        $subs = self::loadSubs($uid);
        unset($subs[$endpoint]);
        set_pconfig($uid, 'webpush', 'subscriptions', json_encode($subs));
        Response::send(['status' => 'ok']);
    }

    private static function loadSubs(int $uid): array
    {
        $raw = get_pconfig($uid, 'webpush', 'subscriptions');
        $subs = $raw ? json_decode($raw, true) : [];
        return is_array($subs) ? $subs : [];
    }

    private static function isValidSubscription($sub): bool
    {
        return is_array($sub)
            && is_string($sub['endpoint'] ?? null) && $sub['endpoint'] !== ''
            && is_array($sub['keys'] ?? null)
            && is_string($sub['keys']['p256dh'] ?? null) && $sub['keys']['p256dh'] !== ''
            && is_string($sub['keys']['auth'] ?? null) && $sub['keys']['auth'] !== '';
    }

    private static function vapidPublicKey(): string
    {
        $pub = Config::Get('system', 'webpush_vapid_public');
        $priv = Config::Get('system', 'webpush_vapid_private');
        if ($pub && $priv) {
            return $pub;
        }

        require_once __DIR__ . '/../../vendor/autoload.php';
        $keys = \Minishlink\WebPush\VAPID::createVapidKeys();
        Config::Set('system', 'webpush_vapid_public', $keys['publicKey']);
        Config::Set('system', 'webpush_vapid_private', $keys['privateKey']);
        return $keys['publicKey'];
    }
}
