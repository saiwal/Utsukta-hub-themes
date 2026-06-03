### AdminLTE

A simple, elegant and clean theme based on [AdminLTE](https://adminlte.io/)

- Based on [AdminLTEv4](https://adminlte.io/).
- Built completely from the ground up.
- Mobile friendly and responsive.
- Multiple variants (schemas) that can be chosen for a unique look.
- Customize background color and image, dark/light mode, mini sidebar.
- Report any issues in the [github repo](https://github.com/saiwal/hubzilla-themes).
- Use [Discussions](https://github.com/saiwal/Utsukta-hub-themes/discussions) to discuss ideas, ask questions and for help.

**Demo available [here](https://utsukta.org/page/utsukta-themes/adminlte-default)**

Some [Screenshots](/adminlte/screenshots/screenshots.md)

#### Variants (Schemes)

This theme comes with 9 variants that can be chosen from `Settings → Display Settings` in the scheme section.

- [Brite](https://utsukta.org/page/utsukta-themes/adminlte-brite)
- [Cosmo](https://utsukta.org/page/utsukta-themes/adminlte-cosmo/)
- [Journal](https://utsukta.org/page/utsukta-themes/adminlte-journal/)
- [Morph](https://utsukta.org/page/utsukta-themes/adminlte-morph)
- [Sandstone](https://utsukta.org/page/utsukta-themes/adminlte-sandstone/)
- [Sketchy](https://utsukta.org/page/utsukta-themes/adminlte-sketchy/)
- [Solar](https://utsukta.org/page/utsukta-themes/adminlte-solar/)
- [Superhero](https://utsukta.org/page/utsukta-themes/adminlte-superhero/)
- [Vapor](https://utsukta.org/page/utsukta-themes/adminlte-vapor/)

#### User Guide

Select the theme and a scheme from `Settings → Display Settings`.

![Settings panel](README/settings.png)

The theme offers additional customisation options:

- **Background**: Choose a background color and/or a background image. For pattern images choose *Tiled* mode; for photographs choose *Cover* mode.
- **Sidebar**: Set the default sidebar state to *Expanded* or *Collapsed* (mini). A collapsed sidebar shows only icons; hovering expands it.
  ![Expanded sidebar](README/logo-expand.png) ![Collapsed sidebar](README/logo-collapsed.png)
- **Dark / Light mode**: Choose your preferred color mode as the default. This can also be toggled at any time from the top navbar.
- **Logo**: A custom logo image URL can be entered in the logo field; it appears at the top of the sidebar.
  ![Logo field](README/logo_field.png)
- **Notifications**: Notifications appear in a dropdown in the top navbar with per-type collapsible panels, filtering, and infinite-scroll loading.
  ![Notification dropdown](README/notification-dropdown.png)

#### Admin Guide

Site administrators can set system-wide defaults for all users from `Admin → Themes → AdminLTE`.

![Admin settings](README/admin-settings.png)

The admin panel lets you configure:

- **Default scheme** — the color variant applied to users who have not chosen one themselves.
- **Default dark/light mode** — site-wide color mode default.
- **Default sidebar mode** — expanded or collapsed by default.
- **Default background color and image** — applied when the user has no personal override set.

These values are stored in Hubzilla's system config under the `theme_adminlte` family. Per-user settings (set in `Settings → Display Settings`) always override the system defaults.

**Installation**: Place the theme directory at `extend/theme/utsukta-themes/adminlte/` inside the Hubzilla installation, then activate it from `Admin → Themes`. The theme registers its hooks and module route automatically on activation.

#### Development Notes

**No build step** — there is no npm, SCSS compilation, or bundler. All CSS and JS changes are live immediately on the next page reload.

**CSS tokens** — `css/style.css` contains placeholder tokens (`$bgcolor`, `$background_image`, `$background_image_dark`, `$bg_mode`, `$logo`) that are substituted at runtime by `php/style.php`, which then appends the active schema CSS from `schema/`.

**Vendored assets** — `css/adminlte.min.css`, `css/adminlte.css`, and `js/adminlte.min.js` are the AdminLTE v4 framework files. `js/overlayscrollbar.min.js` provides the custom sidebar scrollbar. Do not edit these directly.

**Key PHP files**:

| File | Role |
|---|---|
| `php/theme.php` | Theme metadata; `adminlte_init()` entry point |
| `php/theme_init.php` | Enqueues CSS/JS per request; reads per-user pconfig for color/sidebar mode |
| `php/style.php` | Dynamically serves the stylesheet; merges user → system → hardcoded fallback values |
| `php/config.php` | Per-user settings form and site-admin panel |
| `php/default.php` | Two-column layout (content + aside) |
| `php/doubleleft.php` | Two-column variant |
| `php/full.php` | Full-width layout |

**Hooks**: Registered in `adminlte_theme_admin_enable()` / unregistered in `adminlte_theme_admin_disable()` inside `php/config.php`.

| Hook | File | Purpose |
|---|---|---|
| `nav` | `hooks/layout.php` | Injects notification config array into `$nav['ntd']` |
| `page_end` | `hooks/tours.php` | Loads Shepherd.js tour assets when a matching `tours/steps/<page>.<lang>.json` exists |

**Tours**: `tours/steps/` contains Shepherd.js step definitions (currently `hq.en.json`, `hq.de.json`, `hq.hu.json`). Tour completion is recorded to pconfig via the `/adminlte?tour=<page>` endpoint (`mod/Mod_adminlte.php`).

**Templates**: `tpl/*.tpl` override core Hubzilla templates of the same name. Notable overrides: `topnav.tpl`, `sidebar.tpl`, `notifications_widget_topnav.tpl`, `hdr.tpl`.

**Configuration storage**:
- Per-user: `pconfig($uid, 'adminlte', $key)`
- System-wide: `Config::Get/Set('theme_adminlte', $key)`
