# Cart

The Cart app turns a channel into a small shop. Visitors browse a catalog, fill a cart and pay; the channel owner manages products, orders and subscribers from the same page. Besides ordinary goods, a product can grant **access** (membership of one of your privacy groups, or a connection) and can be a **subscription** that lasts for a paid period.

The shop lives at `/cart/<channel>`. The tabs across the top are **Catalog**, **Cart**, **My orders** (when you're logged in on someone else's shop) and **My Shop** (on your own).

## Buying

1. Open **Catalog** and press **Add to cart**. You need to be logged in, either on this hub or as a visitor from your own hub.
2. Open **Cart**. Change quantities, or remove items. If the seller asks a question about an item (a name to engrave, a size), the field appears under that item; fields marked `*` must be filled before you can pay.
3. Pay with one of the methods the seller offers (PayPal, Razorpay, Cashfree, UPI), or place the order and arrange payment with the seller directly.

The cart shows the subtotal, any tax and the total that will be charged. A cart you leave untouched for 30 days is emptied.

### Products that grant access

A product marked **Includes member access** adds you to something on the seller's channel (for example a group that can see members-only posts). Access is granted automatically as soon as your payment is confirmed. You can only have one of these in the cart at a time, since access is something you either have or don't.

If you pay the seller directly rather than through a payment provider, access starts when the seller marks your order as paid.

### Subscriptions

A price shown as "per month" or "every 3 months" is a subscription. One payment buys one period.

- To continue, buy the same product again. Renewing **before** it runs out adds the new period to the end of the current one, so you lose nothing by renewing early.
- When the paid period ends and you haven't renewed, the access it gave is removed. Buying it again later starts a fresh period.
- Renewal is never automatic: nothing is charged unless you place an order.

### My orders

**My orders** lists your subscriptions at this shop (with the date each runs until and a **Renew** button) and every order you have placed, with whether it is paid and whether each item has been fulfilled or its access granted.

## Selling

Open your own `/cart/<you>` and choose **My Shop**. The three icons at the top right open **Subscribers**, **Manage catalog** and **Payment settings**; with none of them open you see your sales figures and orders.

### Products

**Manage catalog → Add item** opens the product form:

| Field | What it does |
|---|---|
| Description | The product's name in the catalog. |
| Price, Tax % | The price, and a tax percentage added on top of it at checkout. |
| Photo | A URL, an upload, or a picture from your albums. |
| Billing | Leave off for a one-time sale. Turn **Recurring** on and set "every N days / weeks / months / years" for a subscription. |
| Ask the buyer | Questions the buyer answers in the cart. Mark one **Long answer** for a multi-line field, **Required** to block checkout until it's filled. |
| What the buyer gets | Privacy groups to add the buyer to, and whether to add them as a connection. |
| Active | Untick to take it off sale without deleting it. |

Privacy-group membership lets a buyer see restricted content when they visit your channel. For private posts to be **delivered** to a buyer on another hub, they also need to be a connection, so tick "Add the buyer as a connection" on products where that matters. Buyers are never shown which group a product adds them to, only that it includes access.

### Orders

Each order shows the buyer, the total, how it was paid, and its items with the buyer's answers to your questions.

- **Mark paid** records a payment you received outside the app. Orders paid through a payment provider are marked automatically.
- When an order becomes paid, access products and subscriptions are fulfilled immediately. Physical items wait for you: press **Fulfil** when you've delivered them.
- **Cancel** on a fulfilled item undoes it; on an access product the button reads **Revoke access**. Only what that purchase added is taken away: a buyer who was already in the group, or already a connection, before buying stays that way.
- If access couldn't be granted (for example the privacy group was deleted since), the item is flagged with the reason. Fix the cause and press **Fulfil** to try again.
- Notes you add to an order are for your own records.

### Subscribers

**Subscribers** lists everyone who has subscribed, with the date their period runs until, or when it ended.

- **Extend** gives one more period for free (and restores access if it had lapsed).
- **Revoke access** ends a subscription now.

Cancelling the order item that paid for a subscription ends it too.

### Sales figures

Above the order list: revenue from paid orders (one figure per currency), the number of orders, how many are unpaid, how many paid items still await fulfilment, and your live subscribers.

### Payment settings

Choose the shop's currency and enable the payment methods you use, each with the keys from that provider's dashboard. "Contact seller" is always available: the buyer places the order and you arrange payment yourselves.

Razorpay charges in the currency set in its own section, not the shop currency.

## Widgets

- **Shopping Cart**: for a buyer, a summary of the cart with a link to check out; for the owner, counts of unpaid and unfulfilled orders.
- **My Subscriptions**: your subscriptions at the shop of the channel you're viewing, with **Renew**. Hidden when you have none there.
- **Shop Subscribers** (owner only): how many live subscribers you have and whose period ends soonest.
- **Shop Item Card**: an opt-in showcase card for one catalog item with a quick **add to cart**. Add it from the widget picker, then use its gear icon to pick the item (by SKU); add several copies to feature more than one product.

## Good to know

- The Cart app needs the hub's `cart` addon enabled. Products, orders and settings are shared with the classic cart pages, with two exceptions: a subscription made here appears there as an ordinary one-time item, and subscriptions made in the classic pages are not listed here.
- Subscription expiry is checked by the hub's scheduled background job, so access ends shortly after the period does rather than to the second.
