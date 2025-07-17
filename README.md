# Utsukta Hub themes

A collection of custom themes developed for hubzilla:

## How to install

Run the following in hubzilla folder on your webserver:

```
./util/add_theme_repo https://github.com/saiwal/Utsukta-hub-themes.git utsukta-themes
```

or manually extract the release in `view/themes`

## Themes

### 1. AdminLTE4

A simple, elegant and clean theme based on [AdminLTE](https://adminlte.io/)

- Based on [AdminLTEv4](https://adminlte.io/).
- Built completely from the ground up.
- Mobile friendly and responsive.
- Multiple variants(schemas) that can be chosen for a unique look.
- Report any issues in the [github repo](https://github.com/saiwal/hubzilla-themes).
- Use [Discussions](https://github.com/saiwal/Utsukta-hub-themes/discussions) to discuss ideas, ask questions and for help.

  **Demo available [here](https://hub.utsukta.org/channel/adminlte)**

Some [Screenshots](/adminlte/screenshots/screenshots.md)

#### Variants (Schemas)

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


