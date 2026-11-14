<?php
namespace Utsukta\SpaCore\Api\Handlers;

use Utsukta\SpaCore\Api\Auth;
use Utsukta\SpaCore\Api\Concerns\CartFulfillment;
use Utsukta\SpaCore\Api\Response;
use Zotlabs\Lib\AccessList;

/**
 * Cart JSON API — direct DB/pconfig implementation over the cart addon's
 * tables and pconfig keys (so the classic UI and this one see the same shop),
 * without calling its hooks. Products with grants ("access products") live
 * in cart-hzservices/skus, plain ones in cart-manualcat; see
 * Concerns/CartFulfillment for why the grant commands are run here.
 *
 * Item state columns: item_confirmed = payment settled, item_fulfilled =
 * delivered / access granted, item_exception = a grant failed (see the item's
 * notes). settlePaid() is the one place an order becomes paid — every gateway
 * and "mark paid" go through it, and it auto-fulfils access products.
 *
 * Subscriptions: a product with a `subscription` {interval, term} key sells
 * one period of access. cart_subscriptions holds ONE row per buyer+product
 * (master_* = the first order item, sub_* = the latest renewal), extended in
 * place. sub_nexttrigger is the "live" marker and the time cron acts on —
 * not sub_expires, because the addon's own cron (always registered) nulls
 * expired sub_expires values without managing to revoke anything, and would
 * otherwise hide the row from us. The grants a subscription made live in its
 * sub_meta.granted, not on any one order item. The table is the addon's
 * (MySQL only there); without it billing is ignored and products sell one-time.
 * Check: Cart.test.php.
 *
 * Money: totals() is the only place an order's amount is computed —
 * subtotal + per-item tax (cart_orderitems.item_tax_rate, a fraction, copied
 * from the product when the item is added). payable() is what every way of
 * placing an order calls first: it refuses one with a required buyer option
 * left empty and returns those totals. Buyer options are the addon's
 * (cart-orderoptions/sku-<sku> definitions, item_meta.item_opts values);
 * only text and textarea are offered.
 *
 * Carts: cart_orders.order_expires is pushed 30 days out on every cart POST;
 * onCron() deletes carts that pass it un-checked-out. A placed order is
 * never deleted, paid or not.
 *
 * GET  /spa/cart/:nick/summary                 (seller — sales figures)
 * POST /spa/cart/:nick/item/options            {sku, options: {<id>: value}}
 * GET  /spa/cart/:nick/my-orders               (buyer — own placed orders)
 * GET  /spa/cart/:nick/my-subscriptions        (buyer)
 * GET  /spa/cart/:nick/subscribers             (seller)
 * POST /spa/cart/:nick/subscribers/:id/extend|revoke   (seller)
 * GET  /spa/cart/:nick/groups                  (seller — privacy groups for the product form)
 * GET  /spa/cart/:nick/catalog
 * GET  /spa/cart/:nick/catalog?all=1           (seller — includes inactive)
 * GET  /spa/cart/:nick/order
 * GET  /spa/cart/:nick/payment-config
 * GET  /spa/cart/:nick/payment-settings        (seller)
 * GET  /spa/cart/:nick/orders                  (seller)
 * GET  /spa/cart/:nick/orders/:hash            (seller)
 * POST /spa/cart/:nick/item                    {sku, qty?}
 * POST /spa/cart/:nick/item/remove             {sku}
 * POST /spa/cart/:nick/item/qty                {sku, qty}
 * POST /spa/cart/:nick/checkout                — manual checkout (contact seller)
 * POST /spa/cart/:nick/payment-settings              (seller) {paypal:{…}, razorpay:{…}, cashfree:{…}, upi:{…}, manual:{…}}
 * POST /spa/cart/:nick/paypal/create-order           {order_hash} → {paypal_order_id}
 * POST /spa/cart/:nick/paypal/capture                {paypal_order_id, order_hash} → {paid}
 * POST /spa/cart/:nick/razorpay/create-order         {order_hash} → {razorpay_order_id, key_id, amount, currency}
 * POST /spa/cart/:nick/razorpay/verify               {razorpay_order_id, razorpay_payment_id, razorpay_signature, order_hash}
 * POST /spa/cart/:nick/cashfree/create-order         {order_hash, customer_phone} → {payment_session_id, mode}
 * POST /spa/cart/:nick/cashfree/verify               {order_hash} → {paid}
 * POST /spa/cart/:nick/orders/:hash/markpaid
 * POST /spa/cart/:nick/orders/:hash/note       {text}
 * POST /spa/cart/:nick/orders/:hash/items/:id/fulfill|cancel
 */
class Cart
{
    use CartFulfillment;

    // ── Dispatch ──────────────────────────────────────────────────────────────

    public function get(): void
    {
        $nick    = \App::$argv[2] ?? '';
        $channel = $this->resolveChannel($nick);
        $action  = \App::$argv[3] ?? 'order';

        switch ($action) {
            case 'catalog':
                $isOwner = local_channel() && local_channel() == intval($channel['channel_id']);
                $this->getCatalog($channel, !empty($_GET['all']) && $isOwner);
                break;
            case 'my-orders':
                $this->getMyOrders($channel);
                break;
            case 'my-subscriptions':
                $buyer = get_observer_hash();
                Response::send($buyer ? $this->formatSubs($channel, "AND o.buyer_xchan = '" . dbesc($buyer) . "'", false) : []);
                break;
            case 'subscribers':
                $this->requireSeller($channel);
                Response::send($this->formatSubs($channel, '', true));
                break;
            case 'summary':
                $this->requireSeller($channel);
                $this->getSummary($channel);
                break;
            case 'groups':
                $this->requireSeller($channel);
                Response::send(q("SELECT hash, gname AS name FROM pgrp WHERE deleted = 0 AND uid = %d ORDER BY gname",
                    intval($channel['channel_id'])) ?: []);
                break;
            case 'payment-config':
                $this->getPaymentConfig($channel);
                break;
            case 'payment-settings':
                $this->requireSeller($channel);
                $this->getPaymentSettings($channel);
                break;
            case 'orders':
                $this->requireSeller($channel);
                $hash = \App::$argv[4] ?? null;
                $hash ? $this->getSellerOrder($channel, $hash)
                      : $this->getSellerOrders($channel);
                break;
            default:
                $this->getCurrentOrder($channel);
        }
    }

    public function post(): void
    {
        // Buyers may be remote (magic-auth) visitors; every seller action below
        // is separately gated by requireSeller(), which needs the local owner.
        Auth::requireLoggedInJson();

        $nick    = \App::$argv[2] ?? '';
        $channel = $this->resolveChannel($nick);
        $action  = \App::$argv[3] ?? '';
        $body    = Auth::$parsedBody ?? [];

        switch ($action) {
            case 'catalog':
                $this->requireSeller($channel);
                $sub = \App::$argv[4] ?? '';
                if ($sub === 'delete') {
                    $this->deleteCatalogItem($channel, $body['sku'] ?? '');
                } elseif ($sub === 'toggle') {
                    $this->toggleCatalogItem($channel, $body['sku'] ?? '');
                } else {
                    $this->saveCatalogItem($channel, $body);
                }
                break;
            case 'item':
                $sub = \App::$argv[4] ?? '';
                if ($sub === 'remove') {
                    $this->removeItem($channel, $body['sku'] ?? '');
                } elseif ($sub === 'options') {
                    $this->setItemOptions($channel, $body['sku'] ?? '', (array) ($body['options'] ?? []));
                } elseif ($sub === 'qty') {
                    $this->setItemQty($channel, $body['sku'] ?? '', intval($body['qty'] ?? 1));
                } else {
                    $this->addItem($channel, $body['sku'] ?? '', intval($body['qty'] ?? 1));
                }
                break;
            case 'checkout':
                $this->checkoutOrder($channel, $body['payment_hint'] ?? '');
                break;
            case 'payment-settings':
                $this->requireSeller($channel);
                $this->savePaymentSettings($channel, $body);
                break;
            case 'paypal':
                $sub = \App::$argv[4] ?? '';
                if ($sub === 'create-order') {
                    $this->paypalCreateOrder($channel, $body['order_hash'] ?? '');
                } elseif ($sub === 'capture') {
                    $this->paypalCapture($channel, $body['paypal_order_id'] ?? '', $body['order_hash'] ?? '');
                } else {
                    Response::error(400, 'Unknown PayPal action');
                }
                break;
            case 'razorpay':
                $sub = \App::$argv[4] ?? '';
                if ($sub === 'create-order') {
                    $this->razorpayCreateOrder($channel, $body['order_hash'] ?? '');
                } elseif ($sub === 'verify') {
                    $this->razorpayVerify(
                        $channel,
                        $body['razorpay_order_id']   ?? '',
                        $body['razorpay_payment_id'] ?? '',
                        $body['razorpay_signature']  ?? '',
                        $body['order_hash']          ?? ''
                    );
                } else {
                    Response::error(400, 'Unknown Razorpay action');
                }
                break;
            case 'cashfree':
                $sub = \App::$argv[4] ?? '';
                if ($sub === 'create-order') {
                    $this->cashfreeCreateOrder($channel, $body['order_hash'] ?? '', $body['customer_phone'] ?? '');
                } elseif ($sub === 'verify') {
                    $this->cashfreeVerify($channel, $body['order_hash'] ?? '');
                } else {
                    Response::error(400, 'Unknown Cashfree action');
                }
                break;
            case 'subscribers':
                $this->requireSeller($channel);
                $this->manageSubscriber($channel, intval(\App::$argv[4] ?? 0), \App::$argv[5] ?? '');
                break;
            case 'orders':
                $this->requireSeller($channel);
                $hash   = \App::$argv[4] ?? '';
                $sub    = \App::$argv[5] ?? '';
                $itemId = intval(\App::$argv[6] ?? 0);
                $subsub = \App::$argv[7] ?? '';
                if ($sub === 'markpaid') {
                    $this->markOrderPaid($channel, $hash);
                } elseif ($sub === 'note') {
                    $this->addOrderNote($channel, $hash, $body['text'] ?? '');
                } elseif ($sub === 'items' && $itemId && in_array($subsub, ['fulfill', 'cancel'])) {
                    $this->manageOrderItem($channel, $hash, $itemId, $subsub);
                } else {
                    Response::error(400, 'Unknown orders action');
                }
                break;
            default:
                Response::error(400, 'Unknown action');
        }
    }

