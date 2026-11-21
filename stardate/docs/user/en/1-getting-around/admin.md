# Site Administration

The admin area (`/admin`) is only visible to hub administrators. Its sections
are listed on the left. Most forms have a **Save** button. Changes affect
everyone on the hub, so it's worth reading a setting's hint before you change it.

<!-- summary -->
## Summary

An overview of the hub: account totals (**Total**, **Blocked**, **Expired**,
**Expiring soon**), **Channels**, **Pending registrations**, **Queued
messages** waiting to be delivered, the Hubzilla **Version**, and the
**Active plugins**. A growing queue or pile of pending registrations usually
needs looking at.

<!-- site -->
## Site

Hub-wide configuration, in groups:

- **Identity**: **Site name**, location, banner/logo, the **Admin info** and **Site info** text shown on the site info page, the system language and the **Default theme**.
- **Registration**: the **Registration policy** (open, approval, closed), daily and per-IP limits, minimum age, email verification, invite codes, and whether a channel is created automatically. **Default permission role for new accounts** decides what kind of channel newcomers start with.
- **Visitors and homepage**: what logged-out visitors see. This covers the login box, the visitor homepage, the public stream (**firehose**) and whether visitors can open it without logging in, and the community/discover pages.
- **Directory**: **Force-publish all profiles to site directory** and the **Directory server** to use.
- **Email**: the sender name and addresses for system emails.
- **Performance and delivery**: network timeout, delivery interval, deliveries per process, poll interval and **Maximum load average** (background jobs pause above it), plus image size limits and how long imported content is kept.
- **Network**: SSL verification, an outbound proxy, and friend-domain or post filters.

<!-- accounts -->
## Accounts

Every registered account, with its email, channels, creation date, service
class and expiry.

- **Approve** accepts a pending registration. Rows marked **Unverified** haven't confirmed their email yet.
- **Block** / **Unblock** stops or restores logins for that account.
- **Edit** changes the account's **Service class**, sets a **Trial expires** date (after that date the account drops to the default class), or resets its password. **Generate** creates a strong password.
- **Delete** removes the account and all its channels.

<!-- service-classes -->
## Service Classes

Service classes are presets of resource limits, such as how many channels,
how much storage and how many connections an account may have. 0 means
unlimited.

- **Create Service Class**, then fill in its limits. Names may use letters, digits, `_` and `-`.
- **Set as default** gives that class to new accounts.
- Assign a class to an account from **Accounts → Edit**.

<!-- channels -->
## Channels

Every channel on the hub. **Filter by name or address…** finds one.

- **Censor** / **Uncensor** hides or restores a channel's content on this hub.
- **Allow code** / **Disallow code** lets a channel post executable content, like PHP or JavaScript in webpages. Only give this to people you trust completely.
- **Delete** removes the channel.

<!-- security -->
## Security

- **Block public access**: visitors must log in to see anything.
- **Allowed sites** / **Blocked sites**: a whitelist or blacklist for federation. If the allowed list has entries, only those hubs may connect.
- **Allowed** / **Blocked channels** and **email domains**: who may connect, and who may register.
- **Allowed** / **Blocked embed domains**, **Only allow HTTPS embeds**: which sites' media can be embedded in posts.
- **Trusted directory servers**: directory servers this hub trusts.
- **Send Content Security Policy header** and **Send Transport Security header (HSTS)**: browser security headers.
- **Provide cloud root directory** and **Show total disk space in cloud**: what the shared `/cloud` root shows.

<!-- features -->
## Features

Optional features that channels can turn on in **Settings → Features**. For
each one you can change its default, or lock it (**Locked by admin**) so
every channel gets the same setting.

<!-- addons -->
## Addons

The plugins in the hub's addon directory. Badges show whether each one is
**Installed** and **Active**. Click an addon to install or remove it, and to
open its **Settings** if it has any.

<!-- themes -->
## Themes

The themes in the hub's theme directory. Badges mark the **Default**, ones
meant for **Mobile**, **Experimental** ones, and any that are **Incompatible**
with this Hubzilla version. Some themes have their own **Settings**.

<!-- inspect-queue -->
## Inspect Queue

Outgoing messages that haven't been delivered yet, grouped by destination
hub. A few are normal. A large or growing pile for one hub means it's down or
refusing deliveries.

For each hub you can **Retry delivery to this hub**, **Empty queue for this
hub** (throw its messages away), or **Mark hub permanently offline** to stop
trying. **Expert mode** shows each message. **Refresh** reloads the list.

<!-- queueworker -->
## Queueworker

The background workers that deliver posts and run scheduled jobs.

- **Queue items**: what's waiting, broken down by command.
- **Max worker threads**: how many workers may run at once. More means faster delivery, but a busier server.
- **Pause before next task**: the gap between jobs. **Auto-adjust pause** tunes it automatically.
- **Assume workers dead after**: how long until a stuck worker's job is handed to another.

<!-- profile-fields -->
## Profile Fields

Which fields appear on profile edit forms. Move fields from **All available
fields** into **Basic Profile Fields** (always shown) or **Advanced Profile
Fields** (shown on the full form).

**Custom Fields** adds your own field, with a **Field nickname** (lowercase,
like `my_custom_field`), a visible **Field Name**, **Help text** and an
**Input type**. A field with a set of options also needs its **Choices**.

<!-- db-updates -->
## DB Updates

The history of database structure updates, with each one's result. After
upgrading Hubzilla, check here that every update succeeded.

<!-- logs -->
## Logs

A live view of the hub's log, newest first.

- **Pause** / **Resume** stops the live view so you can read. A counter shows how many entries arrived meanwhile.
- **Search messages…**, the level and file filters, or click a request or function name to show only its entries. **Clear filters** resets them.
- **Copy** an entry, or **Download** the whole log.
- **Settings**: turn **Debugging** on or off, and choose the **Log level** and **Log file**. Nothing is logged while debugging is off. High log levels make the file grow fast, so turn them down once you're done.
