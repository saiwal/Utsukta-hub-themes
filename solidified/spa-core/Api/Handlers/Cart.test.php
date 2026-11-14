<?php
if (PHP_SAPI !== 'cli') exit;   // deployed into the web root by the build; never runnable over HTTP
/**
 * End-to-end check for Handlers/Cart: an access product from creation to
 * revocation, as two real local channels.
 *
 * What it pins: a product with grants is stored where the cart addon keeps
 * its service items (and moves back when the grants are cleared); the public
 * catalog says *that* it grants access but not which group; paying — here via
 * the seller's "mark paid", the same settlePaid() every gateway calls —
 * confirms the items, stamps the shop currency and puts the buyer in the
 * group; cancelling takes them out again. Then the same for a subscription:
 * one row per buyer+product, an early renewal extends from the old expiry,
 * expireSubscriptions() (the cron hook's body) revokes a lapsed one, and the
 * seller can extend or revoke by hand. Last, money and housekeeping: tax
 * reaches the total every gateway charges, a required buyer option blocks
 * checkout until filled, the summary counts it, and only an expired,
 * un-checked-out cart is purged. Everything it creates is removed.
 *
 * Run inside the Hubzilla install:
 *   ddev exec php core/extend/theme/utsukta-themes/solidified/spa-core/Api/Handlers/Cart.test.php
 *
 * Response::send()/error() exit, so each handler call is its own process.
 */

for ($dir = __DIR__; $dir !== '/'; $dir = dirname($dir)) {
    if (file_exists("$dir/include/cli_startup.php")) { chdir($dir); break; }
}
require_once 'include/cli_startup.php';
cli_startup();

$autoload = __DIR__ . '/../../../vendor/autoload.php';
if (!file_exists($autoload)) {
    fwrite(STDERR, "run this from the deployed theme (expected $autoload)\n");
    exit(2);
}
require_once $autoload;

use Zotlabs\Lib\AccessList;

const GROUP = 'cart-test-group';
const DESC  = 'Cart probe access product';

// Step mode: php Cart.test.php step <actor nick> <get|post> <shop nick> <path/after/nick> <json>
if (($argv[1] ?? '') === 'step') {
    [$actor, $verb, $shop, $path] = [$argv[2], $argv[3], $argv[4], $argv[5]];

    session_id('cart-test');   // local_channel() needs a non-empty session id
    $ch = q("SELECT * FROM channel WHERE channel_address = '%s' LIMIT 1", dbesc($actor));
    $_SESSION['uid']              = intval($ch[0]['channel_id']);
    $_SESSION['authenticated']    = 1;
    $_SESSION['solidified_csrf']  = 'test-token';
    $_SERVER['HTTP_X_CSRF_TOKEN'] = 'test-token';
    $_SERVER['REQUEST_METHOD']    = strtoupper($verb);
    $_SERVER['CONTENT_TYPE']      = 'multipart/form-data';   // parseJsonBody()'s CLI-reachable branch
    $_POST                        = json_decode($argv[6] ?? '{}', true) ?: [];
    $_GET                         = $verb === 'get' ? $_POST : [];

    \App::$channel  = $ch[0];
    \App::$observer = q("SELECT * FROM xchan WHERE xchan_hash = '%s' LIMIT 1", dbesc($ch[0]['channel_hash']))[0];
    \App::$argv     = array_merge(['spa', 'cart', $shop], $path === '-' ? [] : explode('/', $path));
    \App::$argc     = count(\App::$argv);

    (new \Utsukta\SpaCore\Api\Handlers\Cart)->$verb();
    exit;
}

$fail = 0;
function check(string $label, $got, $want = true): void
{
    global $fail;
    if ($got === $want) { echo "ok    $label\n"; return; }
    $fail++;
    echo "FAIL  $label\n      got  " . json_encode($got) . "\n      want " . json_encode($want) . "\n";
}

function step(string $actor, string $verb, string $shop, string $path, array $payload = []): array
{
    $out = shell_exec(implode(' ', array_map('escapeshellarg',
        [PHP_BINARY, __FILE__, 'step', $actor, $verb, $shop, $path ?: '-', json_encode($payload)])) . ' 2>&1');
    $j = json_decode((string) $out, true);
    if (!is_array($j)) {
        echo "      raw: " . substr((string) $out, 0, 400) . "\n";
        return [];
    }
    return $j['data'] ?? $j;
}

$c = q("SELECT * FROM channel WHERE channel_removed = 0 AND channel_system = 0 ORDER BY channel_id LIMIT 2");
if (count($c ?: []) < 2 || q("SELECT COUNT(*) AS n FROM cart_orders") === false) {
    echo "SKIP  needs two local channels and the cart addon's tables\n";
    exit(0);
}
[$seller, $buyer] = $c;
[$S, $B] = [$seller['channel_address'], $buyer['channel_address']];
$uid = intval($seller['channel_id']);

