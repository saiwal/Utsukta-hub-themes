 <!-- Navbar -->
  <nav class="main-header navbar navbar-expand navbar-dark">
    <!-- Left navbar links -->
    <ul class="navbar-nav">
      <li class="nav-item">
        <a class="nav-link" data-widget="pushmenu" href="" role="button"><i class="bi bi-layout-sidebar-inset"></i></a>
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
                  <i class="bi bi-x"></i>
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
<a class="btn btn-primary" data-bs-toggle="offcanvas" href="#offcanvasExample" role="button" aria-controls="offcanvasExample">
  Link with href
</a>
<button class="btn btn-primary" type="button" data-bs-toggle="offcanvas" data-bs-target="#offcanvasExample" aria-controls="offcanvasExample">
  Button with data-bs-target
</button>

<div class="offcanvas offcanvas-start" tabindex="-1" id="offcanvasExample" aria-labelledby="offcanvasExampleLabel">
  <div class="offcanvas-header">
    <h5 class="offcanvas-title" id="offcanvasExampleLabel">Offcanvas</h5>
    <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
  </div>
  <div class="offcanvas-body">
    <div>
      Some text as placeholder. In real life you can have the elements you have chosen. Like, text, images, lists, etc.
    </div>
    <div class="dropdown mt-3">
      <button class="btn btn-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
        Dropdown button
      </button>
      <ul class="dropdown-menu">
        <li><a class="dropdown-item" href="#">Action</a></li>
        <li><a class="dropdown-item" href="#">Another action</a></li>
        <li><a class="dropdown-item" href="#">Something else here</a></li>
      </ul>
    </div>
  </div>
</div>

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

<ScrollWheelUp>

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
    <div class="sidebar os-host os-theme-light os-host-overflow os-host-overflow-y os-host-resize-disabled os-host-scrollbar-horizontal-hidden os-host-transition">
      <!-- Sidebar Menu -->
      <div class="os-resize-observer-host observed">
        <div class="os-resize-observer" style="left: 0px; right: auto;"></div>
      </div>
      <div class="os-size-auto-observer observed" style="height: calc(100% + 1px); float: left;">
        <div class="os-resize-observer"></div>
      </div>
      <div class="os-padding">
        <div class="os-viewport os-viewport-native-scrollbars-invisible os-viewport-native-scrollbars-overlaid" style="overflow-y: scroll;">
          <div class="os-content" style="padding: 0px 8px; height: 100%; width: 100%;">
            <div class="form-inline mt-3">
              <div class="input-group" data-widget="sidebar-search">
                <input class="form-control form-control-sidebar" type="search" placeholder="Search" aria-label="Search">
                <div class="input-group-append">
                  <button class="btn btn-sidebar">
                    <i class="bi bi-search fa-fw"></i>
                  </button>
                </div>
              </div>
            <div class="sidebar-search-results">
              <div class="list-group">
                <a href="#" class="list-group-item">
                <div class="search-title">
                  <strong class="text-light"></strong>N<strong class="text-light"></strong>o<strong class="text-light"></strong> <strong class="text-light"></strong>e<strong class="text-light"></strong>l<strong class="text-light"></strong>e<strong class="text-light"></strong>m<strong class="text-light"></strong>e<strong class="text-light"></strong>n<strong class="text-light"></strong>t<strong class="text-light"></strong> <strong class="text-light"></strong>f<strong class="text-light"></strong>o<strong class="text-light"></strong>u<strong class="text-light"></strong>n<strong class="text-light"></strong>d<strong class="text-light"></strong>!<strong class="text-light"></strong>
                </div>
                <div class="search-path"></div>
                </a>
              </div>
            </div>
          </div>

          <nav class="mt-2">
            <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu" data-accordion="false">
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
          </nav>
        </div>
      </div>
      <div class="os-scrollbar os-scrollbar-horizontal os-scrollbar-unusable os-scrollbar-auto-hidden">
        <div class="os-scrollbar-track">
          <div class="os-scrollbar-handle" style="width: 100%; transform: translate(0px);"></div>
        </div>
      </div>
      <div class="os-scrollbar os-scrollbar-vertical os-scrollbar-auto-hidden">
        <div class="os-scrollbar-track"><div class="os-scrollbar-handle" style="height: 48.05%; transform: translate(0px);"></div>
      </div>
    </div>
      <!-- /.sidebar-menu -->
    <!-- /.sidebar -->
  </aside>

