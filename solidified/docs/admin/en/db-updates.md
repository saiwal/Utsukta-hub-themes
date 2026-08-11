# Database Updates

The DB Updates section shows any **pending database schema changes** — migrations that need to be applied to bring your database up to date with the installed Hubzilla version.

[IMAGE: DB Updates section showing pending update list or "up to date" message]

## When This Matters

After upgrading Hubzilla (via git pull or a manual update), new schema changes may be waiting to run. If updates are pending:

- Some features may not work correctly
- You may see errors in the Logs section related to missing columns or tables

## Running Updates

If updates are listed, click **Run updates** (or the equivalent action button). Updates run sequentially. The page refreshes to confirm completion.

[IMAGE: Run updates button with pending update descriptions]

## Up to Date

If no updates are pending, the section shows a message like "Database is up to date." This is the normal state.

[IMAGE: "Up to date" confirmation message]

## After an Upgrade

**Recommended post-upgrade checklist:**
1. Visit this section — run any pending updates.
2. Visit **Logs** — check for new errors.
3. Visit **Summary** — confirm counts look correct.
4. Test a login as a regular user.

> **Tip:** Run database updates immediately after every Hubzilla upgrade, before announcing the update to your users.