    // ── Channel helpers ───────────────────────────────────────────────────────

    private function resolveChannel(string $nick): array
    {
        if (!$nick) Response::error(400, 'Channel nick required');
        $ch = channelx_by_nick($nick);
        if (!$ch) Response::error(404, 'Channel not found');
        return $ch;
    }

    private function requireSeller(array $channel): void
    {
        if (!local_channel() || local_channel() != intval($channel['channel_id'])) {
            Response::error(403, 'Owner access required');
        }
    }

    // ── Catalog ───────────────────────────────────────────────────────────────

    private function getCatalog(array $channel, bool $all = false): void
    {
        $out = [];
        foreach ($this->loadProducts(intval($channel['channel_id'])) as $sku => $d) {
            if (!$all && empty($d['item_active'])) continue;
            $out[] = $this->formatCatalogItem($sku, $d, $all);
        }
        Response::send($out);
    }

    /**
     * Every product of a seller, from both of the addon's stores:
     * sku => stored data + '_type' (the cart_orderitems.item_type it sells as).
     */
    private function loadProducts(int $uid): array
    {
        $out = [];
        // Buyer-option definitions, all skus in one query.
        $opts = [];
        foreach (q("SELECT k, v FROM pconfig WHERE uid = %d AND cat = 'cart-orderoptions'", $uid) ?: [] as $r) {
            if (str_starts_with($r['k'], 'sku-')) $opts[substr($r['k'], 4)] = (json_decode($r['v'], true) ?: [])['itemoptions'] ?? [];
        }
        foreach ((array) ($this->pget($uid, 'cart-manualcat', 'skulist') ?: []) as $sku) {
            $d = $this->pget($uid, 'cart-manualcat', 'sku-' . $sku);
            if (is_array($d)) $out[(string) $sku] = ['_type' => 'manualcat'] + $d;
        }
        foreach ((array) ($this->pget($uid, 'cart-hzservices', 'skus') ?: []) as $sku => $d) {
            if (is_array($d)) $out[(string) $sku] = ['_type' => 'hzservices'] + $d;
        }
        foreach ($out as $sku => &$d) $d['_options'] = self::optionDefs($opts[$sku] ?? []);
        unset($d);
        return $out;
    }

    /** The addon's itemoptions map → [{id, label, type, required}], text-like types only. */
    private static function optionDefs($itemoptions): array
    {
        $out = [];
        foreach (is_array($itemoptions) ? $itemoptions : [] as $id => $o) {
            if (!is_array($o) || ($o['label'] ?? '') === '') continue;
            $out[] = [
                'id'       => (string) ($o['uuid'] ?? $id),
                'label'    => html_entity_decode((string) $o['label']),   // the addon stores htmlentities()
                'type'     => ($o['type'] ?? '') === 'textarea' ? 'textarea' : 'text',
                'required' => !empty($o['required']),
            ];
        }
        return $out;
    }

    private function saveProduct(int $uid, string $sku, array $d): void
    {
        $type = $d['_type'];
        unset($d['_type'], $d['item_type'], $d['_options']);
        $d['item_sku'] = $sku;
        if ($type === 'hzservices') {
            $skus = $this->pget($uid, 'cart-hzservices', 'skus');
            $skus = is_array($skus) ? $skus : [];
            $skus[$sku] = $d;
            $this->pset($uid, 'cart-hzservices', 'skus', $skus);
        } else {
            unset($d['activate_commands'], $d['deactivate_commands']);
            $this->pset($uid, 'cart-manualcat', 'sku-' . $sku, $d);
            $this->saveSkulist($uid, fn(array $l) => $l + [$sku => $sku]);
        }
    }

    private function deleteProduct(int $uid, string $sku, string $type): void
    {
        if ($type === 'hzservices') {
            $skus = $this->pget($uid, 'cart-hzservices', 'skus');
            $skus = is_array($skus) ? $skus : [];
            unset($skus[$sku]);
            $this->pset($uid, 'cart-hzservices', 'skus', $skus);
        } else {
            del_pconfig($uid, 'cart-manualcat', 'sku-' . $sku);
            $this->saveSkulist($uid, fn(array $l) => array_diff_key($l, [$sku => 1]));
        }
    }

    /** skulist is a sku => sku map in the addon; older SPA saves wrote a plain list. */
    private function saveSkulist(int $uid, callable $change): void
    {
        $list = array_map('strval', array_values((array) ($this->pget($uid, 'cart-manualcat', 'skulist') ?: [])));
        $this->pset($uid, 'cart-manualcat', 'skulist', (object) $change(array_combine($list, $list)));
    }

    private function formatCatalogItem(string $sku, array $d, bool $forSeller = false): array
    {
        $price    = floatval($d['item_price'] ?? 0);
        $photoUrl = $d['item_photo_url'] ?? '';
        if ($photoUrl && strpos($photoUrl, 'http') !== 0) {
            $photoUrl = z_root() . $photoUrl;
        }
        $activate = (array) ($d['activate_commands'] ?? []);
        $out = [
            'sku'       => $sku,
            'desc'      => $d['item_description'] ?? $d['item_desc'] ?? $sku,
            'price_raw' => $price,
            'price'     => $this->fmtPrice($price),
            'photo_url' => $photoUrl ?: null,
            'type'      => $d['_type'] ?? 'manualcat',
            'active'    => !empty($d['item_active']),
            // Public: *that* it grants access, never which group — names are the seller's.
            'access'    => (bool) $activate,
            'billing'   => $this->billingOf($d),
            'tax_percent' => round(floatval($d['item_tax_rate'] ?? 0) * 100, 2),
            'options'   => $d['_options'] ?? [],
        ];
        if ($forSeller) $out['grants'] = self::commandsToGrants($activate);
        return $out;
    }

    // ── Current order ─────────────────────────────────────────────────────────

    private function getCurrentOrder(array $channel): void
    {
        $hash = $this->findOrderHash($channel, false);
        if (!$hash) {
            Response::send($this->emptyOrder());
            return;
        }
        Response::send($this->formatOrderForBuyer($this->loadOrder($hash), $channel));
    }

    // ── Item mutations ────────────────────────────────────────────────────────

    private function addItem(array $channel, string $sku, int $qty): void
    {
        if (!$sku) Response::error(400, 'sku required');
        if ($qty < 1) $qty = 1;

        $catalogItem = $this->findCatalogItem($channel, $sku);
        if (!$catalogItem) Response::error(404, 'Item not in catalog');

        $hash  = $this->findOrderHash($channel, true);
        $order = $this->loadOrder($hash);

        // Access is had or not had — an access product never goes above 1.
        $single = $catalogItem['access'] || $catalogItem['billing'];
        if ($single) $qty = 1;

        foreach ($order['items'] ?? [] as $oi) {
            if ($oi['item_sku'] === $sku) {
                $newQty = $single ? 1 : intval($oi['item_qty']) + $qty;
                q("UPDATE cart_orderitems SET item_qty = %d, item_lastupdate = NOW() WHERE order_hash = '%s' AND id = %d",
                    $newQty, dbesc($hash), intval($oi['id']));
                Response::send($this->formatOrderForBuyer($this->loadOrder($hash), $channel));
                return;
            }
        }

        q("INSERT INTO cart_orderitems (order_hash, item_sku, item_desc, item_type, item_qty, item_price, item_tax_rate, item_lastupdate)
           VALUES ('%s','%s','%s','%s',%d,%f,%f,NOW())",
            dbesc($hash),
            dbesc($sku),
            dbesc(mb_substr($catalogItem['desc'], 0, 191)),
            dbesc($catalogItem['type']),
            $qty,
            floatval($catalogItem['price_raw']),
            floatval($catalogItem['tax_percent']) / 100
        );

        Response::send($this->formatOrderForBuyer($this->loadOrder($hash), $channel));
    }

    private function removeItem(array $channel, string $sku): void
    {
        if (!$sku) Response::error(400, 'sku required');
        $hash = $this->findOrderHash($channel, false);
        if (!$hash) { Response::send($this->emptyOrder()); return; }
        q("DELETE FROM cart_orderitems WHERE order_hash = '%s' AND item_sku = '%s'",
            dbesc($hash), dbesc($sku));
        $this->cleanupEmptyOrder($hash);
        Response::send($this->formatOrderForBuyer($this->loadOrder($hash), $channel));
    }

    private function setItemQty(array $channel, string $sku, int $qty): void
    {
        if (!$sku) Response::error(400, 'sku required');
        $hash = $this->findOrderHash($channel, false);
        if (!$hash) Response::error(404, 'No active order');

        if ($qty <= 0) {
            q("DELETE FROM cart_orderitems WHERE order_hash = '%s' AND item_sku = '%s'",
                dbesc($hash), dbesc($sku));
            $this->cleanupEmptyOrder($hash);
        } else {
            $ci = $qty > 1 ? $this->findCatalogItem($channel, $sku) : null;
            if ($ci && ($ci['access'] || $ci['billing'])) $qty = 1;
            q("UPDATE cart_orderitems SET item_qty = %d, item_lastupdate = NOW() WHERE order_hash = '%s' AND item_sku = '%s'",
                $qty, dbesc($hash), dbesc($sku));
        }
        Response::send($this->formatOrderForBuyer($this->loadOrder($hash), $channel));
    }

    private function checkoutOrder(array $channel, string $paymentHint = ''): void
    {
        $hash = $this->findOrderHash($channel, false);
        if (!$hash) Response::error(404, 'No active order to check out');

        $order = $this->loadOrder($hash);
        if (!$order || empty($order['items'])) Response::error(400, 'Cart is empty');
        if (!empty($order['order_checkedout'])) Response::error(400, 'Order already checked out');
        $totals = $this->payable($channel, $order);

        q("UPDATE cart_orders SET order_checkedout = NOW(), order_currency = '%s' WHERE order_hash = '%s'",
            dbesc($this->shopCurrency(intval($channel['channel_id']))), dbesc($hash));

        $hint = $paymentHint ? strip_tags(trim($paymentHint)) : 'Contact seller to arrange payment';
        $meta            = $this->orderMeta($hash);
        $meta['notes'][] = date('Y-m-d h:i:sa T') . ' — Order placed. ' . $hint;
        $meta['totals']  = $this->totalsMeta($totals);
        $this->saveOrderMeta($hash, $meta);

        unset($_SESSION['cart_order_hash']);

        Response::send(['checked_out' => true, 'order_hash' => $hash]);
    }

