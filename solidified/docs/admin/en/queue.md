# Message Queue

The Queue section shows **undelivered outbound messages** — federation activity that your hub has tried to send but has not yet succeeded in delivering to remote servers.

[IMAGE: Queue section with total count and message table]

## What the Queue Is

When your hub posts content or follows/unfollows a remote channel, Hubzilla sends federation messages to remote servers. If a remote server is temporarily down or unreachable, the message is held in the queue and retried later.

A **healthy queue** is small and shrinking. Messages come and go quickly.
An **unhealthy queue** grows steadily or never empties.

## Queue Table

| Column | Description |
|--------|-------------|
| **Destination** | The remote server URL that the message is addressed to |
| **Updated** | When the last delivery attempt was made |
| **Priority** | Message priority (lower number = higher priority) |

[IMAGE: Queue table rows with destination URLs and timestamps]

## Interpreting the Queue

**Empty queue** → All messages delivered. Federation is healthy.

**Small queue (< ~20)** → Normal. A few messages are always in transit.

**Large or growing queue** → Problem. Common causes:
- A specific remote server is down — check if most entries share one destination
- The **queue worker** (cron job) is not running — see [queue-worker](#queue-worker-health)
- Network / firewall issue preventing outbound connections from your server

## Queue Worker Health

The queue is processed by a background PHP cron job. If the cron is not running, messages accumulate indefinitely.

**To verify the cron is running:**
- Check your server's cron configuration (`crontab -l` or `/etc/cron.d/`)
- Look for the Hubzilla cron entry (typically runs every few minutes)
- Check the **Queueworker** admin section (see [queueworker.txt](queueworker.txt))

## Clearing Stuck Messages

If specific destinations have been failing for days and you are sure the remote server is permanently offline, you may want to remove those entries from the queue. This is done via the server database or Hubzilla's own cleanup tools — not directly from this UI view.

## Refresh

Click **Refresh** to reload the current queue state.

[IMAGE: Refresh button on queue page]
