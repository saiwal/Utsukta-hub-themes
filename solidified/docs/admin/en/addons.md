# Addons (Plugins)

The Addons section lets you install, enable, disable, and remove plugins that extend Hubzilla's functionality.

[IMAGE: Addons section with installed and available plugin lists]

## Installed Plugins

The top part of the page lists **currently installed plugins**. Each shows:
- Plugin name
- Version
- Description
- **Disable** or **Uninstall** button

An enabled plugin is active and its features are available to all users. A disabled plugin is installed but not running.

[IMAGE: Installed plugin card with name, version, description, and action buttons]

## Available Plugins

Below the installed list, available-but-not-installed plugins are shown. Click **Install** to add a plugin.

## Installing a Plugin

1. Find the plugin in the Available list (or upload a plugin archive if manual install is supported).
2. Click **Install**.
3. The plugin is activated immediately.

[IMAGE: Available plugin list with Install button]

## Disabling a Plugin

Click **Disable** on an installed plugin. The plugin stops running but its data and configuration remain. Re-enable it at any time by clicking **Enable**.

**Use disable when:** temporarily testing whether a plugin causes a problem, or rolling out a change without permanent uninstall.

## Uninstalling a Plugin

Click **Uninstall** to completely remove a plugin. Its code is removed. Some plugins also remove their data tables on uninstall.

> ⚠ Uninstalling may delete plugin-specific user data. Check the plugin's documentation before uninstalling on a live hub.

## Viewing Active Plugins

The list of currently active plugins is also shown on the **Summary** page as a badge list — useful for a quick inventory without opening this section.
