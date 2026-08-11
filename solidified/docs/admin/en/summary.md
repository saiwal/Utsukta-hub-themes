# Summary

The Summary section gives a snapshot of the health and size of your Hubzilla installation.

[IMAGE: Summary section with stat grids and version info]

## Account Stats

A 2×2 grid showing:

| Stat | Meaning |
|------|---------|
| **Total** | All registered accounts |
| **Blocked** | Accounts that have been manually blocked |
| **Expired** | Accounts past their expiry date (if expiry is configured) |
| **Expiring soon** | Accounts within the configured `abandon_days` window |

**Action:** If blocked or expiring-soon numbers are unexpectedly high, review the **Accounts** section and your registration/expiry settings under **Site**.

## Key Counts

| Stat | Meaning |
|------|---------|
| **Channels** | Total channels on this hub |
| **Pending registrations** | Accounts awaiting admin approval |
| **Queued messages** | Outbound messages waiting to be delivered |

> A non-zero **Queued messages** count is normal during high activity. A persistently large queue (hundreds or thousands, not decreasing) means federation delivery is broken. Check **Logs** for delivery errors and verify the queue worker is running.

[IMAGE: Key counts row showing Channels, Pending, Queue]

## Version

The installed Hubzilla version string. Compare with the official changelog to know if your installation is up to date.

[IMAGE: Version string displayed]

## Active Plugins

A list of all currently enabled plugins as tag badges. This is a quick inventory — detailed plugin management is in **Addons**.

[IMAGE: Plugin badge list]
