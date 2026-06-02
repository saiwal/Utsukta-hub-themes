# Channels

The Channels section gives an overview of every channel on your hub. This is a read-only listing — use it to audit channel activity.

[IMAGE: Channels table with name, address, created, and last post columns]

## The Channels Table

| Column | Description |
|--------|-------------|
| **Name** | The channel's display name |
| **Address** | The channel's short address (nick@hub) — uniquely identifies it |
| **Created** | When the channel was created |
| **Last post** | The date of the channel's most recent post |

Channels with no recent posts may belong to inactive users. The "Last post" date helps identify dormant channels.

## Pagination

Navigate through pages with **Previous** and **Next** buttons. The count at the top shows total channels.

[IMAGE: Pagination row below channels table]

## Relationship to Accounts

One account can own multiple channels. The **Accounts** section shows how many channels each account has. If an account is blocked, all its channels become inaccessible but are not automatically deleted.

## What You Can Do

This section is currently read-only for listing and auditing. To act on a channel (delete, reassign, etc.) you need access to the Hubzilla server admin interface or database tools.

> **Tip:** Sort by "Last post" to identify channels that have been completely inactive — these may be candidates for cleanup if your hub has an abandonment policy.
