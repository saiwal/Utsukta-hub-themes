# Security

The Security section controls access restrictions, content filtering, and HTTP security headers.

[IMAGE: Security settings form]

## Access Controls

### Block Public Access
When enabled, **all personal pages require authentication**. Anonymous visitors see a login prompt instead of public channel content. Useful for private or corporate hubs.

### Cloud Root Directory
When enabled (default), the cloud file system lists channels that have public files at the root URL. Disable to prevent directory enumeration of channels with files.

### Show Total Disk Space in Cloud
Toggles whether the total disk usage is shown to users in the cloud interface.

---

## Email Domain Filtering

Use these to control who can register based on their email address.

| Field | Description |
|-------|-------------|
| **Allowed email domains** | Comma-separated list. **Only** these email domains may register. Leave blank to allow all. |
| **Blocked email domains** | Comma-separated list. These domains are **always denied** registration, even if allowed domains is blank. |

**Example — allow only a company domain:**
```
Allowed: mycompany.com
```

**Example — block known spam domains:**
```
Blocked: mailinator.com, trashmail.com
```

---

## Federation Controls

### Allowed Sites (Whitelist)
One URL per line. If this list is non-empty, **only** the listed sites can federate with your hub. All other incoming federation requests are rejected.

Leave blank to allow federation with any site (subject to the blocklist).

### Blocked Sites (Blocklist)
One URL per line. These sites are **prevented from federating** with your hub. Useful for blocking individual problem servers.

> **Important:** Whitelist and blocklist interact. If you set a whitelist, the blocklist is redundant (everything else is already blocked). Use one or the other, not both.

---

## Embedded Content

### Embed SSL Only
When enabled, only HTTPS (SSL) URLs are permitted in embedded content (iframes, images, etc.). Prevents mixed-content warnings and HTTP downgrade risks.

---

## HTTP Security Headers

| Header | Description |
|--------|-------------|
| **Transport Security Header** | Sends `Strict-Transport-Security` (HSTS). Tells browsers to always use HTTPS. **Only enable if your hub is fully HTTPS.** |
| **Content Security Policy** | Sends a `Content-Security-Policy` header. Reduces XSS risk by restricting which scripts and resources can load. May break some embeds — test before enabling on production. |

> **Recommendation:** Enable both headers for public hubs running on HTTPS. Do not enable HSTS on HTTP-only installations — it will lock users out.

## Saving

Click **Save** to apply all security changes.
