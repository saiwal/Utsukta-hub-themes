# Cart

`/cart/:nick` is a shop on top of the upstream `cart` addon's storage. The SPA
reads and writes the addon's tables and pconfig keys, so the classic pages and
the SPA show the same shop, but it never calls the addon's hooks: every
state change is done in `Handlers/Cart.php` and `Concerns/CartFulfillment.php`.

Why not the hooks: the addon's service rollback never assigns `$groupname` (a
cancel removes nobody from a privacy group), its fulfil reads
`App::$profile`, which is unset under `/spa`, its subscription SQL is
MySQL-only `interval` arithmetic, and its subscription cron nulls expiry dates
without managing to revoke anything. The cost is that a third-party addon
hooking `cart_fulfill_*` does not fire for SPA orders; none exist in hzaddons.

## Storage

| What | Where | Notes |
|---|---|---|
| Plain products | pconfig `cart-manualcat`: `skulist` (a `sku => sku` map; older SPA saves wrote a list, both are read) and `sku-<sku>` | sold as `item_type` `manualcat` |
| Access products | pconfig `cart-hzservices` / `skus`, one map of all of them | sold as `hzservices`; carry `activate_commands` / `deactivate_commands` |
| Buyer-option definitions | pconfig `cart-orderoptions` / `sku-<sku>` → `itemoptions` | the addon's orderoptions shape; text and textarea only |
| Shop currency | pconfig `cart` / `cart_currency` (plain string) | falls back to the old SPA key `cart` / `currency` |
| Payment provider settings | pconfig `cart-payments` / `settings` | SPA only; secrets never leave the server |
| Orders, items | `cart_orders`, `cart_orderitems` | below |
| Subscriptions | `cart_subscriptions` | below |

Everything is written with `set_pconfig($uid, $cat, $k, json_encode($v))`: a
plain JSON string, which is what the addon's `cart_maybejson()` stores. Do not
pass an array to `set_pconfig`: its own array encoding is a different format
and the addon would not read it.

A product moves between the two product stores when grants are added to or
removed from it (`saveCatalogItem`). Two keys on a product are SPA additions
the addon ignores: `subscription` (`{interval, term}`) and `item_tax_rate`
(a fraction; the addon already passes that key through to the order item).

`cart_orderitems.item_sku` is `varchar(25)`. Generated SKUs are capped at 22
characters plus a `-n` suffix; a longer one is truncated on insert and then no
longer matches its own product.

### Columns