    // ── Payment config ────────────────────────────────────────────────────────

    private function getPaymentConfig(array $channel): void
    {
        $uid      = intval($channel['channel_id']);
        $settings = $this->loadPaymentSettingsRaw($uid);
        $currency = $this->shopCurrency($uid);

        $providers = [
            ['id' => 'manual', 'label' => 'Contact Seller', 'enabled' => true],
        ];

        $pp = $settings['paypal'] ?? [];
        if (!empty($pp['enabled']) && !empty($pp['client_id'])) {
            $providers[] = [
                'id'        => 'paypal',
                'label'     => 'PayPal',
                'enabled'   => true,
                'client_id' => $pp['client_id'],
            ];
        }

        $rp = $settings['razorpay'] ?? [];
        if (!empty($rp['enabled']) && !empty($rp['key_id'])) {
            $providers[] = [
                'id'       => 'razorpay',
                'label'    => 'Razorpay',
                'enabled'  => true,
                'key_id'   => $rp['key_id'],
                'currency' => $rp['currency'] ?? 'INR',
            ];
        }

        $cf = $settings['cashfree'] ?? [];
        if (!empty($cf['enabled']) && !empty($cf['app_id'])) {
            $providers[] = [
                'id'      => 'cashfree',
                'label'   => 'Cashfree',
                'enabled' => true,
                'app_id'  => $cf['app_id'],
                'mode'    => ($cf['mode'] ?? 'sandbox') === 'production' ? 'production' : 'sandbox',
            ];
        }

        $upi = $settings['upi'] ?? [];
        if (!empty($upi['enabled']) && !empty($upi['upi_id'])) {
            $providers[] = [
                'id'           => 'upi',
                'label'        => 'UPI',
                'enabled'      => true,
                'upi_id'       => $upi['upi_id'],
                'display_name' => $upi['display_name'] ?? '',
            ];
        }

        Response::send(['providers' => $providers, 'currency' => $currency]);
    }

    private function getPaymentSettings(array $channel): void
    {
        $uid      = intval($channel['channel_id']);
        $settings = $this->loadPaymentSettingsRaw($uid);
        $settings['currency'] = $this->shopCurrency($uid);
        Response::send($settings);
    }

    private function savePaymentSettings(array $channel, array $body): void
    {
        $uid = intval($channel['channel_id']);

        $existing = $this->loadPaymentSettingsRaw($uid);

        $settings = [
            'paypal' => [
                'enabled'   => (bool) ($body['paypal']['enabled'] ?? false),
                'client_id' => trim($body['paypal']['client_id'] ?? ''),
                'secret'    => trim($body['paypal']['secret'] ?? ''),
                'mode'      => ($body['paypal']['mode'] ?? 'sandbox') === 'live' ? 'live' : 'sandbox',
            ],
            'razorpay' => [
                'enabled'    => (bool) ($body['razorpay']['enabled'] ?? false),
                'key_id'     => trim($body['razorpay']['key_id'] ?? ''),
                'key_secret' => trim($body['razorpay']['key_secret'] ?? ''),
                'currency'   => strtoupper(trim($body['razorpay']['currency'] ?? 'INR')) ?: 'INR',
            ],
            'cashfree' => [
                'enabled'    => (bool) ($body['cashfree']['enabled'] ?? false),
                'app_id'     => trim($body['cashfree']['app_id'] ?? ''),
                'secret_key' => trim($body['cashfree']['secret_key'] ?? ''),
                'mode'       => ($body['cashfree']['mode'] ?? 'sandbox') === 'production' ? 'production' : 'sandbox',
            ],
            'upi' => [
                'enabled'      => (bool) ($body['upi']['enabled'] ?? false),
                'upi_id'       => trim($body['upi']['upi_id'] ?? ''),
                'display_name' => trim(strip_tags($body['upi']['display_name'] ?? '')),
            ],
            'manual' => [
                'enabled'      => true,
                'instructions' => trim(strip_tags($body['manual']['instructions'] ?? '')),
            ],
        ];

        // Save top-level currency
        $currency = strtoupper(trim($body['currency'] ?? 'USD'));
        if (!preg_match('/^[A-Z]{3}$/', $currency)) $currency = 'USD';
        set_pconfig($uid, 'cart', 'cart_currency', $currency);   // the addon's key, plain string
        $settings['currency'] = $currency;

        // Preserve secrets when client sends masked placeholder
        foreach (['paypal' => 'secret', 'razorpay' => 'key_secret', 'cashfree' => 'secret_key'] as $provider => $field) {
            $v = $settings[$provider][$field];
            if ($v === '' || str_starts_with($v, '••')) {
                $settings[$provider][$field] = $existing[$provider][$field] ?? '';
            }
        }

        $this->pset($uid, 'cart-payments', 'settings', $settings);

        // Return masked settings (never expose secrets to frontend)
        $settings['paypal']['secret']        = $settings['paypal']['secret']        ? '••••••••' : '';
        $settings['razorpay']['key_secret']  = $settings['razorpay']['key_secret']  ? '••••••••' : '';
        $settings['cashfree']['secret_key']  = $settings['cashfree']['secret_key']  ? '••••••••' : '';
        // currency already included in $settings via the block above
        Response::send($settings);
    }

    private function loadPaymentSettingsRaw(int $uid): array
    {
        $d = $this->pget($uid, 'cart-payments', 'settings');
        $defaults = [
            'paypal'   => ['enabled' => false, 'client_id' => '', 'secret' => '', 'mode' => 'sandbox'],
            'razorpay' => ['enabled' => false, 'key_id' => '', 'key_secret' => '', 'currency' => 'INR'],
            'cashfree' => ['enabled' => false, 'app_id' => '', 'secret_key' => '', 'mode' => 'sandbox'],
            'upi'      => ['enabled' => false, 'upi_id' => '', 'display_name' => ''],
            'manual'   => ['enabled' => true, 'instructions' => ''],
        ];
        if (!is_array($d)) return $defaults;
        // Merge so new keys are present even in old saved data
        foreach ($defaults as $k => $v) {
            $d[$k] = array_merge($v, $d[$k] ?? []);
        }
        return $d;
    }

    // ── PayPal ────────────────────────────────────────────────────────────────

