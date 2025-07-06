<nav class="app-header navbar navbar-expand  bg-body-tertiary border-0 sticky-top"> <!--begin::Container-->
  <div class="container-fluid"> <!--begin::Start Navbar Links-->
    <ul class="navbar-nav">
      <li class="nav-item">
        <a class="nav-link" data-lte-toggle="sidebar" href="#" role="button"><i class="bi bi-layout-sidebar"></i></a>
      </li>
    </ul>
    <ul class="navbar-nav">
      {{if $userinfo}}
      {{if $sel.name}}
      {{if $sitelocation}}
      <div class="lh-1 d-flex flex-column align-items-center">
        <h1 class="h6 mb-2 lh-1">{{$sel.name}}</h1>
        <small>{{$sitelocation}}</small>
      </div>
      {{else}}
      <li class="nav-item">
        <a class="nav-link active" aria-current="page" href="{{$url}}">{{$sel.name}}</a>
      </li>
      {{/if}}
      {{/if}}
      {{/if}}
    </ul>

    <!-- Right navbar links -->
    <ul class="navbar-nav ml-auto align-items-center justify-content-center">
      <!-- App settings icon-->
      {{if $userinfo}}
      {{if $sel.name}}
      {{if $settings_url}}
      <li class="nav-item">
        <a href="{{$settings_url}}/?f=&rpath={{$url}}" class="nav-link"><i class="bi bi-gear"></i></a>
      </li>
      {{/if}}
      {{/if}}
      {{/if}}
<!-- Search Button in Navbar -->
<li class="nav-item" id="nav-search-btn">
    <a class="nav-link" href="#" title="{{$nav.search.3}}" data-bs-toggle="modal" data-bs-target="#searchModal"><i class="bi bi-search generic-icons"></i></a>
</li>

      <!-- user dowpdown menu-->
      {{if $userinfo}}
      <!--begin::User Menu Dropdown-->
      <li class="nav-item dropdown user-menu"> <a href="#" class="d-block link-body-emphasis text-decoration-none dropdown-toggle ps-2" data-bs-toggle="dropdown">
          <img src="{{$userinfo.icon}}" class="rounded-circle shadow img-size-32" alt="User Image"></a>

        <ul class="dropdown-menu dropdown-menu-lg dropdown-menu-end" style="overflow-y: auto; overflow-x:hidden; max-height: 80vh;"> <!--begin::User Image-->
          {{if $is_owner}}
          <!--begin::Menu Body-->
              {{foreach $nav.usermenu as $usermenu}}
          <li><a href="{{$usermenu.0}}" class="dropdown-item">{{$usermenu.1}}</a></li>
              {{/foreach}}
            <li><hr class="dropdown-divider"></li>
              {{if $nav.group}}
          <li><a href="{{$nav.group.0}}" class="dropdown-item">{{$nav.group.1}}</a></li>
            <li><hr class="dropdown-divider"></li>
              {{/if}}
          {{if $nav.manage}}
          <li><a href="{{$nav.manage.0}}" class="dropdown-item">{{$nav.manage.1}}</a></li>
            <li><hr class="dropdown-divider"></li>
          {{/if}}
          {{if $nav.channels}}
              {{foreach $nav.channels as $chan}}
              <li><a href="manage/{{$chan.channel_id}}" class="dropdown-item">
                  <i
                    class="bi bi-circle{{if $localuser == $chan.channel_id}}-fill text-success{{else}} text-disabled{{/if}}"></i>
                  {{$chan.channel_name}}
                </a></li>
              {{/foreach}}
          {{/if}}
            <li><hr class="dropdown-divider"></li>
          {{if $nav.settings}}
          <li><a class="dropdown-item" href="{{$nav.settings.0}}" title="{{$nav.settings.3}}" role="menuitem"
            id="{{$nav.settings.4}}">{{$nav.settings.1}}</a></li>
              {{if $nav.admin}}
          <li><a class="dropdown-item" href="{{$nav.admin.0}}" title="{{$nav.admin.3}}" role="menuitem"
            id="{{$nav.admin.4}}">{{$nav.admin.1}}</a></li>
              {{/if}}
          <li><hr class="dropdown-divider"></li>
          {{/if}}

              {{if $nav.profiles}}
          <li><a href="{{$nav.profiles.0}}" class="dropdown-item">{{$nav.profiles.1}}</a></li>
              {{/if}}
              {{if $nav.logout}}
          <li><a href="{{$nav.logout.0}}" class="dropdown-item">{{$nav.logout.1}}</a></li>
              {{/if}}
          {{/if}}
          {{if ! $is_owner}}
          <!--begin::Menu Footer-->
          <li><a href="{{$nav.rusermenu.0}}" class="dropdown-item">{{$nav.rusermenu.1}}</a></li>
          <li><a href="{{$nav.rusermenu.2}}" class="dropdown-item">{{$nav.rusermenu.3}}</a></li>
          {{/if}}
        </ul>
      </li>
      {{/if}}
      <!--end::User Menu Dropdown-->
      {{if $nav.login && !$userinfo}}
      {{if $nav.loginmenu.1.4}}
      <li class="nav-item ps-2 pe-1">
        <a class="btn btn-info" href="#" title="{{$nav.loginmenu.1.3}}" data-bs-toggle="modal"
          data-bs-target="#nav-login">{{$nav.loginmenu.1.1}}</a>
      </li>
      {{else}}
      <li class="nav-item px-1">
        <a class="btn btn-primary" href="login" title="{{$nav.loginmenu.1.3}}">{{$nav.loginmenu.1.1}}</a>
      </li>
      {{/if}}
      {{if $nav.register}}
      <li class="nav-item px-1">
        <a class="btn btn-success" href="{{$nav.register.0}}" title="{{$nav.register.3}}">{{$nav.register.1}}</a>
      </li>
      {{/if}}
      {{/if}}

      <!-- right sidebar button on smaller screen-->
      <li class="nav-item">
        <a class="nav-link d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#offcanvasResponsive"
          aria-controls="offcanvasResponsive"><i class="bi bi-layout-text-sidebar"></i></a>
      </li>

    </ul> <!--end::End Navbar Links-->
  </div> <!--end::Container-->
</nav>

{{include "sidebar.tpl"}}


