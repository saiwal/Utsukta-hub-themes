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

- [Brite](https://bootswatch.com/brite)
- [Cosmo](https://bootswatch.com/cosmo/)
- [Journal](https://bootswatch.com/journal/)
- [Sandstone](https://bootswatch.com/sandstone/)
- [Sketchy](https://bootswatch.com/sketchy/)
- [Solar](https://bootswatch.com/solar/)
- [Superhero](https://bootswatch.com/superhero/)
- [Vapor](https://bootswatch.com/vapor/)

#### Features and Setup

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