    private function paypalCreateOrder(array $channel, string $orderHash): void
    {
        $orderHash = preg_replace('/[^a-z0-9\-]/', '', $orderHash);
        if (!$orderHash) Response::error(400, 'order_hash required');

        $order = $this->loadOrder($orderHash);
        if (!$order) Response::error(404, 'Order not found');

        // Verify the order belongs to the current buyer
        $buyerXchan = get_observer_hash();
        if (!$buyerXchan || $order['buyer_xchan'] !== $buyerXchan) {
            Response::error(403, 'Access denied');
        }
        if (!empty($order['order_paid'])) Response::error(400, 'Order already paid');

        $uid      = intval($channel['channel_id']);
        $settings = $this->loadPaymentSettingsRaw($uid);
        $pp       = $settings['paypal'] ?? [];

        if (empty($pp['enabled']) || empty($pp['client_id']) || empty($pp['secret'])) {
            Response::error(503, 'PayPal not configured for this seller');
        }

        $total = $this->payable($channel, $order)['total'];
        // Not $order['order_currency']: that is only stamped at checkout, and
        // until then holds the column default (USD) whatever the shop sells in.
        $currency = $this->shopCurrency($uid);

        $token   = $this->paypalGetToken($pp);
        $baseUrl = $pp['mode'] === 'live' ? 'https://api-m.paypal.com' : 'https://api-m.sandbox.paypal.com';

        $payload = json_encode([
            'intent'         => 'CAPTURE',
            'purchase_units' => [[
                'reference_id' => $orderHash,
                'amount'       => [
                    'currency_code' => $currency,
                    'value'         => number_format($total, 2, '.', ''),
                ],
            ]],
        ]);

        $ch = curl_init($baseUrl . '/v2/checkout/orders');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $token,
                'Content-Type: application/json',
            ],
        ]);
        $resp   = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($status !== 201) {
            Response::error(502, 'PayPal order creation failed');
        }

        $data = json_decode($resp, true);
        if (empty($data['id'])) Response::error(502, 'PayPal returned no order ID');

        Response::send(['paypal_order_id' => $data['id']]);
    }

    private function paypalCapture(array $channel, string $paypalOrderId, string $orderHash): void
    {
        $orderHash    = preg_replace('/[^a-z0-9\-]/', '', $orderHash);
        $paypalOrderId = preg_replace('/[^A-Z0-9]/', '', strtoupper($paypalOrderId));

        if (!$orderHash || !$paypalOrderId) Response::error(400, 'order_hash and paypal_order_id required');

        $order = $this->loadOrder($orderHash);
        if (!$order) Response::error(404, 'Order not found');

        $buyerXchan = get_observer_hash();
        if (!$buyerXchan || $order['buyer_xchan'] !== $buyerXchan) {
            Response::error(403, 'Access denied');
        }
        if (!empty($order['order_paid'])) {
            Response::send(['paid' => true, 'order_hash' => $orderHash]);
            return;
        }

        $uid      = intval($channel['channel_id']);
        $settings = $this->loadPaymentSettingsRaw($uid);
        $pp       = $settings['paypal'] ?? [];

        if (empty($pp['secret'])) Response::error(503, 'PayPal not configured');

        $token   = $this->paypalGetToken($pp);
        $baseUrl = $pp['mode'] === 'live' ? 'https://api-m.paypal.com' : 'https://api-m.sandbox.paypal.com';

        $ch = curl_init($baseUrl . '/v2/checkout/orders/' . $paypalOrderId . '/capture');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => '{}',
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $token,
                'Content-Type: application/json',
            ],
        ]);
        $resp   = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($status !== 201 && $status !== 200) {
            Response::error(502, 'PayPal capture failed');
        }

        $data = json_decode($resp, true);
        if (($data['status'] ?? '') !== 'COMPLETED') {
            Response::error(502, 'PayPal payment not completed: ' . ($data['status'] ?? 'unknown'));
        }

        // Extract PayPal transaction ID from capture
        $txnId = $data['purchase_units'][0]['payments']['captures'][0]['id'] ?? '';

        // Mark order as checked out + paid, record payment metadata
        $this->settlePaid($channel, $orderHash, 'Paid via PayPal (txn: ' . $txnId . ')', ['provider' => 'paypal', 'txn_id' => $txnId, 'paypal_order_id' => $paypalOrderId]);

        Response::send(['paid' => true, 'order_hash' => $orderHash, 'txn_id' => $txnId]);
    }

    private function paypalGetToken(array $pp): string
    {
        $baseUrl = $pp['mode'] === 'live' ? 'https://api-m.paypal.com' : 'https://api-m.sandbox.paypal.com';

        $ch = curl_init($baseUrl . '/v1/oauth2/token');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => 'grant_type=client_credentials',
            CURLOPT_USERPWD        => $pp['client_id'] . ':' . $pp['secret'],
            CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
        ]);
        $resp   = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($status !== 200) Response::error(502, 'PayPal authentication failed');

        $data = json_decode($resp, true);
        if (empty($data['access_token'])) Response::error(502, 'PayPal returned no token');

        return $data['access_token'];
    }

    // ── Razorpay ──────────────────────────────────────────────────────────────

    private function razorpayCreateOrder(array $channel, string $orderHash): void
    {
        $orderHash = preg_replace('/[^a-z0-9\-]/', '', $orderHash);
        if (!$orderHash) Response::error(400, 'order_hash required');

        $order = $this->loadOrder($orderHash);
        if (!$order) Response::error(404, 'Order not found');

        $buyerXchan = get_observer_hash();
        if (!$buyerXchan || $order['buyer_xchan'] !== $buyerXchan) Response::error(403, 'Access denied');
        if (!empty($order['order_paid'])) Response::error(400, 'Order already paid');

        $uid      = intval($channel['channel_id']);
        $settings = $this->loadPaymentSettingsRaw($uid);
        $rp       = $settings['razorpay'] ?? [];

        if (empty($rp['enabled']) || empty($rp['key_id']) || empty($rp['key_secret'])) {
            Response::error(503, 'Razorpay not configured for this seller');
        }

        $total    = $this->payable($channel, $order)['total'];
        $currency = strtoupper($rp['currency'] ?? 'INR');
        // Razorpay amounts are in smallest currency unit (paisa for INR)
        $amountMinor = intval(round($total * 100));

        $payload = json_encode([
            'amount'   => $amountMinor,
            'currency' => $currency,
            'receipt'  => $orderHash,
        ]);

        $ch = curl_init('https://api.razorpay.com/v1/orders');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_USERPWD        => $rp['key_id'] . ':' . $rp['key_secret'],
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        ]);
        $resp   = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($status !== 200) {
            $err = json_decode($resp, true);
            Response::error(502, 'Razorpay error: ' . ($err['error']['description'] ?? 'unknown'));
        }

        $data = json_decode($resp, true);
        if (empty($data['id'])) Response::error(502, 'Razorpay returned no order ID');

        Response::send([
            'razorpay_order_id' => $data['id'],
            'key_id'            => $rp['key_id'],
            'amount'            => $amountMinor,
            'currency'          => $currency,
        ]);
    }

    private function razorpayVerify(
        array $channel,
        string $rzpOrderId,
        string $rzpPaymentId,
        string $rzpSignature,
        string $orderHash
    ): void {
        $orderHash = preg_replace('/[^a-z0-9\-]/', '', $orderHash);
        if (!$orderHash || !$rzpOrderId || !$rzpPaymentId || !$rzpSignature) {
            Response::error(400, 'Missing required fields');
        }

        $order = $this->loadOrder($orderHash);
        if (!$order) Response::error(404, 'Order not found');

        $buyerXchan = get_observer_hash();
        if (!$buyerXchan || $order['buyer_xchan'] !== $buyerXchan) Response::error(403, 'Access denied');
        if (!empty($order['order_paid'])) {
            Response::send(['paid' => true, 'order_hash' => $orderHash]);
            return;
        }

        $uid      = intval($channel['channel_id']);
        $settings = $this->loadPaymentSettingsRaw($uid);
        $rp       = $settings['razorpay'] ?? [];

        if (empty($rp['key_secret'])) Response::error(503, 'Razorpay not configured');

        // Verify HMAC-SHA256 signature
        $expected = hash_hmac('sha256', $rzpOrderId . '|' . $rzpPaymentId, $rp['key_secret']);
        if (!hash_equals($expected, $rzpSignature)) {
            Response::error(400, 'Payment signature verification failed');
        }

        $this->settlePaid($channel, $orderHash, 'Paid via Razorpay (txn: ' . $rzpPaymentId . ')', ['provider' => 'razorpay', 'txn_id' => $rzpPaymentId, 'razorpay_order_id' => $rzpOrderId]);

        Response::send(['paid' => true, 'order_hash' => $orderHash, 'txn_id' => $rzpPaymentId]);
    }

    // ── Cashfree ──────────────────────────────────────────────────────────────

    private function cashfreeCreateOrder(array $channel, string $orderHash, string $customerPhone): void
    {
        $orderHash     = preg_replace('/[^a-z0-9\-]/', '', $orderHash);
        $customerPhone = preg_replace('/[^0-9+]/', '', $customerPhone);
        if (!$orderHash) Response::error(400, 'Missing order_hash');
        if (strlen($customerPhone) < 10) Response::error(400, 'Valid customer_phone required');

        $order = $this->loadOrder($orderHash);
        if (!$order) Response::error(404, 'Order not found');

        // Verify the order belongs to the current buyer (matches
        // paypalCreateOrder/razorpayCreateOrder/*Verify — this was the one
        // payment-order function missing the check).
        $buyerXchan = get_observer_hash();
        if (!$buyerXchan || $order['buyer_xchan'] !== $buyerXchan) {
            Response::error(403, 'Access denied');
        }
        if (!empty($order['order_paid'])) {
            Response::send(['paid' => true]);
            return;
        }

        $uid      = intval($channel['channel_id']);
        $settings = $this->loadPaymentSettingsRaw($uid);
        $cf       = $settings['cashfree'] ?? [];
        if (empty($cf['app_id']) || empty($cf['secret_key'])) Response::error(503, 'Cashfree not configured');

        $mode    = ($cf['mode'] ?? 'sandbox') === 'production' ? 'production' : 'sandbox';
        $baseUrl = $mode === 'production' ? 'https://api.cashfree.com/pg' : 'https://sandbox.cashfree.com/pg';
        $currency = $this->shopCurrency($uid, 'INR');

        // Total in paisa units handled by Cashfree as decimal rupees (not like Razorpay)
        $amount = $this->payable($channel, $order)['total'];

        // Derive customer email from xchan_addr; fallback to a placeholder
        $xchanRows  = q("SELECT xchan_addr, xchan_name FROM xchan WHERE xchan_hash = '%s' LIMIT 1", dbesc($buyerXchan));
        $xchanAddr  = (!empty($xchanRows[0]['xchan_addr'])) ? $xchanRows[0]['xchan_addr'] : 'buyer@hubzilla.example';
        $xchanName  = (!empty($xchanRows[0]['xchan_name']))  ? $xchanRows[0]['xchan_name']  : 'Buyer';

        $payload = json_encode([
            'order_id'         => $orderHash,  // reuse our UUID as Cashfree order_id
            'order_amount'     => $amount,
            'order_currency'   => $currency,
            'customer_details' => [
                'customer_id'    => substr(md5($buyerXchan), 0, 40),
                'customer_name'  => $xchanName,
                'customer_email' => $xchanAddr,
                'customer_phone' => $customerPhone,
            ],
        ]);

        $ch = curl_init("$baseUrl/orders");
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_HTTPHEADER     => [
                'x-api-version: 2023-08-01',
                'x-client-id: '     . $cf['app_id'],
                'x-client-secret: ' . $cf['secret_key'],
                'Content-Type: application/json',
            ],
        ]);
        $body   = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $data = json_decode($body, true);
        if ($status !== 200 || empty($data['payment_session_id'])) {
            $msg = $data['message'] ?? 'Cashfree order creation failed';
            Response::error(502, $msg);
        }

        // Store cf_order_id for verification
        $meta                        = $this->orderMeta($orderHash);
        $meta['cashfree_session_id'] = $data['payment_session_id'];
        $this->saveOrderMeta($orderHash, $meta);

        Response::send([
            'payment_session_id' => $data['payment_session_id'],
            'mode'               => $mode,
        ]);
    }

    private function cashfreeVerify(array $channel, string $orderHash): void
    {
        $orderHash = preg_replace('/[^a-z0-9\-]/', '', $orderHash);
        if (!$orderHash) Response::error(400, 'Missing order_hash');

        $order = $this->loadOrder($orderHash);
        if (!$order) Response::error(404, 'Order not found');

        $buyerXchan = get_observer_hash();
        if (!$buyerXchan || $order['buyer_xchan'] !== $buyerXchan) Response::error(403, 'Access denied');
        if (!empty($order['order_paid'])) {
            Response::send(['paid' => true, 'order_hash' => $orderHash]);
            return;
        }

        $uid      = intval($channel['channel_id']);
        $settings = $this->loadPaymentSettingsRaw($uid);
        $cf       = $settings['cashfree'] ?? [];
        if (empty($cf['app_id']) || empty($cf['secret_key'])) Response::error(503, 'Cashfree not configured');

        $mode    = ($cf['mode'] ?? 'sandbox') === 'production' ? 'production' : 'sandbox';
        $baseUrl = $mode === 'production' ? 'https://api.cashfree.com/pg' : 'https://sandbox.cashfree.com/pg';

        // Query Cashfree using our order_hash as the Cashfree order_id
        $ch = curl_init("$baseUrl/orders/$orderHash");
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                'x-api-version: 2023-08-01',
                'x-client-id: '     . $cf['app_id'],
                'x-client-secret: ' . $cf['secret_key'],
            ],
        ]);
        $body   = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $data = json_decode($body, true);
        if ($status !== 200) {
            Response::error(502, 'Could not verify payment with Cashfree');
        }

        $orderStatus = $data['order_status'] ?? '';
        if ($orderStatus !== 'PAID') {
            Response::error(402, 'Payment not completed (status: ' . $orderStatus . ')');
        }

        // Find the transaction ID from the payments array if available
        $txnId = $data['payments'][0]['cf_payment_id'] ?? ($data['cf_order_id'] ?? '');

        $this->settlePaid($channel, $orderHash, 'Paid via Cashfree (txn: ' . $txnId . ')', ['provider' => 'cashfree', 'txn_id' => (string) $txnId]);

        Response::send(['paid' => true, 'order_hash' => $orderHash, 'txn_id' => (string) $txnId]);
    }

    // ── Seller: listing ───────────────────────────────────────────────────────

    private function getSellerOrders(array $channel): void
    {
        $sellerHash = $channel['channel_hash'];
        $rows = q("SELECT DISTINCT order_hash FROM cart_orders
                   WHERE seller_channel = '%s'
                     AND order_checkedout IS NOT NULL
                   ORDER BY id DESC LIMIT 200",
                   dbesc($sellerHash));
        $out = [];
        foreach ($rows ?: [] as $row) {
            $out[] = $this->formatOrderForSeller($this->loadOrder($row['order_hash']));
        }
        Response::send($out);
    }

    private function getSellerOrder(array $channel, string $hash): void
    {
        $hash  = preg_replace('/[^a-z0-9\-]/', '', $hash);
        $order = $this->loadOrder($hash);
        if (!$order) Response::error(404, 'Order not found');
        if (!$this->isSellerOrder($order, $channel)) Response::error(403, 'Access denied');
        Response::send($this->formatOrderForSeller($order));
    }

    // ── Seller: actions ───────────────────────────────────────────────────────

    private function markOrderPaid(array $channel, string $hash): void
    {
        $hash  = preg_replace('/[^a-z0-9\-]/', '', $hash);
        $order = $this->loadOrder($hash);
        if (!$order) Response::error(404, 'Order not found');
        if (!$this->isSellerOrder($order, $channel)) Response::error(403, 'Access denied');

        if (empty($order['order_paid'])) $this->settlePaid($channel, $hash, 'Marked Paid (manual)');

        Response::send($this->formatOrderForSeller($this->loadOrder($hash)));
    }

    private function addOrderNote(array $channel, string $hash, string $text): void
    {
        $hash = preg_replace('/[^a-z0-9\-]/', '', $hash);
        $text = trim(strip_tags($text));
        if (!$text) Response::error(400, 'Note text required');

        $order = $this->loadOrder($hash);
        if (!$order) Response::error(404, 'Order not found');
        if (!$this->isSellerOrder($order, $channel)) Response::error(403, 'Access denied');

        $meta            = $this->orderMeta($hash);
        $meta['notes'][] = date('Y-m-d h:i:sa T') . ' — ' . $text;
        $this->saveOrderMeta($hash, $meta);

        Response::send($this->formatOrderForSeller($this->loadOrder($hash)));
    }

    private function manageOrderItem(array $channel, string $hash, int $itemId, string $action): void
    {
        $hash  = preg_replace('/[^a-z0-9\-]/', '', $hash);
        $order = $this->loadOrder($hash);
        if (!$order) Response::error(404, 'Order not found');
        if (!$this->isSellerOrder($order, $channel)) Response::error(403, 'Access denied');

        $item = null;
        foreach ($order['items'] as $oi) {
            if (intval($oi['id']) === $itemId) { $item = $oi; break; }
        }
        if (!$item) Response::error(404, 'Item not found');

        $action === 'fulfill' ? $this->fulfilItem($channel, $order, $item)
                              : $this->cancelItem($channel, $order, $item);

        Response::send($this->formatOrderForSeller($this->loadOrder($hash)));
    }

    // ── Payment settled / fulfilment ──────────────────────────────────────────

    /**
     * The one place an order becomes paid. Confirms every item and fulfils the
     * access products straight away; anything else waits for the seller.
     */
    private function settlePaid(array $channel, string $hash, string $note, ?array $payment = null): void
    {
        $order = $this->loadOrder($hash);
        if (!$order) return;

        // Currency is fixed when the order is placed; a later settings change
        // must not relabel an order that was already checked out.
        if (empty($order['order_checkedout'])) {
            q("UPDATE cart_orders SET order_checkedout = NOW(), order_currency = '%s' WHERE order_hash = '%s'",
                dbesc($this->shopCurrency(intval($channel['channel_id']))), dbesc($hash));
        }
        q("UPDATE cart_orders SET order_paid = NOW() WHERE order_hash = '%s'", dbesc($hash));
        q("UPDATE cart_orderitems SET item_confirmed = 1, item_lastupdate = NOW() WHERE order_hash = '%s'", dbesc($hash));

        $meta = $this->orderMeta($hash);
        if ($payment) $meta['payment'] = $payment;
        $meta['totals'] = $this->totalsMeta($this->totals($order));
        $meta['notes'][] = date('Y-m-d h:i:sa T') . ' — ' . $note;
        $this->saveOrderMeta($hash, $meta);

        $products = $this->loadProducts(intval($channel['channel_id']));
        foreach ($order['items'] as $oi) {
            if (!empty($products[$oi['item_sku']]['activate_commands'])) {
                $this->fulfilItem($channel, $order, $oi, $products);
            }
        }

        if (($_SESSION['cart_order_hash'] ?? '') === $hash) unset($_SESSION['cart_order_hash']);
    }

    /**
     * Grants come from the product as it is *now* (looked up by sku, so a row
     * with a pre-fix item_type still works); what they changed is snapshotted
     * in item_meta.granted, which is all cancelItem() ever undoes.
     */
    private function fulfilItem(array $channel, array $order, array $oi, ?array $products = null): void
    {
        if (intval($oi['item_fulfilled'])) return;

        $products ??= $this->loadProducts(intval($channel['channel_id']));
        $activate = (array) ($products[$oi['item_sku']]['activate_commands'] ?? []);
        $meta     = is_array($oi['item_meta']) ? $oi['item_meta'] : [];
        $errors   = [];

        if ($activate) {
            $res    = self::applyGrants($channel, (string) $order['buyer_xchan'], $activate);
            $errors = $res['errors'];
            $prev   = $meta['granted'] ?? ['groups' => [], 'connection' => false];   // a retry after a partial failure
            $meta['granted'] = self::mergeGranted($prev, $res['granted']);
        }

        $stamp = date('Y-m-d h:i:sa T') . ' — ';
        foreach ($errors as $e) $meta['notes'][] = $stamp . 'Fulfilment error: ' . $e;

        // A period is added only on a clean fulfil, so retrying after a
        // failed grant can't hand out two. From here the subscription row
        // owns the grants: expiry has to undo them long after this order.
        $spec = $this->billingOf($products[$oi['item_sku']] ?? []);
        if (!$errors && $spec) {
            $expires = $this->addSubPeriod($channel, (string) $order['buyer_xchan'], $oi['item_sku'], $spec, $meta['granted'] ?? [], $oi);
            unset($meta['granted']);
            $meta['subscription'] = ['expires' => $expires];
            $meta['notes'][] = $stamp . 'Fulfilled — subscribed until ' . $expires . ' UTC';
        } elseif (!$errors) {
            $meta['notes'][] = $stamp . ($activate ? 'Fulfilled — access granted' : 'Fulfilled');
        }

        $this->saveItemState($order['order_hash'], intval($oi['id']), $errors ? 0 : 1, $errors ? 1 : 0, $meta);
    }

    private function cancelItem(array $channel, array $order, array $oi): void
    {
        $meta   = is_array($oi['item_meta']) ? $oi['item_meta'] : [];
        $errors = [];
        if (!empty($meta['granted'])) {
            $errors = self::revokeGrants($channel, (string) $order['buyer_xchan'], $meta['granted']);
            if (!$errors) unset($meta['granted']);
        }
        // Cancelling any order item of a subscription ends the subscription.
        if (!empty($meta['subscription'])) {
            $id = intval($oi['id']);
            foreach ($this->subRows("AND s.sub_nexttrigger IS NOT NULL AND (s.master_itemid = $id OR s.sub_itemid = $id)") as $row) {
                $errors = array_merge($errors, $this->endSub($channel, $row, 'cancelled'));
            }
        }

        $stamp = date('Y-m-d h:i:sa T') . ' — ';
        foreach ($errors as $e) $meta['notes'][] = $stamp . 'Cancellation error: ' . $e;
        $meta['notes'][] = $stamp . 'Cancelled';

        $this->saveItemState($order['order_hash'], intval($oi['id']), 0, $errors ? 1 : 0, $meta);
    }

    private function saveItemState(string $hash, int $itemId, int $fulfilled, int $exception, array $meta): void
    {
        q("UPDATE cart_orderitems
           SET item_fulfilled = %d, item_exception = %d, item_lastupdate = NOW(), item_meta = '%s'
           WHERE order_hash = '%s' AND id = %d",
            $fulfilled, $exception, dbesc(json_encode($meta)), dbesc($hash), $itemId);
    }

    // ── Totals, buyer options, housekeeping, summary ──────────────────────────

    /** @return array{subtotal: float, tax: float, total: float} */
    private function totals(array $order): array
    {
        $sub = $tax = 0.0;
        foreach ($order['items'] ?? [] as $oi) {
            $line = floatval($oi['item_price'] ?? 0) * intval($oi['item_qty'] ?? 1);
            $sub += $line;
            $tax += $line * floatval($oi['item_tax_rate'] ?? 0);
        }
        $sub = round($sub, 2);
        $tax = round($tax, 2);
        return ['subtotal' => $sub, 'tax' => $tax, 'total' => round($sub + $tax, 2)];
    }

    /** order_meta.totals, in the addon's keys. */
    private function totalsMeta(array $t): array
    {
        return ['Subtotal' => $this->fmtPrice($t['subtotal']), 'Tax' => $this->fmtPrice($t['tax']), 'OrderTotal' => $this->fmtPrice($t['total'])];
    }

    /** Gate for placing/paying an order: every required buyer option filled. Returns totals(). */
    private function payable(array $channel, array $order): array
    {
        $products = $this->loadProducts(intval($channel['channel_id']));
        foreach ($order['items'] ?? [] as $oi) {
            foreach ($products[$oi['item_sku']]['_options'] ?? [] as $def) {
                if ($def['required'] && trim((string) ($oi['item_meta']['item_opts'][$def['id']]['value'] ?? '')) === '') {
                    Response::error(400, 'Please fill in "' . $def['label'] . '" for ' . ($oi['item_desc'] ?? $oi['item_sku']));
                }
            }
        }
        return $this->totals($order);
    }

    /** item_meta.item_opts as [{id, label, value}]. */
    private static function itemOptions(array $oi): array
    {
        $out = [];
        foreach ((array) ($oi['item_meta']['item_opts'] ?? []) as $id => $o) {
            if (is_array($o)) $out[] = ['id' => (string) $id, 'label' => html_entity_decode((string) ($o['label'] ?? '')), 'value' => html_entity_decode((string) ($o['value'] ?? ''))];
        }
        return $out;
    }

    private function setItemOptions(array $channel, string $sku, array $values): void
    {
        $hash = $this->findOrderHash($channel, false);
        if (!$hash) Response::error(404, 'No active order');
        $defs = $this->loadProducts(intval($channel['channel_id']))[$sku]['_options'] ?? [];

        foreach ($this->loadOrder($hash)['items'] ?? [] as $oi) {
            if ($oi['item_sku'] !== $sku) continue;
            $opts = [];
            foreach ($defs as $def) {   // only options the product defines; anything else in the body is dropped
                $v = mb_substr(trim(strip_tags((string) ($values[$def['id']] ?? ''))), 0, 1000);
                // Stored entity-encoded, as the addon's templates print these raw.
                if ($v !== '') $opts[$def['id']] = ['label' => htmlentities($def['label']), 'value' => htmlentities($v)];
            }
            $meta = $oi['item_meta'];
            $meta['item_opts'] = $opts;
            $this->saveItemMeta($hash, intval($oi['id']), $meta);
        }
        Response::send($this->formatOrderForBuyer($this->loadOrder($hash), $channel));
    }

    /** Buyer-option definitions, written the way the addon's own editor writes them. */
    private function saveOptionDefs(int $uid, string $sku, array $input, array $old): void
    {
        $known = array_column($old, null, 'id');
        $map   = [];
        foreach ($input as $o) {
            $label = is_array($o) ? trim(strip_tags((string) ($o['label'] ?? ''))) : '';
            if ($label === '' || count($map) >= 20) continue;
            $id = isset($known[$o['id'] ?? '']) ? (string) $o['id'] : new_uuid();   // ids are ours, never the client's
            $map[$id] = [
                'uuid' => $id, 'label' => htmlentities(mb_substr($label, 0, 100)), 'instructions' => '', 'default' => '',
                'required' => empty($o['required']) ? 0 : 1,
                'type' => ($o['type'] ?? '') === 'textarea' ? 'textarea' : 'text', 'values' => '',
            ];
        }
        if ($map) {
            $this->pset($uid, 'cart-orderoptions', 'sku-' . $sku, ['item_sku' => $sku, 'itemoptions' => $map, 'optorder' => array_keys($map)]);
        } else {
            del_pconfig($uid, 'cart-orderoptions', 'sku-' . $sku);
        }
    }

    /** cron hook (hooks/cartsubs.php). */
    public static function onCron(): void
    {
        self::expireSubscriptions();
        self::purgeAbandonedCarts();
    }

    /** Carts untouched past their order_expires. Rows from before expiry was tracked have none and are kept. */
    public static function purgeAbandonedCarts(): void
    {
        $r = q("SELECT order_hash FROM cart_orders
                WHERE order_checkedout IS NULL AND order_expires IS NOT NULL AND order_expires < '%s' LIMIT 500",
                dbesc(datetime_convert()));
        foreach ($r ?: [] as $row) {
            q("DELETE FROM cart_orderitems WHERE order_hash = '%s'", dbesc($row['order_hash']));
            q("DELETE FROM cart_orders WHERE order_hash = '%s' AND order_checkedout IS NULL", dbesc($row['order_hash']));
        }
    }

    private function getSummary(array $channel): void
    {
        $rows = q("SELECT o.order_currency AS currency,
                          COUNT(DISTINCT o.id) AS orders,
                          COUNT(DISTINCT CASE WHEN o.order_paid IS NULL THEN o.id END) AS unpaid,
                          SUM(CASE WHEN o.order_paid IS NOT NULL AND i.item_fulfilled = 0 THEN 1 ELSE 0 END) AS to_fulfil,
                          SUM(CASE WHEN o.order_paid IS NOT NULL
                                   THEN i.item_price * i.item_qty * (1 + COALESCE(i.item_tax_rate, 0)) ELSE 0 END) AS revenue
                   FROM cart_orders o JOIN cart_orderitems i ON i.order_hash = o.order_hash
                   WHERE o.seller_channel = '%s' AND o.order_checkedout IS NOT NULL
                   GROUP BY o.order_currency",
                   dbesc($channel['channel_hash'])) ?: [];

        $out = ['orders' => 0, 'unpaid' => 0, 'to_fulfil' => 0, 'subscribers' => 0, 'revenue' => []];
        foreach ($rows as $r) {
            $out['orders']    += intval($r['orders']);
            $out['unpaid']    += intval($r['unpaid']);
            $out['to_fulfil'] += intval($r['to_fulfil']);
            // Never summed across currencies.
            if (floatval($r['revenue']) > 0) $out['revenue'][] = ['currency' => $r['currency'], 'amount' => $this->fmtPrice(floatval($r['revenue']))];
        }
        foreach ($this->formatSubs($channel, 'AND s.sub_nexttrigger IS NOT NULL', false) as $_) $out['subscribers']++;
        Response::send($out);
    }

    // ── Subscriptions ─────────────────────────────────────────────────────────

    private const TERMS = ['day', 'week', 'month', 'year'];

    private ?bool $subsTable = null;

    /** A product's {interval, term}, or null: one-time, malformed, or no table to track it in. */
    private function billingOf(array $product): ?array
    {
        $b = $product['subscription'] ?? null;
        if (!is_array($b) || intval($b['interval'] ?? 0) < 1 || !in_array($b['term'] ?? '', self::TERMS, true)) return null;
        $this->subsTable ??= q("SELECT id FROM cart_subscriptions LIMIT 1") !== false;
        return $this->subsTable ? ['interval' => intval($b['interval']), 'term' => $b['term']] : null;
    }

    /** Subscription rows with their buyer, seller and product, newest first. $where starts with AND. */
    private function subRows(string $where): array
    {
        // q() always runs vsprintf, so a '%' in an interpolated value (a classic-made sku) must be doubled.
        $where = str_replace('%', '%%', $where);
        return q("SELECT s.*, o.buyer_xchan, o.seller_channel, i.item_sku, i.item_desc
                  FROM cart_subscriptions s
                  JOIN cart_orders o ON o.order_hash = s.master_order_hash
                  JOIN cart_orderitems i ON i.id = s.master_itemid
                  WHERE 1 = 1 $where ORDER BY s.id DESC") ?: [];
    }

    /** All times here are PHP-side UTC strings — never NOW(), whose zone is the DB server's. */
    private static function mergeGranted(array $a, array $b): array
    {
        return [
            'groups'     => array_values(array_unique(array_merge($a['groups'] ?? [], $b['groups'] ?? []))),
            'connection' => !empty($a['connection']) || !empty($b['connection']),
        ];
    }

    /**
     * Start, renew or revive the buyer's subscription to $sku by one period.
     * A live one is extended from its current expiry (renewing early loses
     * nothing); a lapsed one restarts from now. $oi = the order item that
     * paid for it, null for a seller's manual extension.
     */
    private function addSubPeriod(array $channel, string $buyer, string $sku, array $spec, array $granted, ?array $oi): string
    {
        $row = $this->subRows(sprintf("AND o.seller_channel = '%s' AND o.buyer_xchan = '%s' AND i.item_sku = '%s'",
            dbesc($channel['channel_hash']), dbesc($buyer), dbesc($sku)))[0] ?? null;

        $now     = datetime_convert();
        $live    = $row && $row['sub_nexttrigger'] && $row['sub_expires'] > $now;
        $expires = gmdate('Y-m-d H:i:s', strtotime("+{$spec['interval']} {$spec['term']}",
            strtotime(($live ? $row['sub_expires'] : $now) . ' UTC')));

        $meta = $row ? (json_decode($row['sub_meta'] ?? '', true) ?: []) : [];
        $meta['granted'] = self::mergeGranted($meta['granted'] ?? [], $granted);
        unset($meta['ended'], $meta['reason']);

        if ($row) {
            q("UPDATE cart_subscriptions
               SET sub_order_hash = '%s', sub_itemid = %d, sub_expires = '%s', sub_nexttrigger = '%s', sub_meta = '%s'
               WHERE id = %d",
                dbesc($oi['order_hash'] ?? $row['sub_order_hash']), intval($oi['id'] ?? $row['sub_itemid']),
                dbesc($expires), dbesc($expires), dbesc(json_encode($meta)), intval($row['id']));
        } elseif ($oi) {
            q("INSERT INTO cart_subscriptions
               (master_order_hash, master_itemid, sub_order_hash, sub_itemid, sub_expires, sub_nexttrigger, sub_meta)
               VALUES ('%s', %d, '%s', %d, '%s', '%s', '%s')",
                dbesc($oi['order_hash']), intval($oi['id']), dbesc($oi['order_hash']), intval($oi['id']),
                dbesc($expires), dbesc($expires), dbesc(json_encode($meta)));
        }
        return $expires;
    }

    /**
     * End a subscription and take back what it granted. sub_expires is left
     * as the record of when the paid period ran to.
     *
     * @return string[] errors
     */
    private function endSub(array $channel, array $row, string $reason): array
    {
        $meta   = json_decode($row['sub_meta'] ?? '', true) ?: [];
        $errors = self::revokeGrants($channel, (string) $row['buyer_xchan'], $meta['granted'] ?? []);
        if (!$errors) unset($meta['granted']);
        $meta['ended']  = datetime_convert();
        $meta['reason'] = $reason;
        // On an error the grants stay recorded and the row stays live, so the next cron run retries.
        q("UPDATE cart_subscriptions SET sub_nexttrigger = %s, sub_meta = '%s' WHERE id = %d",
            $errors ? "'" . dbesc($row['sub_nexttrigger']) . "'" : 'NULL', dbesc(json_encode($meta)), intval($row['id']));
        return $errors;
    }

    /** cron hook (hooks/cartsubs.php): every seller's subscriptions whose period has run out. */
    public static function expireSubscriptions(): void
    {
        $me = new self;
        if (!$me->billingOf(['subscription' => ['interval' => 1, 'term' => 'day']])) return;   // no table
        foreach ($me->subRows("AND s.sub_nexttrigger IS NOT NULL AND s.sub_nexttrigger <= '" . dbesc(datetime_convert()) . "'") as $row) {
            $seller = channelx_by_hash($row['seller_channel']);
            if (!$seller) continue;
            $errors = $me->endSub($seller, $row, 'expired');
            logger('[spa cart] subscription ' . $row['id'] . ' expired' . ($errors ? ': ' . implode('; ', $errors) : ''), LOGGER_DEBUG);
        }
    }

    private function formatSubs(array $channel, string $where, bool $forSeller): array
    {
        if (!$this->billingOf(['subscription' => ['interval' => 1, 'term' => 'day']])) return [];
        $products = $this->loadProducts(intval($channel['channel_id']));
        $out = [];
        foreach ($this->subRows("AND o.seller_channel = '" . dbesc($channel['channel_hash']) . "' $where") as $row) {
            $p    = $products[$row['item_sku']] ?? null;
            $meta = json_decode($row['sub_meta'] ?? '', true) ?: [];
            $sub  = [
                'id'      => intval($row['id']),
                'sku'     => $row['item_sku'],
                'desc'    => $p['item_description'] ?? $row['item_desc'],
                'active'  => !empty($row['sub_nexttrigger']),
                'expires' => $row['sub_expires'],
                'ended'   => $meta['ended'] ?? null,
                'reason'  => $meta['reason'] ?? null,
                'billing' => $p ? $this->billingOf($p) : null,
                // Renewable = still on sale as a subscription.
                'renewable' => $p && !empty($p['item_active']) && $this->billingOf($p),
            ];
            if ($forSeller) {
                $sub['buyer']      = $row['buyer_xchan'];
                $sub['buyer_name'] = $this->buyerName($row['buyer_xchan']);
            }
            $out[] = $sub;
        }
        return $out;
    }

    private function manageSubscriber(array $channel, int $id, string $action): void
    {
        $row = $id ? ($this->subRows("AND s.id = $id AND o.seller_channel = '" . dbesc($channel['channel_hash']) . "'")[0] ?? null) : null;
        if (!$row) Response::error(404, 'Subscription not found');

        if ($action === 'revoke') {
            $errors = $this->endSub($channel, $row, 'cancelled');
            if ($errors) Response::error(500, implode('; ', $errors));
        } elseif ($action === 'extend') {
            // A free period from the seller. Re-grants first: a lapsed one has had its access taken back.
            $p    = $this->loadProducts(intval($channel['channel_id']))[$row['item_sku']] ?? [];
            $spec = $this->billingOf($p);
            if (!$spec) Response::error(400, 'This product is no longer a subscription');
            $res = self::applyGrants($channel, (string) $row['buyer_xchan'], (array) ($p['activate_commands'] ?? []));
            if ($res['errors']) Response::error(500, implode('; ', $res['errors']));
            $this->addSubPeriod($channel, (string) $row['buyer_xchan'], $row['item_sku'], $spec, $res['granted'], null);
        } else {
            Response::error(400, 'Unknown subscribers action');
        }
        Response::send($this->formatSubs($channel, '', true));
    }

    // ── Buyer: placed orders ──────────────────────────────────────────────────

    private function getMyOrders(array $channel): void
    {
        $buyer = get_observer_hash();
        $rows  = $buyer ? q("SELECT order_hash FROM cart_orders
                             WHERE seller_channel = '%s' AND buyer_xchan = '%s'
                               AND order_checkedout IS NOT NULL
                             ORDER BY id DESC LIMIT 100",
                             dbesc($channel['channel_hash']), dbesc($buyer)) : [];
        $out = [];
        foreach ($rows ?: [] as $row) {
            $out[] = $this->formatOrderForBuyer($this->loadOrder($row['order_hash']), $channel);
        }
        Response::send($out);
    }

    // ── Order helpers ─────────────────────────────────────────────────────────

    private const CART_DAYS = 30;

    /** The buyer's open cart here. Any cart POST counts as activity and pushes its expiry out. */
    private function findOrderHash(array $channel, bool $create): ?string
    {
        $hash = $this->lookupOrderHash($channel, $create);
        if ($hash && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
            q("UPDATE cart_orders SET order_expires = '%s' WHERE order_hash = '%s' AND order_checkedout IS NULL",
                dbesc(gmdate('Y-m-d H:i:s', time() + self::CART_DAYS * 86400)), dbesc($hash));
        }
        return $hash;
    }

    private function lookupOrderHash(array $channel, bool $create): ?string
    {
        $sellerHash = $channel['channel_hash'];
        $buyerXchan = get_observer_hash();
        if (!$buyerXchan) {
            if ($create) Response::error(401, 'Authentication required');
            return null;
        }

        $hash = $_SESSION['cart_order_hash'] ?? null;
        if ($hash) {
            $r = q("SELECT * FROM cart_orders WHERE order_hash = '%s' LIMIT 1", dbesc($hash));
            if ($r && $r[0]['buyer_xchan'] === $buyerXchan
                   && $r[0]['seller_channel'] === $sellerHash
                   && $r[0]['order_checkedout'] === null) {
                return $hash;
            }
        }

        $r = q("SELECT order_hash FROM cart_orders
                WHERE seller_channel = '%s' AND buyer_xchan = '%s'
                  AND order_checkedout IS NULL
                LIMIT 1",
                dbesc($sellerHash), dbesc($buyerXchan));
        if ($r) {
            $_SESSION['cart_order_hash'] = $r[0]['order_hash'];
            return $r[0]['order_hash'];
        }

        if (!$create) return null;

        $hash = new_uuid();
        q("INSERT INTO cart_orders (seller_channel, buyer_xchan, order_hash)
           VALUES ('%s','%s','%s')",
            dbesc($sellerHash), dbesc($buyerXchan), dbesc($hash));
        $_SESSION['cart_order_hash'] = $hash;
        return $hash;
    }

    private function loadOrder(string $hash): ?array
    {
        if (!$hash) return null;
        $r = q("SELECT * FROM cart_orders WHERE order_hash = '%s' LIMIT 1", dbesc($hash));
        if (!$r) return null;
        $order         = $r[0];
        $order['meta'] = json_decode($order['order_meta'] ?? '', true) ?? [];
        $items         = q("SELECT * FROM cart_orderitems WHERE order_hash = '%s'", dbesc($hash));
        $order['items'] = [];
        foreach ($items ?: [] as $oi) {
            $oi['item_meta']  = json_decode($oi['item_meta'] ?? '', true) ?? [];
            $order['items'][] = $oi;
        }
        return $order;
    }

    private function cleanupEmptyOrder(string $hash): void
    {
        $r = q("SELECT COUNT(*) AS cnt FROM cart_orderitems WHERE order_hash = '%s'", dbesc($hash));
        if ($r && intval($r[0]['cnt']) === 0) {
            q("DELETE FROM cart_orders WHERE order_hash = '%s'", dbesc($hash));
            if (($_SESSION['cart_order_hash'] ?? '') === $hash) unset($_SESSION['cart_order_hash']);
        }
    }

    private function isSellerOrder(?array $order, array $channel): bool
    {
        return $order && ($order['seller_channel'] ?? '') === $channel['channel_hash'];
    }

    // ── Order meta ────────────────────────────────────────────────────────────

    private function orderMeta(string $hash): array
    {
        $r = q("SELECT order_meta FROM cart_orders WHERE order_hash = '%s' LIMIT 1", dbesc($hash));
        return $r ? (json_decode($r[0]['order_meta'] ?? '', true) ?? []) : [];
    }

    private function saveOrderMeta(string $hash, array $meta): void
    {
        q("UPDATE cart_orders SET order_meta = '%s' WHERE order_hash = '%s'",
            dbesc(json_encode($meta)), dbesc($hash));
    }

    private function itemMeta(string $hash, int $itemId): array
    {
        $r = q("SELECT item_meta FROM cart_orderitems WHERE order_hash = '%s' AND id = %d LIMIT 1",
                dbesc($hash), $itemId);
        return $r ? (json_decode($r[0]['item_meta'] ?? '', true) ?? []) : [];
    }

    private function saveItemMeta(string $hash, int $itemId, array $meta): void
    {
        q("UPDATE cart_orderitems SET item_meta = '%s' WHERE order_hash = '%s' AND id = %d",
            dbesc(json_encode($meta)), dbesc($hash), $itemId);
    }

    // ── Catalog management (seller) ───────────────────────────────────────────

    private function saveCatalogItem(array $channel, array $body): void
    {
        $uid   = intval($channel['channel_id']);
        $sku   = trim($body['sku'] ?? '');
        $desc  = trim(strip_tags($body['description'] ?? ''));
        $price = max(0.0, floatval($body['price'] ?? 0));

        if (!$desc) Response::error(400, 'Description required');

        $products = $this->loadProducts($uid);

        if (!$sku) {
            // cart_orderitems.item_sku is varchar(25): 22 leaves room for a "-nn" suffix.
            $base = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $desc));
            $sku  = trim(substr($base, 0, 22), '-') ?: 'item';
            $orig = $sku;
            $n    = 1;
            while (isset($products[$sku])) {
                $sku = $orig . '-' . $n++;
            }
        } elseif (!isset($products[$sku])) {
            Response::error(404, 'Item not found');
        }

        $old      = $products[$sku] ?? [];
        $activate = (array) ($old['activate_commands'] ?? []);
        $deact    = (array) ($old['deactivate_commands'] ?? []);

        // Absent `grants` = leave them alone (an older client, or a toggle).
        if (is_array($body['grants'] ?? null)) {
            $groups = array_values(array_filter((array) ($body['grants']['groups'] ?? []),
                fn($h) => is_string($h) && AccessList::by_hash($uid, $h)));
            [$activate, $deact] = self::grantsToCommands(
                ['groups' => $groups, 'connect' => !empty($body['grants']['connect'])], $activate, $deact);
        }

        // `billing` absent = leave alone; null = one-time; {interval, term} = recurring.
        $subscription = $old['subscription'] ?? null;
        if (array_key_exists('billing', $body)) {
            $b    = is_array($body['billing']) ? $body['billing'] : [];
            $n    = intval($b['interval'] ?? 0);
            $term = (string) ($b['term'] ?? '');
            $subscription = ($n >= 1 && $n <= 365 && in_array($term, self::TERMS, true))
                ? ['interval' => $n, 'term' => $term] : null;
        }
        unset($old['subscription']);

        // Like the rest: absent = leave alone.
        if (array_key_exists('tax_percent', $body)) {
            $old['item_tax_rate'] = min(99.99, max(0.0, floatval($body['tax_percent']))) / 100;
        }
        if (is_array($body['options'] ?? null)) {
            $this->saveOptionDefs($uid, $sku, $body['options'], $old['_options'] ?? []);
        }

        $type = $activate ? 'hzservices' : 'manualcat';
        if (isset($old['_type']) && $old['_type'] !== $type) $this->deleteProduct($uid, $sku, $old['_type']);

        $this->saveProduct($uid, $sku, [
            '_type'               => $type,
            'item_active'         => (!isset($body['active']) || $body['active']) ? 1 : 0,
            'item_description'    => $desc,
            'item_price'          => $price,
            'item_photo_url'      => trim($body['photo_url'] ?? ''),
            'activate_commands'   => $activate,
            'deactivate_commands' => $deact,
        ] + ($subscription ? ['subscription' => $subscription] : []) + $old);

        $this->getCatalog($channel, true);
    }

    private function deleteCatalogItem(array $channel, string $sku): void
    {
        if (!$sku) Response::error(400, 'sku required');
        $uid = intval($channel['channel_id']);
        $d   = $this->loadProducts($uid)[$sku] ?? null;
        if ($d) {
            $this->deleteProduct($uid, $sku, $d['_type']);
            del_pconfig($uid, 'cart-orderoptions', 'sku-' . $sku);
        }
        $this->getCatalog($channel, true);
    }

    private function toggleCatalogItem(array $channel, string $sku): void
    {
        if (!$sku) Response::error(400, 'sku required');
        $uid = intval($channel['channel_id']);
        $d   = $this->loadProducts($uid)[$sku] ?? null;
        if (!$d) Response::error(404, 'Item not found');

        $d['item_active'] = empty($d['item_active']) ? 1 : 0;
        $this->saveProduct($uid, $sku, $d);
        $this->getCatalog($channel, true);
    }

    // ── Catalog helpers ───────────────────────────────────────────────────────

    private function findCatalogItem(array $channel, string $sku): ?array
    {
        $d = $this->loadProducts(intval($channel['channel_id']))[$sku] ?? null;
        return ($d && !empty($d['item_active'])) ? $this->formatCatalogItem($sku, $d) : null;
    }

    // ── Formatters ────────────────────────────────────────────────────────────

    private function formatOrderForBuyer(?array $order, array $channel): array
    {
        if (!$order) return $this->emptyOrder();

        $items    = [];
        $subtotal = 0.0;
        foreach ($order['items'] ?? [] as $oi) {
            $price     = floatval($oi['item_price'] ?? 0);
            $qty       = intval($oi['item_qty'] ?? 1);
            $subtotal += $price * $qty;
            $items[]   = [
                'id'        => intval($oi['id']),
                'sku'       => $oi['item_sku'],
                'desc'      => $oi['item_desc'] ?? $oi['item_sku'],
                'qty'       => $qty,
                'price_raw' => $price,
                'price'     => $this->fmtPrice($price),
                'type'      => $oi['item_type'] ?? '',
                'fulfilled' => (bool) intval($oi['item_fulfilled'] ?? 0),
                'access'    => !empty($oi['item_meta']['granted']) || ($oi['item_type'] ?? '') === 'hzservices',
                'options'   => self::itemOptions($oi),
            ];
        }

        $paid = !empty($order['order_paid']) && $order['order_paid'] !== '0000-00-00 00:00:00';

        return [
            'hash'           => $order['order_hash'],
            'items'          => $items,
            'subtotal_raw'   => $subtotal,
            'subtotal'       => $this->fmtPrice($subtotal),
            ...$this->totalFields($order),
            // order_currency is only meaningful once stamped at checkout.
            'currency'       => empty($order['order_checkedout'])
                ? $this->shopCurrency(intval($channel['channel_id']))
                : ($order['order_currency'] ?? 'USD'),
            'checked_out'    => !empty($order['order_checkedout']),
            'checked_out_at' => $order['order_checkedout'] ?: null,
            'paid'           => $paid,
            'paid_at'        => $paid ? $order['order_paid'] : null,
        ];
    }

    private function formatOrderForSeller(?array $order): array
    {
        if (!$order) return $this->emptyOrder();

        $items    = [];
        $subtotal = 0.0;
        foreach ($order['items'] ?? [] as $oi) {
            $price     = floatval($oi['item_price'] ?? 0);
            $qty       = intval($oi['item_qty'] ?? 1);
            $subtotal += $price * $qty;
            $meta      = is_array($oi['item_meta']) ? $oi['item_meta'] : [];
            $items[]   = [
                'id'        => intval($oi['id']),
                'sku'       => $oi['item_sku'],
                'desc'      => $oi['item_desc'] ?? $oi['item_sku'],
                'qty'       => $qty,
                'price_raw' => $price,
                'price'     => $this->fmtPrice($price),
                'type'      => $oi['item_type'] ?? '',
                'options'   => self::itemOptions($oi),
                'access'    => !empty($meta['granted']) || ($oi['item_type'] ?? '') === 'hzservices',
                'fulfilled' => (bool) intval($oi['item_fulfilled'] ?? 0),
                'confirmed' => (bool) intval($oi['item_confirmed'] ?? 0),
                'exception' => (bool) intval($oi['item_exception'] ?? 0),
                'notes'     => $meta['notes'] ?? [],
            ];
        }

        $meta    = is_array($order['meta']) ? $order['meta'] : [];
        $paid    = !empty($order['order_paid']) && $order['order_paid'] !== '0000-00-00 00:00:00';
        $payment = $meta['payment'] ?? null;
        $flags   = [
            'confirmed' => !in_array(false, array_column($items, 'confirmed'), true),
            'fulfilled' => count($items) > 0 && !in_array(false, array_column($items, 'fulfilled'), true),
            'exception' => in_array(true, array_column($items, 'exception'), true),
        ];

        return [
            'hash'         => $order['order_hash'],
            'items'        => $items,
            'subtotal_raw' => $subtotal,
            'subtotal'     => $this->fmtPrice($subtotal),
            ...$this->totalFields($order),
            'currency'     => $order['order_currency'] ?? 'USD',
            'checked_out'  => !empty($order['order_checkedout']),
            'checked_out_at' => $order['order_checkedout'] ?: null,
            'paid_at'      => $paid ? $order['order_paid'] : null,
            'buyer'        => $order['buyer_xchan'] ?? '',
            'buyer_name'   => $this->buyerName($order['buyer_xchan'] ?? ''),
            'paid'         => $paid,
            'payment'      => $payment,
            'flags'        => $flags,
            'notes'        => $meta['notes'] ?? [],
        ];
    }

    private function totalFields(array $order): array
    {
        $t = $this->totals($order);
        return ['tax_raw' => $t['tax'], 'tax' => $this->fmtPrice($t['tax']), 'total_raw' => $t['total'], 'total' => $this->fmtPrice($t['total'])];
    }

    private function emptyOrder(): array
    {
        return [
            'hash'        => null,
            'items'       => [],
            'subtotal_raw'=> 0,
            'subtotal'    => '0.00',
            'tax_raw'     => 0,
            'tax'         => '0.00',
            'total_raw'   => 0,
            'total'       => '0.00',
            'currency'    => 'USD',
            'checked_out' => false,
            'paid'        => false,
        ];
    }

    // ── Utilities ─────────────────────────────────────────────────────────────

    private function pget(int $uid, string $cat, string $key): mixed
    {
        $r = q("SELECT v FROM pconfig WHERE uid = %d AND cat = '%s' AND k = '%s' LIMIT 1",
                $uid, dbesc($cat), dbesc($key));
        if (!$r) return null;
        $decoded = json_decode($r[0]['v'], true);
        return json_last_error() === JSON_ERROR_NONE ? $decoded : $r[0]['v'];
    }

    /** Stored the way the addon stores its own: a plain JSON string, not set_pconfig's array encoding. */
    private function pset(int $uid, string $cat, string $key, mixed $value): void
    {
        set_pconfig($uid, $cat, $key, json_encode($value));
    }

    /** cart_currency is the addon's key; `currency` is where this handler used to write. */
    private function shopCurrency(int $uid, string $default = 'USD'): string
    {
        $c = $this->pget($uid, 'cart', 'cart_currency') ?: $this->pget($uid, 'cart', 'currency') ?: $default;
        return preg_match('/^[A-Z]{3}$/', (string) $c) ? $c : $default;
    }

    private function fmtPrice(float $amount): string
    {
        return number_format($amount, 2, '.', ',');
    }

    private function buyerName(string $xchan): string
    {
        if (!$xchan) return '';
        $r = q("SELECT xchan_name, xchan_addr FROM xchan WHERE xchan_hash = '%s' LIMIT 1",
                dbesc($xchan));
        if (!$r) return $xchan;
        return $r[0]['xchan_name'] . ' (' . $r[0]['xchan_addr'] . ')';
    }
}
