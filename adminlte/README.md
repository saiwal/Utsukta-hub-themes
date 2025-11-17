A simple, elegant and clean theme based on [AdminLTE](https://adminlte.io/)

- Based on [AdminLTEv4](https://adminlte.io/).
- Built completely from the ground up.
- Mobile friendly and responsive.
- Multiple variants(schemas) that can be chosen for a unique look.
- Report any issues in the [github repo](https://github.com/saiwal/hubzilla-themes).
- Use [Discussions](https://github.com/saiwal/Utsukta-hub-themes/discussions) to discuss ideas, ask questions and for help.

  **Demo available [here](https://utsukta.org/channel/utsukta-themes)**

Some [Screenshots](/adminlte/screenshots/screenshots.md)

#### Variants (Schemes)

- [Brite](https://utsukta.org/page/utsukta-themes/adminlte-brite)
- [Cosmo](https://utsukta.org/page/utsukta-themes/adminlte-cosmo/)
- [Journal](https://utsukta.org/page/utsukta-themes/adminlte-journal/)
- [Morph](https://utsukta.org/page/utsukta-themes/adminlte-morph)
- [Sandstone](https://utsukta.org/page/utsukta-themes/adminlte-sandstone/)
- [Sketchy](https://utsukta.org/page/utsukta-themes/adminlte-sketchy/)
- [Solar](https://utsukta.org/page/utsukta-themes/adminlte-solar/)
- [Superhero](https://utsukta.org/page/utsukta-themes/adminlte-superhero/)
- [Vapor](https://utsukta.org/page/utsukta-themes/adminlte-vapor/)

#### Features and Setup

**Setting default system options:** You can set default options for the theme(scheme, dark mode, background color, images etc.) through specifying the values in your `.htconfig` file. Simply include a section as shown below in your `.htconfig` file in your hubzilla root folder:
```
// System-wide settings for theme_adminlte
App::$config['theme_adminlte'] = [
    // Default scheme (default, cosmo, journal, etc.)
    'schema' => 'default',

    // Light / default background color (bootstrap color variable or hex value)
    // 'bgcolor'               => '#ff0000',
    // Dark mode background
    // 'bgcolor_dark'          => '#121212',

    // Background images (url)
    // 'background_image'      => '',
    // 'background_image_dark' => '',
    // Background-size mode — recommended flexible value
    // Accepted: 1 for 'cover', 0 for tiled
    'bg_mode'               => 1,

    // Enable dark mode globally (0/1)
    'dark_mode'             => 1,
    // Sidebar style 0 -> expanded, 1 -> collapsed
    'sidebar_mode'          => 0,
   ];
```

**Notification Dropdown:** It may happen that the notification dropdown does not display by default. This is because AdminLTE uses a custom navbar that needs to be specified in the `pdl` file if you have previously used the pdleditor and customised your layouts. To do this simply go to the pdleditor and `reset` the layout, or to preserve your previous edits, add the following to the `source`:

```
[region=topnav]
[widget=mynavbar][/widget]
[/region]
```


![Notification dropdown menu](README/%20notification-dropdown.png)

**Personalization options:** AdminLTE offers some ways to further customize the look and feel of your theme by specifying color and background image for your pages. This can be specified by going to `Settings`->`Display Settings`-> `Custom Theme Settings`. 

**Custom Logo:** The AdminLTE themes support only display of banner text which disappears when you collapse the sidebar. However with a little tweak of the banner code you can use a logo image to display alongside banner text, and on collapse the logo still displays.
Just upload the logo with public access and replace the url in code below and set it as your site banner

![logo-collapsed](README/%20logo-collapsed.png)

![logo-expand](README/%20logo-expand.png)
```
</span>
<img  src="<link to image>"  alt="U" class="brand-image opacity-75 shadow"/> 
<span class="brand-text fw-light"><your hub name></span>
```

Note: This may break the banner on other themes so only implement if other themes are not being used by others. 

**Sortable navigation menu:** The items on the navigation menu can be dragged in the order you wish for them to appear.

**Interface tour(via addon):** Install the addon `adminlte_tour` via the [addon repo](https://github.com/saiwal/utsukta-hub-addons) to give new members a quick tour of the interface.
