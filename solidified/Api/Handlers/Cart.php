<?php
namespace Theme\Solidified\Api\Handlers;

use Theme\Solidified\Api\Auth;
use Theme\Solidified\Api\Response;

/**
 * Cart JSON API — direct DB/pconfig implementation.
 *
 * GET  /api/cart/:nick/catalog
 * GET  /api/cart/:nick/order
 * GET  /api/cart/:nick/payment-config
 * GET  /api/cart/:nick/payment-settings        (seller)
 * GET  /api/cart/:nick/orders                  (seller)
 * GET  /api/cart/:nick/orders/:hash            (seller)
 * POST /api/cart/:nick/item                    {sku, qty?}
 * POST /api/cart/:nick/item/remove             {sku}
 * POST /api/cart/:nick/item/qty                {sku, qty}
 * POST /api/cart/:nick/checkout                — manual checkout (contact seller)
 * POST /api/cart/:nick/payment-settings              (seller) {paypal:{…}, razorpay:{…}, cashfree:{…}, upi:{…}, manual:{…}}
 * POST /api/cart/:nick/paypal/create-order           {order_hash} → {paypal_order_id}
 * POST /api/cart/:nick/paypal/capture                {paypal_order_id, order_hash} → {paid}
 * POST /api/cart/:nick/razorpay/create-order         {order_hash} → {razorpay_order_id, key_id, amount, currency}
 * POST /api/cart/:nick/razorpay/verify               {razorpay_order_id, razorpay_payment_id, razorpay_signature, order_hash}
 * POST /api/cart/:nick/cashfree/create-order         {order_hash, customer_phone} → {payment_session_id, mode}
 * POST /api/cart/:nick/cashfree/verify               {order_hash} → {paid}
 * POST /api/cart/:nick/orders/:hash/markpaid
 * POST /api/cart/:nick/orders/:hash/note       {text}
 * POST /api/cart/:nick/orders/:hash/items/:id/fulfill|cancel
 */
class Cart
{
    // ── Dispatch ──────────────────────────────────────────────────────────────

