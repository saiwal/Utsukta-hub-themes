# Themes

The Themes section shows all Hubzilla server-side themes installed on your hub. Users can select a theme from their Display Settings — this page shows what is available.

[IMAGE: Themes section listing installed theme names]

## What Themes Control

Hubzilla themes control the **server-side template and layout** of the interface — colours, fonts, and the overall HTML structure of non-SPA pages. The SPA (this interface) has its own colour scheme setting independent of the Hubzilla theme.

## Theme List

Each theme is listed by name. The currently active default theme is indicated.

## Setting a Default Theme

The default theme is applied to new users and visitors who have not chosen a theme. This is configured in the server's configuration file (`config/local.config.php` or equivalent) — not directly from this UI.

## Installing New Themes

Install new themes by placing the theme folder in `extend/theme/` on the server filesystem and then visiting this page to refresh the list.

## User Theme Choice

Users can override the hub default in **Settings → Display → Theme**. Their choice is saved per-account. As admin you cannot force a specific theme on existing users, only change the default for new users.

[IMAGE: Theme list with theme names and selection indicator]