| Column | Meaning here |
|---|---|
| `cart_orders.order_checkedout` | the order was placed; from then on it is frozen (`findOrderHash` only ever returns un-checked-out orders) |
| `order_paid` | set by `settlePaid()` only |
| `order_currency` | stamped at checkout. Before that it holds the column default (`USD`) whatever the shop sells in, so anything pricing an open cart uses `shopCurrency()` instead |
| `order_expires` | cart abandonment deadline (below); meaningless once checked out |
| `order_meta` | `notes[]`, `payment` (`provider`, `txn_id`), `totals` (`Subtotal`/`Tax`/`OrderTotal`, the addon's keys, snapshotted at checkout and at payment) |
| `item_type` | `manualcat` or `hzservices`. Fulfilment does not depend on it: grants are looked up by sku, so rows written as `manual` by older builds still work |
| `item_tax_rate` | fraction, copied from the product when the item is added |
| `item_confirmed` | payment settled |
| `item_fulfilled` | delivered / access granted |
| `item_exception` | a grant or revocation failed; the reason is the item's last note |
| `item_lastupdate` | every mutation |
| `item_meta` | `notes[]`, `granted` (what fulfilment changed, below), `item_opts` (buyer answers), `subscription` (`expires` at the time of fulfilment) |

`buyer_altid` is unused.

## Access products

A product's grants are the addon's command maps, `cmdhash => {cmdhash, cmd,
params}`: `addtoprivacygroup` / `rmvfromprivacygroup` (`params.group` = the
`pgrp.hash`), `addconnection` / `rmvconnection`, and `setsvcclass`. The form
edits `{groups: [hash…], connect: bool}`; `grantsToCommands()` /
`commandsToGrants()` convert, using the addon's own `md5()` cmdhash recipe so a
product round-trips with the classic editor. The deactivate map is always the
mirror of the activate map.

`applyGrants()` returns what it **changed**, and that is stored
(`item_meta.granted`, or the subscription row for a subscription).
`revokeGrants()` undoes only that. So a buyer who was already in the group, or
already a connection, before buying is untouched by a cancel. The known
ceiling: bookkeeping is per item, so if two fulfilled orders grant the same
group, only the first records it, and cancelling that one removes the buyer
while the second is still valid.

Grants are read from the product **as it is at fulfilment time**, not as it was
when ordered. Revocation uses the recorded snapshot, so later edits to the
product cannot change what a cancel removes.

`setsvcclass` (admin sellers, classic editor only) is preserved on save but
never run: it writes the `account` table. Fulfilment reports it as an error to
apply by hand, which flags the item.

The public catalog exposes `access: true`, never the group: privacy-group names
are the seller's. `grants` is only in the seller's `?all=1` catalog, and group
hashes in a save are filtered to the seller's own (`AccessList::by_hash`).

## Payment and fulfilment

`settlePaid()` is the one place an order becomes paid. PayPal capture, Razorpay
verify, Cashfree verify and the seller's "mark paid" all call it. It stamps the
currency if the order was not yet checked out, sets `order_paid`, confirms every
item, snapshots totals, and calls `fulfilItem()` for every item whose product
has grants. Other items wait for the seller.

`fulfilItem()` is idempotent on an already-fulfilled item. On a grant error it
leaves the item unfulfilled with `item_exception = 1` and keeps the partial
`granted`; the seller's Fulfil button retries and merges.

`totals()` is the only place an amount is computed (subtotal + per-item tax).
`payable()` wraps it and is called before anything that places or charges an
order (checkout and each gateway's create-order): it refuses an order that has
a required buyer option left empty. Razorpay charges in its own configured
currency, not the shop's; that predates this code and was left alone because
unifying it could change what existing sellers charge.

Cart POSTs use `Auth::requireLoggedInJson()`, so a magic-auth visitor from
another hub can order. Every seller action is separately gated by
`requireSeller()` (the local owner).

## Subscriptions

A product with `subscription: {interval, term}` sells one period per paid order.
`cart_subscriptions` holds **one row per buyer + product**, extended in place:

| Column | Use |
|---|---|
| `master_order_hash`, `master_itemid` | the first order item; joins give the buyer, seller and sku |
| `sub_order_hash`, `sub_itemid` | the latest order item that paid for a period |
| `sub_expires` | end of the paid period. Left in place after the subscription ends, as the record |
| `sub_nexttrigger` | non-NULL = live, and the time cron acts on |
| `sub_meta` | `granted` (the grants this subscription made), `ended`, `reason` (`expired` / `cancelled`) |

`sub_nexttrigger`, not `sub_expires`, decides whether a subscription is live.
The addon registers its own subscription cron whenever it is loaded, and that
cron nulls any past `sub_expires` while failing to revoke; keyed on
`sub_expires`, the row would vanish from our expiry pass with access still
granted. The addon never touches `sub_nexttrigger`. `Cart.test.php` covers
exactly this (expiry with `sub_expires` already NULL).

- **Start / renew / revive** (`addSubPeriod`): a live subscription is extended
  from its current expiry; a lapsed one restarts from now. A period is only
  added on a clean fulfil, so retrying after a failed grant cannot add two.
- **End** (`endSub`): revokes `sub_meta.granted` and NULLs `sub_nexttrigger`. If
  revocation fails the row stays live and the next cron run retries.
- A subscription owns its grants because expiry has to undo them long after the
  order that paid for them; the order item's `granted` is moved into the row.
- Cancelling any order item that paid for a subscription ends it.
- The seller's **Extend** applies the product's grants again and adds a period.

All subscription times are PHP-side UTC strings (`datetime_convert()`), never
`NOW()`, whose zone is the database server's.

The table is created by the addon, and its Postgres definition is invalid
(`mediumtext`), so it only exists on MySQL. `billingOf()` returns null when the
table is missing: billing is then ignored and products sell one-time.

Renewal is manual. Auto-charging needs each gateway's recurring-billing API and
webhooks; nothing here stores a payment method.

## Cron

The theme registers one `cron` hook (`src/hooks/cartsubs.php` →
`Cart::onCron()`, listed in `SpaTheme::HOOKS`, so an existing hub must
re-enable the theme):

- `expireSubscriptions()`: ends every subscription whose `sub_nexttrigger` has
  passed, across all sellers.
- `purgeAbandonedCarts()`: every cart POST pushes `order_expires` 30 days out;
  carts past it that were never checked out are deleted with their items. Rows
  with no `order_expires` (from before it was tracked) are kept, and a placed
  order is never deleted, paid or not.

## Frontend

`src/modules/cart/`: `api.ts` (types + fetchers), `store.ts` (module-level
signals; `loadCatalog(nick)` seeds it), and the routed view is the usual locked
header + content widget pair. The content widget only switches tabs; the UI is
in `views/`:

| File | Holds |
|---|---|
| `views/Shop.tsx` | buyer side: catalog grid, cart (with buyer-option fields and totals), My orders, `SubscriptionRow`, `useBillingText` |
| `views/Checkout.tsx` | payment methods: PayPal, Razorpay, Cashfree, UPI, contact-seller |
| `views/Seller.tsx` | My Shop: sales tiles, orders, order detail, catalog manager and product form, subscribers, payment settings |

Sidebar widgets (`widgets/`): `cart.cart`, `cart.item_card` (multi-instance),
`cart.my_subscriptions` and `cart.subscribers`. The two subscription widgets
fetch through `createQueryResource` with the page nick rather than the store,
because they can sit on channel and profile pages where the cart store was
never loaded. `cart.my_subscriptions` renders nothing when the viewer has no
subscription at that shop; `cart.subscribers` is `visitorVisible: false` and
also checks the owner role itself.

Access products and subscriptions are capped at quantity 1 on the server; the
UI hides the stepper for them.

## Endpoints

All under `/spa/cart/:nick/`.

| Method | Path | Who | |
|---|---|---|---|
| GET | `catalog` | anyone | active products; `?all=1` (owner) adds inactive ones and `grants` |
| GET | `order` | buyer | the open cart |
| POST | `item` `{sku, qty?}`, `item/qty`, `item/remove`, `item/options` `{sku, options: {id: value}}` | buyer | each returns the cart |
| POST | `checkout` | buyer | place the order without paying online |
| GET | `payment-config` | anyone | enabled providers and their public keys, shop currency |
| POST | `paypal/create-order`, `paypal/capture`, `razorpay/create-order`, `razorpay/verify`, `cashfree/create-order`, `cashfree/verify` | buyer | |
| GET | `my-orders`, `my-subscriptions` | buyer | |
| GET/POST | `payment-settings` | owner | secrets come back masked; a masked value on save keeps the stored one |
| POST | `catalog`, `catalog/toggle`, `catalog/delete` | owner | on save, `grants`, `billing`, `tax_percent` and `options` each mean "leave alone" when absent |
| GET | `groups` | owner | privacy groups for the grants picker |
| GET | `orders`, `orders/:hash`, `summary` | owner | |
| POST | `orders/:hash/markpaid`, `orders/:hash/note`, `orders/:hash/items/:id/fulfill`, `…/cancel` | owner | |
| GET | `subscribers` | owner | |
| POST | `subscribers/:id/extend`, `subscribers/:id/revoke` | owner | |

## Checks

`npm run test:php -- Cart` (after `npm run build`; they run against the
deployed theme):

- `Concerns/CartFulfillment.test.php`: the command-map round trip, and the
  grant/revoke asymmetry against a real, temporary privacy group.
- `Handlers/Cart.test.php`: as two real local channels, an access product from
  creation to revocation; a subscription through renew, cron expiry, revival
  and the seller's extend/revoke; tax, a required buyer option, the summary,
  and the cart purge. It skips rather than touch a real open cart, and removes
  everything it creates.

One trap found while writing these: `q()` always runs `vsprintf`, even with no
arguments, so a `%` in an interpolated value is a fatal. `subRows()` doubles
them; anything else building a WHERE fragment by interpolation must too.
