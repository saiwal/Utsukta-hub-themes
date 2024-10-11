 <!-- Navbar -->
  <nav class="main-header navbar navbar-expand navbar-dark">
    <!-- Left navbar links -->
    <ul class="navbar-nav">
      <li class="nav-item">
        <a class="nav-link" data-widget="pushmenu" href="" role="button"><i class="bi bi-bars"></i></a>
      </li>
    </ul>

    <!-- Right navbar links -->
    <ul class="navbar-nav ml-auto">
      <!-- Navbar Search -->
      <li class="nav-item">
        <a class="nav-link" data-widget="navbar-search" href="#" role="button">
          <i class="bi bi-search"></i>
        </a>
        <div class="navbar-search-block">
          <form class="form-inline">
            <div class="input-group input-group-sm">
              <input class="form-control form-control-navbar" type="search" placeholder="Search" aria-label="Search">
              <div class="input-group-append">
                <button class="btn btn-navbar" type="submit">
                  <i class="bi bi-search"></i>
                </button>
                <button class="btn btn-navbar" type="button" data-widget="navbar-search">
                  <i class="bi bi-times"></i>
                </button>
              </div>
            </div>
          </form>
        </div>
      </li>
      {{if $userinfo}}

      <li class="nav-item dropdown">
        <a class="nav-link dropdown-toggle text-primary-emphasis" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">{{$userinfo.name}}</a>
        <ul class="dropdown-menu dropdown-menu-right">
            {{if $is_owner}}
            {{foreach $nav.usermenu as $usermenu}}
            <li><a href="{{$usermenu.0}}" class="dropdown-item">{{$usermenu.1}}</a></li>
            {{/foreach}}

            {{if $nav.group}}
            <a href="{{$nav.group.0}}" class="dropdown-item">{{$nav.group.1}}</a>
            {{/if}}

            {{if $nav.manage}}
            <li> <a href="{{$nav.manage.0}}" class="dropdown-item">
            {{$nav.manage.1}}
            </a></li>
            <li><hr class="dropdown-divider"></li>
            {{/if}}

            {{if $nav.channels}}
            {{foreach $nav.channels as $chan}}
            <li><a href="manage/{{$chan.channel_id}}" class="dropdown-item">
              <i class="bi bi-circle{{if $localuser == $chan.channel_id}} text-success{{else}} invisible{{/if}}"></i> {{$chan.channel_name}}
            </a></li>
            {{/foreach}}
            <li><hr class="dropdown-divider"></li>
            {{/if}}

            {{if $nav.profiles}}
            <li><a href="{{$nav.profiles.0}}" class="dropdown-item">
            {{$nav.profiles.1}}
            </a></li>
            <li><hr class="dropdown-divider"></li>
            {{/if}}

            {{if $settings_url}}
            <li><a id="nav-app-settings-link" href="{{$settings_url}}/?f=&rpath={{$url}}" class="dropdown-item">
            {{if $sel.name}}{{$sel.name}} {{/if}} <i class="bi bi-cog"></i>
            {{/if}}

            {{if $nav.settings}}
            <li><a href="{{$nav.settings.0}}" class="dropdown-item">
            {{$nav.settings.1}}
            </a></li>
            <li><hr class="dropdown-divider"></li>
            {{/if}}

            {{if $nav.admin}}
            <li><a href="{{$nav.admin.0}}" class="dropdown-item">
            {{$nav.admin.1}}
            </a></li>
            <li><hr class="dropdown-divider"></li>
            {{/if}}

            {{if $nav.logout}}
            <li><a href="{{$nav.logout.0}}" class="dropdown-item">
            {{$nav.logout.1}}
            </a></li>
            {{/if}}

            <li>
            {{/if}}
            {{if ! $is_owner}}
            <li><a class="dropdown-item" href="{{$nav.rusermenu.0}}" role="menuitem">{{$nav.rusermenu.1}}</a></li>
            <li><a class="dropdown-item" href="{{$nav.rusermenu.2}}" role="menuitem">{{$nav.rusermenu.3}}</a></li>
            {{/if}}
            </li>

        </ul>
      </li>
      {{/if}}
      <li class="nav-item">
        <a class="nav-link" data-widget="control-sidebar" data-slide="true" href="#" role="button">
          <i class="bi bi-three-dots-vertical"></i>
        </a>
      </li>      
    </ul>
    {{if $nav.login && !$userinfo}}
      <div class="hstack gap-1 pt-1 pb-1 pr-2">
      {{if $nav.loginmenu.1.4}}
        <a class="btn btn-info btn-sm" href="#" title="{{$nav.loginmenu.1.3}}" data-bs-toggle="modal" data-bs-target="#nav-login">{{$nav.loginmenu.1.1}}</a>
      {{else}}
        <a class="btn btn-primary btn-sm" href="login" title="{{$nav.loginmenu.1.3}}">{{$nav.loginmenu.1.1}}</a>
      {{/if}}
      {{if $nav.register}}
        <a class="btn btn-warning btn-sm" href="{{$nav.register.0}}" title="{{$nav.register.3}}">{{$nav.register.1}}</a>
      {{/if}}
      </div>
    {{/if}}

  </nav>
  <!-- /.navbar -->