// Never check out somebody's real open cart.
if (q("SELECT id FROM cart_orders WHERE seller_channel = '%s' AND buyer_xchan = '%s' AND order_checkedout IS NULL",
        dbesc($seller['channel_hash']), dbesc($buyer['channel_hash']))) {
    echo "SKIP  $B already has an open cart at $S\n";
    exit(0);
}
echo "seller: $S   buyer: $B\n";

AccessList::remove($uid, GROUP);
AccessList::add($uid, GROUP);
$g      = q("SELECT * FROM pgrp WHERE uid = %d AND gname = '%s' AND deleted = 0 LIMIT 1", $uid, dbesc(GROUP))[0];
$member = fn() => (bool) q("SELECT id FROM pgrp_member WHERE uid = %d AND gid = %d AND xchan = '%s'",
    $uid, intval($g['id']), dbesc($buyer['channel_hash']));
$find   = fn(array $list) => array_values(array_filter($list, fn($i) => ($i['desc'] ?? '') === DESC))[0] ?? null;

$hash   = null;
$hashes = [];
// Buy $sku as the buyer and have the seller mark it paid; returns the seller's view of the order.
$buy = function (string $sku) use ($S, $B, &$hashes): array {
    $h = step($B, 'post', $S, 'item', ['sku' => $sku])['hash'] ?? '';
    $hashes[] = $h;
    step($B, 'post', $S, 'checkout');
    return step($S, 'post', $S, "orders/$h/markpaid");
};
$days = fn(?string $utc) => $utc ? (strtotime($utc . ' UTC') - time()) / 86400 : -1;

