A simple, elegant and clean theme based on [AdminLTE](https://adminlte.io/)

- Based on [AdminLTEv4](https://adminlte.io/).
- Built completely from the ground up.
- Mobile friendly and responsive.
- Multiple variants(schemas) that can be chosen for a unique look.
- Report any issues in the [github repo](https://github.com/saiwal/hubzilla-themes).
- Use [Discussions](https://github.com/saiwal/Utsukta-hub-themes/discussions) to discuss ideas, ask questions and for help.

  **Demo available [here](https://utsukta.org/page/utsukta-themes/adminlte-default)**

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

**Setting default system options:** You can set default options for the theme(scheme, dark mode, background color, images etc.) for your hub in /admin/site and clicking on `change theme settings`
![set system pref](README/%20admin-settings.png)

and you will see the various theme settings that can be set fo your hub.

![settings ui](README/%20settings.png)
**Notification Dropdown:** It may happen that the notification dropdown does not display by default. This is because AdminLTE uses a custom navbar that needs to be specified in the `pdl` file if you have previously used the pdleditor and customised your layouts. To do this simply go to the pdleditor and `reset` the layout, or to preserve your previous edits, add the following to the `source`:

```
[region=topnav]
[widget=mynavbar][/widget]
[/region]
```


![Notification dropdown menu](README/%20notification-dropdown.png)

**Personalization options:** AdminLTE offers some ways to further customize the look and feel of your theme by specifying color and background image for your pages. This can be specified by going to `Settings`->`Display Settings`-> `Custom Theme Settings`. 

**Custom Logo:** To have a customized logo on the top left corner, upload the logo with public access and provide the url in `/admin/site` under `theme settings`


![logo](README/%20logo_field.png)

![logo-collapsed](README/%20logo-collapsed.png)

![logo-expand](README/%20logo-expand.png)

**Sortable navigation menu:** The items on the navigation menu can be dragged in the order you wish for them to appear.

**Interface tour(via addon):** Install the addon `adminlte_tour` via the [addon repo](https://github.com/saiwal/utsukta-hub-addons) to give new members a quick tour of the interface.
