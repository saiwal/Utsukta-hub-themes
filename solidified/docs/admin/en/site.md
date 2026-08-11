# Site Configuration

The Site section controls your hub's identity and registration behaviour.

[IMAGE: Site settings form]

## Identity Fields

| Field | Description |
|-------|-------------|
| **Site name** | The name of your hub, displayed in the header and metadata |
| **Site location** | The canonical URL of your hub (e.g. `https://myhub.example.com`) |
| **Banner text** | A short tagline or welcome message shown on public pages |
| **Admin info** | Contact information for the administrator, shown in site info |
| **Site info** | A longer description of your hub shown on the public info page |

## Registration Policy

Controls who can create a new account:

| Value | Behaviour |
|-------|-----------|
| **No (closed)** | Registration is disabled. Nobody can create new accounts. |
| **Yes – with approval** | Anyone can apply, but an admin must approve each registration. Pending registrations appear in **Summary**. |
| **Yes (open)** | Anyone can register immediately without approval. |

**Max daily registrations** — Set a limit on how many new accounts can be created per day. `0` means unlimited.

> **Recommendation for new hubs:** Start with "with approval" until you are confident in your spam controls, then switch to "open" if needed.

## Access Policy

Describes the commercial nature of your hub — shown on public site information pages:

| Value | Meaning |
|-------|---------|
| **Not a public server** | Private or closed hub |
| **Paid access only** | All users pay |
| **Free access only** | All users are free |
| **Free + optional paid upgrades** | Free tier with optional paid features |

## Other Settings

| Setting | Description |
|---------|-------------|
| **Abandon days** | Accounts inactive for this many days are considered abandoned (0 = never) |
| **Max image size** | Maximum upload size for images in bytes |
| **Login on homepage** | Show the login form on the public homepage |
| **Disable discover tab** | Hide the network discovery/explore tab from users |
| **Site firehose** | Include this hub in the network-wide public firehose |
| **Open public stream** | Allow anonymous visitors to view the public stream |

## Saving

Click **Save** at the top right of the section. Changes take effect immediately.