try {
    // ── seller creates an access product ──
    $p = $find(step($S, 'post', $S, 'catalog',
        ['description' => DESC, 'price' => 3, 'grants' => ['groups' => [$g['hash'], 'not-my-group'], 'connect' => false]]));
    check('product saved as the addon\'s service type', $p['type'] ?? null, 'hzservices');
    check('only the seller\'s own groups are kept', $p['grants'] ?? null, ['groups' => [$g['hash']], 'connect' => false]);
    $sku = $p['sku'] ?? '';
    check('sku fits cart_orderitems.item_sku', strlen($sku) > 0 && strlen($sku) <= 25);

    $pub = $find(step($B, 'get', $S, 'catalog'));
    check('buyer sees it grants access', $pub['access'] ?? null);
    check('buyer is not told which group', array_key_exists('grants', $pub ?? []), false);
    check('?all=1 is the seller\'s only', array_key_exists('grants', $find(step($B, 'get', $S, 'catalog', ['all' => 1])) ?? []), false);

    // ── buyer orders it ──
    $o = step($B, 'post', $S, 'item', ['sku' => $sku, 'qty' => 3]);
    check('access product is capped at 1', $o['items'][0]['qty'] ?? null, 1);
    $hash = $o['hash'] ?? null;
    check('checkout', step($B, 'post', $S, 'checkout')['checked_out'] ?? null);

    $mine = step($B, 'get', $S, 'my-orders');
    check('order appears under the buyer\'s orders', $mine[0]['hash'] ?? null, $hash);
    check('unpaid = no access yet', [$mine[0]['paid'] ?? null, $mine[0]['items'][0]['fulfilled'] ?? null, $member()], [false, false, false]);
    check('other channels see none of it', step($S, 'get', $S, 'my-orders'), []);

    // ── paid → confirmed + fulfilled + in the group ──
    $so = step($S, 'post', $S, "orders/$hash/markpaid");
    $it = $so['items'][0] ?? [];
    check('paid', $so['paid'] ?? null);
    check('item confirmed and auto-fulfilled', [$it['confirmed'] ?? null, $it['fulfilled'] ?? null, $it['exception'] ?? null], [true, true, false]);
    check('buyer is in the privacy group', $member());
    $row = q("SELECT order_currency FROM cart_orders WHERE order_hash = '%s'", dbesc($hash))[0];
    $cur = step($S, 'get', $S, 'payment-config')['currency'] ?? '';
    check('order stamped with the shop currency', $row['order_currency'], $cur);
    check('stored as the addon\'s item_type', q("SELECT item_type FROM cart_orderitems WHERE order_hash = '%s'", dbesc($hash))[0]['item_type'], 'hzservices');
    check('buyer sees access granted', step($B, 'get', $S, 'my-orders')[0]['items'][0]['fulfilled'] ?? null);

    // ── cancel → out of the group ──
    $so = step($S, 'post', $S, "orders/$hash/items/" . intval($it['id'] ?? 0) . '/cancel');
    check('cancel un-fulfils the item', $so['items'][0]['fulfilled'] ?? null, false);
    check('buyer is out of the privacy group', $member(), false);

    // ── subscription: one period per paid order ──
    $sp = array_values(array_filter(step($S, 'post', $S, 'catalog', ['description' => DESC . ' monthly', 'price' => 2,
        'grants' => ['groups' => [$g['hash']], 'connect' => false], 'billing' => ['interval' => 1, 'term' => 'month']]),
        fn($i) => $i['desc'] === DESC . ' monthly'))[0] ?? [];
    check('billing is stored and public', $sp['billing'] ?? null, ['interval' => 1, 'term' => 'month']);
    $bad = array_values(array_filter(step($S, 'post', $S, 'catalog', ['description' => DESC . ' bad', 'price' => 1,
        'billing' => ['interval' => 1, 'term' => 'minute; DROP']]), fn($i) => $i['desc'] === DESC . ' bad'))[0] ?? [];
    check('an unknown term saves as one-time', [isset($bad['sku']), $bad['billing'] ?? null], [true, null]);

    $so = $buy($sp['sku']);
    check('subscription item fulfilled on payment', $so['items'][0]['fulfilled'] ?? null);
    check('subscriber is in the group', $member());
    $subs = step($B, 'get', $S, 'my-subscriptions');
    check('buyer sees one live subscription', [count($subs), $subs[0]['active'] ?? null, $subs[0]['renewable'] ?? null], [1, true, true]);
    check('…running about a month', $days($subs[0]['expires'] ?? null) > 27 && $days($subs[0]['expires'] ?? null) < 32);
    $subId = intval($subs[0]['id'] ?? 0);

    $buy($sp['sku']);
    $subs = step($B, 'get', $S, 'my-subscriptions');
    check('renewing early extends the same row from its expiry', [count($subs), $subs[0]['id'] ?? null, $days($subs[0]['expires'] ?? null) > 57], [1, $subId, true]);

    // The addon's cron nulls a past sub_expires; ours must still act on the row.
    q("UPDATE cart_subscriptions SET sub_nexttrigger = '2000-01-01 00:00:00', sub_expires = NULL WHERE id = %d", $subId);
    \Utsukta\SpaCore\Api\Handlers\Cart::expireSubscriptions();
    $subs = step($B, 'get', $S, 'my-subscriptions');
    check('a lapsed subscription is ended by cron', [$subs[0]['active'] ?? null, $subs[0]['reason'] ?? null], [false, 'expired']);
    check('…and its access taken back', $member(), false);
    \Utsukta\SpaCore\Api\Handlers\Cart::expireSubscriptions();
    check('an ended one is not processed twice', step($B, 'get', $S, 'my-subscriptions')[0]['reason'] ?? null, 'expired');

    $buy($sp['sku']);
    $subs = step($B, 'get', $S, 'my-subscriptions');
    check('buying again revives it from now', [count($subs), $subs[0]['active'] ?? null, $member(), $days($subs[0]['expires'] ?? null) < 32], [1, true, true, true]);

    $ss = step($S, 'post', $S, "subscribers/$subId/revoke");
    check('seller revoke ends it', [$ss[0]['active'] ?? null, $member()], [false, false]);
    $ss = step($S, 'post', $S, "subscribers/$subId/extend");
    check('seller extend revives it and re-grants', [$ss[0]['active'] ?? null, $member()], [true, true]);
    check('seller list names the buyer', ($ss[0]['buyer'] ?? '') === $buyer['channel_hash']);
    check('a buyer cannot manage subscribers', isset(step($B, 'post', $S, "subscribers/$subId/revoke")['error']));
    $last = end($hashes);
    $lo   = step($S, 'get', $S, "orders/$last");
    step($S, 'post', $S, "orders/$last/items/" . intval($lo['items'][0]['id'] ?? 0) . '/cancel');
    check('cancelling its order item ends the subscription', [step($B, 'get', $S, 'my-subscriptions')[0]['active'] ?? null, $member()], [false, false]);

    // ── tax, buyer options, summary ──
    $before = step($S, 'get', $S, 'summary');
    $tp = array_values(array_filter(step($S, 'post', $S, 'catalog', ['description' => DESC . ' taxed', 'price' => 10, 'tax_percent' => 18,
        'options' => [['id' => 'client-made-id', 'label' => 'Engraving & <b>name</b>', 'type' => 'text', 'required' => true], ['label' => '']]]),
        fn($i) => $i['desc'] === DESC . ' taxed'))[0] ?? [];
    $opt = $tp['options'][0] ?? [];
    check('tax and one option saved; blank label dropped, tags stripped', [floatval($tp['tax_percent'] ?? -1), count($tp['options'] ?? []), $opt['label'] ?? null, $opt['required'] ?? null],
        [18.0, 1, 'Engraving & name', true]);
    check('option ids are issued here, not taken from the client', ($opt['id'] ?? 'client-made-id') !== 'client-made-id');

    $o = step($B, 'post', $S, 'item', ['sku' => $tp['sku'], 'qty' => 2]);
    $hashes[] = $o['hash'] ?? '';
    check('totals: 2 × 10 + 18%', [$o['subtotal'] ?? null, $o['tax'] ?? null, $o['total'] ?? null], ['20.00', '3.60', '23.60']);
    check('checkout refused while a required option is empty', isset(step($B, 'post', $S, 'checkout')['error']));
    $o = step($B, 'post', $S, 'item/options', ['sku' => $tp['sku'], 'options' => [$opt['id'] => ' <i>Ada</i> ', 'bogus' => 'x']]);
    check('option value stored, undefined ones dropped', $o['items'][0]['options'] ?? null, [['id' => $opt['id'], 'label' => 'Engraving & name', 'value' => 'Ada']]);
    check('checkout passes once filled', step($B, 'post', $S, 'checkout')['checked_out'] ?? null);
    $so = step($S, 'post', $S, 'orders/' . end($hashes) . '/markpaid');
    check('seller sees the option and the taxed total', [$so['items'][0]['options'][0]['value'] ?? null, $so['total'] ?? null], ['Ada', '23.60']);
    $meta = json_decode(q("SELECT order_meta FROM cart_orders WHERE order_hash = '%s'", dbesc(end($hashes)))[0]['order_meta'], true);
    check('totals snapshotted in the addon\'s keys', $meta['totals'] ?? null, ['Subtotal' => '20.00', 'Tax' => '3.60', 'OrderTotal' => '23.60']);
    $after = step($S, 'get', $S, 'summary');
    check('summary counts the new paid order, awaiting fulfilment', [$after['orders'] - $before['orders'], $after['to_fulfil'] - $before['to_fulfil']], [1, 1]);
    check('a buyer cannot read the summary', isset(step($B, 'get', $S, 'summary')['error']));

    // ── abandoned carts ──
    $cart = step($B, 'post', $S, 'item', ['sku' => $tp['sku']])['hash'] ?? '';
    $hashes[] = $cart;
    $exp = q("SELECT order_expires FROM cart_orders WHERE order_hash = '%s'", dbesc($cart))[0]['order_expires'] ?? null;
    check('a cart POST sets its expiry ~30 days out', $days($exp) > 29 && $days($exp) < 31);
    \Utsukta\SpaCore\Api\Handlers\Cart::purgeAbandonedCarts();
    check('a live cart survives the purge', (bool) q("SELECT id FROM cart_orders WHERE order_hash = '%s'", dbesc($cart)));
    q("UPDATE cart_orders SET order_expires = '2000-01-01 00:00:00' WHERE order_hash IN ('%s', '%s')", dbesc($cart), dbesc($hashes[count($hashes) - 2]));
    \Utsukta\SpaCore\Api\Handlers\Cart::purgeAbandonedCarts();
    check('an expired cart is purged with its items', [q("SELECT id FROM cart_orders WHERE order_hash = '%s'", dbesc($cart)), q("SELECT id FROM cart_orderitems WHERE order_hash = '%s'", dbesc($cart))], [[], []]);
    check('a placed order is never purged', (bool) q("SELECT id FROM cart_orders WHERE order_hash = '%s'", dbesc($hashes[count($hashes) - 2])));

    // ── clearing the grants moves it back to the plain catalog ──
    $p = $find(step($S, 'post', $S, 'catalog', ['sku' => $sku, 'description' => DESC, 'price' => 3, 'grants' => ['groups' => [], 'connect' => false]]));
    check('no grants = plain catalog item', [$p['type'] ?? null, $p['access'] ?? null], ['manualcat', false]);
    check('delete', $find(step($S, 'post', $S, 'catalog/delete', ['sku' => $sku])), null);
} finally {
    foreach (array_filter(array_merge([$hash], $hashes)) as $h) {
        q("DELETE FROM cart_subscriptions WHERE master_order_hash = '%s'", dbesc($h));
        q("DELETE FROM cart_orderitems WHERE order_hash = '%s'", dbesc($h));
        q("DELETE FROM cart_orders WHERE order_hash = '%s'", dbesc($h));
    }
    foreach (step($S, 'get', $S, 'catalog', ['all' => 1]) as $i) {
        if (str_starts_with($i['desc'] ?? '', DESC)) step($S, 'post', $S, 'catalog/delete', ['sku' => $i['sku']]);
    }
    AccessList::remove($uid, GROUP);
}

echo $fail ? "\n$fail FAILED\n" : "\nall ok\n";
exit($fail ? 1 : 0);