<!-- Control Sidebar -->
  <aside class="control-sidebar control-sidebar-dark">
    <!-- Control sidebar content goes here -->
    <div class="p-3 control-sidebar-content" style="">
      <h5>Customize AdminLTE</h5>
      <hr class="mb-2">
        <ul class="nav flex-column">
        {{if $navbar_apps.0}}
            <a class="nav-header" aria-disabled="true">{{$pinned_apps}}</a>
        {{foreach $navbar_apps as $navbar_app}}
            {{$navbar_app|replace:'fa':'generic-icons-nav fa'}}
        {{/foreach}}
        {{/if}}
        {{if $channel_apps.0}}
              <a class="nav-header" aria-disabled="true">{{$channelapps}}</a>
          {{foreach $channel_apps as $channel_app}}
              {{$channel_app}}
          {{/foreach}}
        {{/if}}

        {{if $is_owner}}
          <a class="nav-header" aria-disabled="true">{{$featured_apps}}</a>
            {{foreach $nav_apps as $nav_app}}
              {{$nav_app}}
            {{/foreach}}
          <a class="nav-header" href="/apps"><i class="bi bi-plus"></i> {{$addapps}}</a>
        {{else}}
          <a class="nav-header" aria-disabled="true">{{$sysapps}}</a>
        {{foreach $nav_apps as $nav_app}}
              {{$nav_app}}
        {{/foreach}}
        {{/if}} 

        </ul>      
    </div>
  </aside>
  <!-- /.control-sidebar -->
  <!-- Main Sidebar Container -->
  <aside class="main-sidebar sidebar-dark-primary elevation-0">
    <!-- Brand Logo -->
    <a href="https://hub.utsukta.org" class="brand-link">
      <img src="https://hub.utsukta.org/photo/ec268a46-11ab-427f-a605-a54cb341a637-1.png" alt="Utsukta Hub" class="brand-image img-circle elevation-3" style="opacity: .8">
      <span class="brand-text font-weight-light">Utsukta Hub</span>
    </a>

    <!-- Sidebar -->
    <div class="sidebar">
      <!-- Sidebar Menu -->
<div class="os-resize-observer-host observed"><div class="os-resize-observer" style="left: 0px; right: auto;"></div></div><div class="os-size-auto-observer observed" style="height: calc(100% + 1px); float: left;"><div class="os-resize-observer"></div></div><div class="os-content-glue" style="margin: 0px -8px; width: 249px; height: 652px;"></div><div class="os-padding"><div class="os-viewport os-viewport-native-scrollbars-invisible os-viewport-native-scrollbars-overlaid" style="overflow-y: scroll;"><div class="os-content" style="padding: 0px 8px; height: 100%; width: 100%;">

<div class="user-panel mt-3 pb-3 mb-3 d-flex">
<div class="image">
<img src="dist/img/user2-160x160.jpg" class="img-circle elevation-2" alt="User Image">
</div>
<div class="info">
<a href="#" class="d-block">Alexander Pierce</a>
</div>
</div>