    public function get(): void
    {
        $nick    = \App::$argv[2] ?? '';
        $channel = $this->resolveChannel($nick);
        $action  = \App::$argv[3] ?? 'order';

        switch ($action) {
            case 'catalog':
                $this->getCatalog($channel);
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
        Auth::requireLocalJson();

        $nick    = \App::$argv[2] ?? '';
        $channel = $this->resolveChannel($nick);
        $action  = \App::$argv[3] ?? '';
        $body    = Auth::$parsedBody ?? [];

        switch ($action) {
            case 'item':
                $sub = \App::$argv[4] ?? '';
                if ($sub === 'remove') {
                    $this->removeItem($channel, $body['sku'] ?? '');
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

    private function getCatalog(array $channel): void
    {
        $uid = intval($channel['channel_id']);
        $out = [];

        $skulist = $this->pget($uid, 'cart-manualcat', 'skulist');
        if (is_array($skulist)) {
            foreach ($skulist as $sku) {
                $d = $this->pget($uid, 'cart-manualcat', 'sku-' . $sku);
                if (!is_array($d) || empty($d['item_active'])) continue;
                $out[] = $this->formatCatalogItem($sku, $d);
            }
        }

        $services = $this->pget($uid, 'cart-hzservices', 'skus');
        if (is_array($services)) {
            foreach ($services as $sku => $d) {
                if (!is_array($d) || empty($d['item_active'])) continue;
                $out[] = $this->formatCatalogItem((string) $sku, $d);
            }
        }

        Response::send($out);
    }

    private function formatCatalogItem(string $sku, array $d): array
    {
        $price    = floatval($d['item_price'] ?? 0);
        $photoUrl = $d['item_photo_url'] ?? '';
        if ($photoUrl && strpos($photoUrl, 'http') !== 0) {
            $photoUrl = z_root() . $photoUrl;
        }
        return [
            'sku'       => $sku,
            'desc'      => $d['item_description'] ?? $d['item_desc'] ?? $sku,
            'price_raw' => $price,
            'price'     => $this->fmtPrice($price),
            'photo_url' => $photoUrl ?: null,
            'type'      => $d['item_type'] ?? 'manual',
        ];
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

        foreach ($order['items'] ?? [] as $oi) {
            if ($oi['item_sku'] === $sku) {
                $newQty = intval($oi['item_qty']) + $qty;
                q("UPDATE cart_orderitems SET item_qty = %d WHERE order_hash = '%s' AND id = %d",
                    $newQty, dbesc($hash), intval($oi['id']));
                Response::send($this->formatOrderForBuyer($this->loadOrder($hash), $channel));
                return;
            }
        }

        q("INSERT INTO cart_orderitems (order_hash, item_sku, item_desc, item_type, item_qty, item_price)
           VALUES ('%s','%s','%s','%s',%d,%f)",
            dbesc($hash),
            dbesc($sku),
            dbesc($catalogItem['desc']),
            dbesc($catalogItem['type']),
            $qty,
            floatval($catalogItem['price_raw'])
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
            q("UPDATE cart_orderitems SET item_qty = %d WHERE order_hash = '%s' AND item_sku = '%s'",
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

        q("UPDATE cart_orders SET order_checkedout = NOW() WHERE order_hash = '%s'", dbesc($hash));

        $hint = $paymentHint ? strip_tags(trim($paymentHint)) : 'Contact seller to arrange payment';
        $meta            = $this->orderMeta($hash);
        $meta['notes'][] = date('Y-m-d h:i:sa T') . ' — Order placed. ' . $hint;
        $this->saveOrderMeta($hash, $meta);

        unset($_SESSION['cart_order_hash']);

        Response::send(['checked_out' => true, 'order_hash' => $hash]);
    }

    // ── Payment config ────────────────────────────────────────────────────────

    private function getPaymentConfig(array $channel): void
    {
        $uid      = intval($channel['channel_id']);
        $settings = $this->loadPaymentSettingsRaw($uid);
        $currency = $this->pget($uid, 'cart', 'currency') ?? 'USD';

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

        // Preserve secrets when client sends masked placeholder
        foreach (['paypal' => 'secret', 'razorpay' => 'key_secret', 'cashfree' => 'secret_key'] as $provider => $field) {
            $v = $settings[$provider][$field];
            if ($v === '' || str_starts_with($v, '••')) {
                $settings[$provider][$field] = $existing[$provider][$field] ?? '';
            }
        }

        q("INSERT INTO pconfig (uid, cat, k, v) VALUES (%d,'cart-payments','settings','%s')
           ON DUPLICATE KEY UPDATE v='%s'",
            $uid,
            dbesc(json_encode($settings)),
            dbesc(json_encode($settings))
        );

        // Return masked settings (never expose secrets to frontend)
        $settings['paypal']['secret']        = $settings['paypal']['secret']        ? '••••••••' : '';
        $settings['razorpay']['key_secret']  = $settings['razorpay']['key_secret']  ? '••••••••' : '';
        $settings['cashfree']['secret_key']  = $settings['cashfree']['secret_key']  ? '••••••••' : '';
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

        // Calculate total from order items
        $total    = 0.0;
        $currency = strtoupper($order['order_currency'] ?? 'USD');
        foreach ($order['items'] as $oi) {
            $total += floatval($oi['item_price'] ?? 0) * intval($oi['item_qty'] ?? 1);
        }

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
        q("UPDATE cart_orders SET order_checkedout = NOW(), order_paid = NOW()
           WHERE order_hash = '%s'", dbesc($orderHash));

        $meta              = $this->orderMeta($orderHash);
        $meta['payment']   = ['provider' => 'paypal', 'txn_id' => $txnId, 'paypal_order_id' => $paypalOrderId];
        $meta['notes'][]   = date('Y-m-d h:i:sa T') . ' — Paid via PayPal (txn: ' . $txnId . ')';
        $this->saveOrderMeta($orderHash, $meta);

        unset($_SESSION['cart_order_hash']);

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

        $total    = 0.0;
        $currency = strtoupper($rp['currency'] ?? 'INR');
        foreach ($order['items'] as $oi) {
            $total += floatval($oi['item_price'] ?? 0) * intval($oi['item_qty'] ?? 1);
        }
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

        q("UPDATE cart_orders SET order_checkedout = NOW(), order_paid = NOW()
           WHERE order_hash = '%s'", dbesc($orderHash));

        $meta              = $this->orderMeta($orderHash);
        $meta['payment']   = ['provider' => 'razorpay', 'txn_id' => $rzpPaymentId, 'razorpay_order_id' => $rzpOrderId];
        $meta['notes'][]   = date('Y-m-d h:i:sa T') . ' — Paid via Razorpay (txn: ' . $rzpPaymentId . ')';
        $this->saveOrderMeta($orderHash, $meta);

        unset($_SESSION['cart_order_hash']);

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
        $currency = $this->pget($uid, 'cart', 'currency') ?? 'INR';

        // Total in paisa units handled by Cashfree as decimal rupees (not like Razorpay)
        $amount = 0.0;
        foreach ($order['items'] as $item) {
            $amount += floatval($item['item_price']) * intval($item['item_qty']);
        }
        $amount = round($amount, 2);

        // Derive customer email from xchan_addr; fallback to a placeholder
        $buyerXchan = get_observer_hash();
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

        q("UPDATE cart_orders SET order_checkedout = NOW(), order_paid = NOW()
           WHERE order_hash = '%s'", dbesc($orderHash));

        $meta            = $this->orderMeta($orderHash);
        $meta['payment'] = ['provider' => 'cashfree', 'txn_id' => (string) $txnId];
        $meta['notes'][] = date('Y-m-d h:i:sa T') . ' — Paid via Cashfree (txn: ' . $txnId . ')';
        $this->saveOrderMeta($orderHash, $meta);

        unset($_SESSION['cart_order_hash']);

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

        q("UPDATE cart_orders SET order_paid = NOW() WHERE order_hash = '%s'", dbesc($hash));

        $meta            = $this->orderMeta($hash);
        $meta['notes'][] = date('Y-m-d h:i:sa T') . ' — Marked Paid (manual)';
        $this->saveOrderMeta($hash, $meta);

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

        $found = false;
        foreach ($order['items'] as $oi) {
            if (intval($oi['id']) === $itemId) { $found = true; break; }
        }
        if (!$found) Response::error(404, 'Item not found');

        if ($action === 'fulfill') {
            q("UPDATE cart_orderitems SET item_fulfilled = 1 WHERE id = %d AND order_hash = '%s'",
                $itemId, dbesc($hash));
            $meta            = $this->itemMeta($hash, $itemId);
            $meta['notes'][] = date('Y-m-d h:i:sa T') . ' — Fulfilled';
            $this->saveItemMeta($hash, $itemId, $meta);
        } else {
            q("UPDATE cart_orderitems SET item_fulfilled = 0 WHERE id = %d AND order_hash = '%s'",
                $itemId, dbesc($hash));
            $meta            = $this->itemMeta($hash, $itemId);
            $meta['notes'][] = date('Y-m-d h:i:sa T') . ' — Cancelled';
            $this->saveItemMeta($hash, $itemId, $meta);
        }

        Response::send($this->formatOrderForSeller($this->loadOrder($hash)));
    }

    // ── Order helpers ─────────────────────────────────────────────────────────

    private function findOrderHash(array $channel, bool $create): ?string
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

    // ── Catalog helpers ───────────────────────────────────────────────────────

    private function findCatalogItem(array $channel, string $sku): ?array
    {
        $uid     = intval($channel['channel_id']);
        $skulist = $this->pget($uid, 'cart-manualcat', 'skulist');
        if (is_array($skulist) && isset($skulist[$sku])) {
            $d = $this->pget($uid, 'cart-manualcat', 'sku-' . $sku);
            if (is_array($d) && !empty($d['item_active'])) return $this->formatCatalogItem($sku, $d);
        }
        $services = $this->pget($uid, 'cart-hzservices', 'skus');
        if (is_array($services) && isset($services[$sku])) {
            $d = $services[$sku];
            if (is_array($d) && !empty($d['item_active'])) return $this->formatCatalogItem($sku, $d);
        }
        return null;
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
            ];
        }

        $paid = !empty($order['order_paid']) && $order['order_paid'] !== '0000-00-00 00:00:00';

        return [
            'hash'         => $order['order_hash'],
            'items'        => $items,
            'subtotal_raw' => $subtotal,
            'subtotal'     => $this->fmtPrice($subtotal),
            'currency'     => $order['order_currency'] ?? 'USD',
            'checked_out'  => !empty($order['order_checkedout']),
            'paid'         => $paid,
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
            'currency'     => $order['order_currency'] ?? 'USD',
            'checked_out'  => !empty($order['order_checkedout']),
            'buyer'        => $order['buyer_xchan'] ?? '',
            'buyer_name'   => $this->buyerName($order['buyer_xchan'] ?? ''),
            'paid'         => $paid,
            'payment'      => $payment,
            'flags'        => $flags,
            'notes'        => $meta['notes'] ?? [],
        ];
    }

    private function emptyOrder(): array
    {
        return [
            'hash'        => null,
            'items'       => [],
            'subtotal_raw'=> 0,
            'subtotal'    => '0.00',
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