<div class="form-inline">
<div class="input-group" data-widget="sidebar-search">
<input class="form-control form-control-sidebar" type="search" placeholder="Search" aria-label="Search">
<div class="input-group-append">
<button class="btn btn-sidebar">
<i class="fas fa-search fa-fw"></i>
</button>
</div>
</div><div class="sidebar-search-results"><div class="list-group"><a href="#" class="list-group-item"><div class="search-title"><strong class="text-light"></strong>N<strong class="text-light"></strong>o<strong class="text-light"></strong> <strong class="text-light"></strong>e<strong class="text-light"></strong>l<strong class="text-light"></strong>e<strong class="text-light"></strong>m<strong class="text-light"></strong>e<strong class="text-light"></strong>n<strong class="text-light"></strong>t<strong class="text-light"></strong> <strong class="text-light"></strong>f<strong class="text-light"></strong>o<strong class="text-light"></strong>u<strong class="text-light"></strong>n<strong class="text-light"></strong>d<strong class="text-light"></strong>!<strong class="text-light"></strong></div><div class="search-path"></div></a></div></div>
</div>

<nav class="mt-2">
<ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu" data-accordion="false">

<li class="nav-item menu-open">
<a href="#" class="nav-link active">
<i class="nav-icon fas fa-tachometer-alt"></i>
<p>
Dashboard
<i class="right fas fa-angle-left"></i>
</p>
</a>
<ul class="nav nav-treeview">
<li class="nav-item">
<a href="./index.html" class="nav-link active">
<i class="far fa-circle nav-icon"></i>
<p>Dashboard v1</p>
</a>
</li>
<li class="nav-item">
<a href="./index2.html" class="nav-link">
<i class="far fa-circle nav-icon"></i>
<p>Dashboard v2</p>
</a>
</li>
<li class="nav-item">
<a href="./index3.html" class="nav-link">
<i class="far fa-circle nav-icon"></i>
<p>Dashboard v3</p>
</a>
</li>
</ul>
</li>
<li class="nav-item">
<a href="pages/widgets.html" class="nav-link">
<i class="nav-icon fas fa-th"></i>
<p>
Widgets
<span class="right badge badge-danger">New</span>
</p>
</a>
</li>
<li class="nav-item">
<a href="#" class="nav-link">
<i class="nav-icon fas fa-copy"></i>
<p>
Layout Options
<i class="fas fa-angle-left right"></i>
<span class="badge badge-info right">6</span>
</p>
</a>
<ul class="nav nav-treeview">
<li class="nav-item">
<a href="pages/layout/top-nav.html" class="nav-link">
<i class="far fa-circle nav-icon"></i>
<p>Top Navigation</p>
</a>
</li>
<li class="nav-item">
<a href="pages/layout/top-nav-sidebar.html" class="nav-link">
<i class="far fa-circle nav-icon"></i>
<p>Top Navigation + Sidebar</p>
</a>
</li>
<li class="nav-item">
<a href="pages/layout/boxed.html" class="nav-link">
<i class="far fa-circle nav-icon"></i>
<p>Boxed</p>
</a>
</li>
<li class="nav-item">
<a href="pages/layout/fixed-sidebar.html" class="nav-link">
<i class="far fa-circle nav-icon"></i>
<p>Fixed Sidebar</p>
</a>
</li>
<li class="nav-item">
<a href="pages/layout/fixed-sidebar-custom.html" class="nav-link">
<i class="far fa-circle nav-icon"></i>
<p>Fixed Sidebar <small>+ Custom Area</small></p>
</a>
</li>
<li class="nav-item">
<a href="pages/layout/fixed-topnav.html" class="nav-link">
<i class="far fa-circle nav-icon"></i>
<p>Fixed Navbar</p>
</a>
</li>
<li class="nav-item">
<a href="pages/layout/fixed-footer.html" class="nav-link">
<i class="far fa-circle nav-icon"></i>
<p>Fixed Footer</p>
</a>
</li>
<li class="nav-item">
<a href="pages/layout/collapsed-sidebar.html" class="nav-link">
<i class="far fa-circle nav-icon"></i>
<p>Collapsed Sidebar</p>
</a>
</li>
</ul>
</li>
<li class="nav-item">
<a href="#" class="nav-link">
<i class="nav-icon fas fa-chart-pie"></i>
<p>
Charts
<i class="right fas fa-angle-left"></i>
</p>
</a>
<ul class="nav nav-treeview">
<li class="nav-item">
<a href="pages/charts/chartjs.html" class="nav-link">
<i class="far fa-circle nav-icon"></i>
<p>ChartJS</p>
</a>
</li>
<li class="nav-item">
<a href="pages/charts/flot.html" class="nav-link">
<i class="far fa-circle nav-icon"></i>
<p>Flot</p>
</a>
</li>
<li class="nav-item">
<a href="pages/charts/inline.html" class="nav-link">
<i class="far fa-circle nav-icon"></i>
<p>Inline</p>
</a>
</li>
<li class="nav-item">
<a href="pages/charts/uplot.html" class="nav-link">
<i class="far fa-circle nav-icon"></i>
<p>uPlot</p>
</a>
</li>
</ul>
</li>
<li class="nav-item">
<a href="#" class="nav-link">
<i class="nav-icon fas fa-tree"></i>
<p>
UI Elements
<i class="fas fa-angle-left right"></i>
</p>
</a>
<ul class="nav nav-treeview">
<li class="nav-item">
<a href="pages/UI/general.html" class="nav-link">
<i class="far fa-circle nav-icon"></i>
<p>General</p>
</a>
</li>
<li class="nav-item">
<a href="pages/UI/icons.html" class="nav-link">
<i class="far fa-circle nav-icon"></i>
<p>Icons</p>
</a>
</li>
<li class="nav-item">
<a href="pages/UI/buttons.html" class="nav-link">
<i class="far fa-circle nav-icon"></i>
<p>Buttons</p>
</a>
</li>
<li class="nav-item">
<a href="pages/UI/sliders.html" class="nav-link">
<i class="far fa-circle nav-icon"></i>
<p>Sliders</p>
</a>
</li>
<li class="nav-item">
<a href="pages/UI/modals.html" class="nav-link">
<i class="far fa-circle nav-icon"></i>
<p>Modals &amp; Alerts</p>
</a>
</li>
<li class="nav-item">
<a href="pages/UI/navbar.html" class="nav-link">
<i class="far fa-circle nav-icon"></i>
<p>Navbar &amp; Tabs</p>
</a>
</li>
<li class="nav-item">
<a href="pages/UI/timeline.html" class="nav-link">
<i class="far fa-circle nav-icon"></i>
<p>Timeline</p>
</a>
</li>
<li class="nav-item">
<a href="pages/UI/ribbons.html" class="nav-link">
<i class="far fa-circle nav-icon"></i>
<p>Ribbons</p>
</a>
</li>
</ul>
</li>
<li class="nav-item">
<a href="#" class="nav-link">
<i class="nav-icon fas fa-edit"></i>
<p>
Forms
<i class="fas fa-angle-left right"></i>
</p>
</a>
<ul class="nav nav-treeview">
<li class="nav-item">
<a href="pages/forms/general.html" class="nav-link">
<i class="far fa-circle nav-icon"></i>
<p>General Elements</p>
</a>
</li>
<li class="nav-item">
<a href="pages/forms/advanced.html" class="nav-link">
<i class="far fa-circle nav-icon"></i>
<p>Advanced Elements</p>
</a>
</li>
<li class="nav-item">
<a href="pages/forms/editors.html" class="nav-link">
<i class="far fa-circle nav-icon"></i>
<p>Editors</p>
</a>
</li>
<li class="nav-item">
<a href="pages/forms/validation.html" class="nav-link">
<i class="far fa-circle nav-icon"></i>
<p>Validation</p>
</a>
</li>
</ul>
</li>
<li class="nav-item">
<a href="#" class="nav-link">
<i class="nav-icon fas fa-table"></i>
<p>
Tables
<i class="fas fa-angle-left right"></i>
</p>
</a>
<ul class="nav nav-treeview">
<li class="nav-item">
<a href="pages/tables/simple.html" class="nav-link">
<i class="far fa-circle nav-icon"></i>
<p>Simple Tables</p>
</a>
</li>
<li class="nav-item">
<a href="pages/tables/data.html" class="nav-link">
<i class="far fa-circle nav-icon"></i>
<p>DataTables</p>
</a>
</li>
<li class="nav-item">
<a href="pages/tables/jsgrid.html" class="nav-link">
<i class="far fa-circle nav-icon"></i>
<p>jsGrid</p>
</a>
</li>
</ul>
</li>
<li class="nav-header">EXAMPLES</li>
<li class="nav-item">
<a href="pages/calendar.html" class="nav-link">
<i class="nav-icon far fa-calendar-alt"></i>
<p>
Calendar
<span class="badge badge-info right">2</span>
</p>
</a>
</li>
<li class="nav-item">
<a href="pages/gallery.html" class="nav-link">
<i class="nav-icon far fa-image"></i>
<p>
Gallery
</p>
</a>
</li>
<li class="nav-item">
<a href="pages/kanban.html" class="nav-link">
<i class="nav-icon fas fa-columns"></i>
<p>
Kanban Board
</p>
</a>
</li>
<li class="nav-item">
<a href="#" class="nav-link">
<i class="nav-icon far fa-envelope"></i>
<p>
Mailbox
<i class="fas fa-angle-left right"></i>
</p>
</a>
<ul class="nav nav-treeview">
<li class="nav-item">
<a href="pages/mailbox/mailbox.html" class="nav-link">
<i class="far fa-circle nav-icon"></i>
<p>Inbox</p>
</a>
</li>
<li class="nav-item">
<a href="pages/mailbox/compose.html" class="nav-link">
<i class="far fa-circle nav-icon"></i>
<p>Compose</p>
</a>
</li>
<li class="nav-item">
<a href="pages/mailbox/read-mail.html" class="nav-link">
<i class="far fa-circle nav-icon"></i>
<p>Read</p>
</a>
</li>
</ul>
</li>
<li class="nav-item">
<a href="#" class="nav-link">
<i class="nav-icon fas fa-book"></i>
<p>
Pages
<i class="fas fa-angle-left right"></i>
</p>
</a>
<ul class="nav nav-treeview">
<li class="nav-item">
<a href="pages/examples/invoice.html" class="nav-link">
<i class="far fa-circle nav-icon"></i>
<p>Invoice</p>
</a>
</li>
<li class="nav-item">
<a href="pages/examples/profile.html" class="nav-link">
<i class="far fa-circle nav-icon"></i>
<p>Profile</p>
</a>
</li>
<li class="nav-item">
<a href="pages/examples/e-commerce.html" class="nav-link">
<i class="far fa-circle nav-icon"></i>
<p>E-commerce</p>
</a>
</li>
<li class="nav-item">
<a href="pages/examples/projects.html" class="nav-link">
<i class="far fa-circle nav-icon"></i>
<p>Projects</p>
</a>
</li>
<li class="nav-item">
<a href="pages/examples/project-add.html" class="nav-link">
<i class="far fa-circle nav-icon"></i>
<p>Project Add</p>
</a>
</li>
<li class="nav-item">
<a href="pages/examples/project-edit.html" class="nav-link">
<i class="far fa-circle nav-icon"></i>
<p>Project Edit</p>
</a>
</li>
<li class="nav-item">
<a href="pages/examples/project-detail.html" class="nav-link">
<i class="far fa-circle nav-icon"></i>
<p>Project Detail</p>
</a>
</li>
<li class="nav-item">
<a href="pages/examples/contacts.html" class="nav-link">
<i class="far fa-circle nav-icon"></i>
<p>Contacts</p>
</a>
</li>
<li class="nav-item">
<a href="pages/examples/faq.html" class="nav-link">
<i class="far fa-circle nav-icon"></i>
<p>FAQ</p>
</a>
</li>
<li class="nav-item">
<a href="pages/examples/contact-us.html" class="nav-link">
<i class="far fa-circle nav-icon"></i>
<p>Contact us</p>
</a>
</li>
</ul>
</li>
<li class="nav-item">
<a href="#" class="nav-link">
<i class="nav-icon far fa-plus-square"></i>
<p>
Extras
<i class="fas fa-angle-left right"></i>
</p>
</a>
<ul class="nav nav-treeview">
<li class="nav-item">
<a href="#" class="nav-link">
<i class="far fa-circle nav-icon"></i>
<p>
Login &amp; Register v1
<i class="fas fa-angle-left right"></i>
</p>
</a>
<ul class="nav nav-treeview">
<li class="nav-item">
<a href="pages/examples/login.html" class="nav-link">
<i class="far fa-circle nav-icon"></i>
<p>Login v1</p>
</a>
</li>
<li class="nav-item">
<a href="pages/examples/register.html" class="nav-link">
<i class="far fa-circle nav-icon"></i>
<p>Register v1</p>
</a>
</li>
<li class="nav-item">
<a href="pages/examples/forgot-password.html" class="nav-link">
<i class="far fa-circle nav-icon"></i>
<p>Forgot Password v1</p>
</a>
</li>
<li class="nav-item">
<a href="pages/examples/recover-password.html" class="nav-link">
<i class="far fa-circle nav-icon"></i>
<p>Recover Password v1</p>
</a>
</li>
</ul>
</li>
<li class="nav-item">
<a href="#" class="nav-link">
<i class="far fa-circle nav-icon"></i>
<p>
Login &amp; Register v2
<i class="fas fa-angle-left right"></i>
</p>
</a>
<ul class="nav nav-treeview">
<li class="nav-item">
<a href="pages/examples/login-v2.html" class="nav-link">
<i class="far fa-circle nav-icon"></i>
<p>Login v2</p>
</a>
</li>
<li class="nav-item">
<a href="pages/examples/register-v2.html" class="nav-link">
<i class="far fa-circle nav-icon"></i>
<p>Register v2</p>
</a>
</li>
<li class="nav-item">
<a href="pages/examples/forgot-password-v2.html" class="nav-link">
<i class="far fa-circle nav-icon"></i>
<p>Forgot Password v2</p>
</a>
</li>
<li class="nav-item">
<a href="pages/examples/recover-password-v2.html" class="nav-link">
<i class="far fa-circle nav-icon"></i>
<p>Recover Password v2</p>
</a>
</li>
</ul>
</li>
<li class="nav-item">
<a href="pages/examples/lockscreen.html" class="nav-link">
<i class="far fa-circle nav-icon"></i>
<p>Lockscreen</p>
</a>
</li>
<li class="nav-item">
<a href="pages/examples/legacy-user-menu.html" class="nav-link">
<i class="far fa-circle nav-icon"></i>
<p>Legacy User Menu</p>
</a>
</li>
<li class="nav-item">
<a href="pages/examples/language-menu.html" class="nav-link">
<i class="far fa-circle nav-icon"></i>
<p>Language Menu</p>
</a>
</li>
<li class="nav-item">
<a href="pages/examples/404.html" class="nav-link">
<i class="far fa-circle nav-icon"></i>
<p>Error 404</p>
</a>
</li>
<li class="nav-item">
<a href="pages/examples/500.html" class="nav-link">
<i class="far fa-circle nav-icon"></i>
<p>Error 500</p>
</a>
</li>
<li class="nav-item">
<a href="pages/examples/pace.html" class="nav-link">
<i class="far fa-circle nav-icon"></i>
<p>Pace</p>
</a>
</li>
<li class="nav-item">
<a href="pages/examples/blank.html" class="nav-link">
<i class="far fa-circle nav-icon"></i>
<p>Blank Page</p>
</a>
</li>
<li class="nav-item">
<a href="starter.html" class="nav-link">
<i class="far fa-circle nav-icon"></i>
<p>Starter Page</p>
</a>
</li>
</ul>
</li>
<li class="nav-item">
<a href="#" class="nav-link">
<i class="nav-icon fas fa-search"></i>
<p>
Search
<i class="fas fa-angle-left right"></i>
</p>
</a>
<ul class="nav nav-treeview">
<li class="nav-item">
<a href="pages/search/simple.html" class="nav-link">
<i class="far fa-circle nav-icon"></i>
<p>Simple Search</p>
</a>
</li>
<li class="nav-item">
<a href="pages/search/enhanced.html" class="nav-link">
<i class="far fa-circle nav-icon"></i>
<p>Enhanced</p>
</a>
</li>
</ul>
</li>
<li class="nav-header">MISCELLANEOUS</li>
<li class="nav-item">
<a href="iframe.html" class="nav-link">
<i class="nav-icon fas fa-ellipsis-h"></i>
<p>Tabbed IFrame Plugin</p>
</a>
</li>
<li class="nav-item">
<a href="https://adminlte.io/docs/3.1/" class="nav-link">
<i class="nav-icon fas fa-file"></i>
<p>Documentation</p>
</a>
</li>
<li class="nav-header">MULTI LEVEL EXAMPLE</li>
<li class="nav-item">
<a href="#" class="nav-link">
<i class="fas fa-circle nav-icon"></i>
<p>Level 1</p>
</a>
</li>
<li class="nav-item">
<a href="#" class="nav-link">
<i class="nav-icon fas fa-circle"></i>
<p>
Level 1
<i class="right fas fa-angle-left"></i>
</p>
</a>
<ul class="nav nav-treeview">
<li class="nav-item">
<a href="#" class="nav-link">
<i class="far fa-circle nav-icon"></i>
<p>Level 2</p>
</a>
</li>
<li class="nav-item">
<a href="#" class="nav-link">
<i class="far fa-circle nav-icon"></i>
<p>
Level 2
<i class="right fas fa-angle-left"></i>
</p>
</a>
<ul class="nav nav-treeview">
<li class="nav-item">
<a href="#" class="nav-link">
<i class="far fa-dot-circle nav-icon"></i>
<p>Level 3</p>
</a>
</li>
<li class="nav-item">
<a href="#" class="nav-link">
<i class="far fa-dot-circle nav-icon"></i>
<p>Level 3</p>
</a>
</li>
<li class="nav-item">
<a href="#" class="nav-link">
<i class="far fa-dot-circle nav-icon"></i>
<p>Level 3</p>
</a>
</li>
</ul>
</li>
<li class="nav-item">
<a href="#" class="nav-link">
<i class="far fa-circle nav-icon"></i>
<p>Level 2</p>
</a>
</li>
</ul>
</li>
<li class="nav-item">
<a href="#" class="nav-link">
<i class="fas fa-circle nav-icon"></i>
<p>Level 1</p>
</a>
</li>
<li class="nav-header">LABELS</li>
<li class="nav-item">
<a href="#" class="nav-link">
<i class="nav-icon far fa-circle text-danger"></i>
<p class="text">Important</p>
</a>
</li>
<li class="nav-item">
<a href="#" class="nav-link">
<i class="nav-icon far fa-circle text-warning"></i>
<p>Warning</p>
</a>
</li>
<li class="nav-item">
<a href="#" class="nav-link">
<i class="nav-icon far fa-circle text-info"></i>
<p>Informational</p>
</a>
</li>
</ul>
</nav>

</div></div></div><div class="os-scrollbar os-scrollbar-horizontal os-scrollbar-unusable os-scrollbar-auto-hidden"><div class="os-scrollbar-track"><div class="os-scrollbar-handle" style="width: 100%; transform: translate(0px);"></div></div></div><div class="os-scrollbar os-scrollbar-vertical os-scrollbar-auto-hidden"><div class="os-scrollbar-track"><div class="os-scrollbar-handle" style="height: 48.05%; transform: translate(0px);"></div></div></div><div class="os-scrollbar-corner"></div>
      <!-- /.sidebar-menu -->
    </div>
    <!-- /.sidebar -->
  </aside>

