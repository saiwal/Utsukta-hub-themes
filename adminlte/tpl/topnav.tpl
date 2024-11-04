<nav class="app-header navbar navbar-expand bg-body border-0 sticky-top"> <!--begin::Container-->
  <div class="container-fluid"> <!--begin::Start Navbar Links-->
    <ul class="navbar-nav">
      <li class="nav-item">
        <a class="nav-link" data-lte-toggle="sidebar" href="#" role="button"><i class="bi bi-layout-sidebar"></i></a>
      </li>
      <li class="nav-item">
        <a class="nav-link d-md-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#offcanvasResponsive" aria-controls="offcanvasResponsive"><i class="bi bi-layout-text-sidebar"></i></a>
      </li>
    </ul>
    <!-- Right navbar links -->
    <ul class="navbar-nav ml-auto">
      <!-- Navbar Search -->
      <li class="nav-item">
        <div class="navbar-search-block">
          <form class="form-inline" method="get" action="{{$nav.search.4}}" role="search">
						<input class="form-control form-control-sm mt-1 me-2" id="nav-search-text" type="text" value="" placeholder="{{$help}}" name="search" title="{{$nav.search.3}}" onclick="this.submit();" onblur="closeMenu('nav-search'); openMenu('nav-search-btn');"/>
					</form>
        </div>
      </li>

      {{if $localuser || $nav.pubs}}
      <li class="nav-item dropdown"> <a class="nav-link show" data-bs-toggle="dropdown" id="notifications-btn" href="#" aria-expanded="true"> <i class="bi bi-bell-fill"></i> <span class="navbar-badge badge text-bg-warning"></span> </a>
          <div class="dropdown-menu dropdown-menu-lg dropdown-menu-end" data-bs-popper="static"> 
              <div class="dropdown-divider"></div> <a href="/notifications" class="dropdown-item dropdown-footer">
                  See All Notifications
              </a>
          </div>
      </li>
			{{/if}}

    {{if $userinfo}}
    <!--begin::User Menu Dropdown-->
    <li class="nav-item dropdown user-menu"> <a href="#" class="nav-link dropdown-toggle" data-bs-toggle="dropdown"> <img src="{{$userinfo.icon}}" class="user-image rounded-circle shadow" alt="User Image"> <span class="d-none d-md-inline">{{$userinfo.name}}</span> </a>
      <ul class="dropdown-menu dropdown-menu-lg dropdown-menu-end"> <!--begin::User Image-->
				{{if $is_owner}}
        <li class="user-header text-bg-secondary"> <img src="{{$userinfo.icon}}" class="bg-dark shadow" alt="User Image">
          <p>
            {{$userinfo.name}}
          </p>
        </li> <!--end::User Image--> <!--begin::Menu Body-->
        <li class="user-body p-0">
          <!--begin::Profile Row-->
          <div class="row">
            {{foreach $nav.usermenu as $usermenu}}
            <div class="col-6"><a href="{{$usermenu.0}}" class="dropdown-item">{{$usermenu.1}}</a> </div>
            {{/foreach}}
            {{if $nav.group}}
            <div class="col-6"><a href="{{$nav.group.0}}" class="dropdown-item">{{$nav.group.1}}</a>
            </div>
            {{/if}}
          </div> <!--end::Row-->
        </li>
        {{if $nav.manage}}
        <li class="user-body p-0">
          <!--begin::Channels Row-->
          <div class="row">
            <div class="col-6"><a href="{{$nav.manage.0}}" class="dropdown-item">{{$nav.manage.1}}</a>
            </div>
          </div> <!--end::Row-->
        </li>
        {{/if}}
        {{if $nav.channels}}
        <li class="user-body p-0">
          <!--begin::Channel list Row-->
          <div class="row">
            {{foreach $nav.channels as $chan}}
            <div class="col-12"><a href="manage/{{$chan.channel_id}}" class="dropdown-item">
              <i class="bi bi-circle{{if $localuser == $chan.channel_id}}-fill text-success{{else}} text-disabled{{/if}}"></i> {{$chan.channel_name}}
            </a></div>
            {{/foreach}}
        </li>
        {{/if}}
        {{if $nav.settings}}
        <li class="user-body p-0">
        <div class="row">
          <div class="col-6">
   			    <a class="dropdown-item" href="{{$nav.settings.0}}" title="{{$nav.settings.3}}" role="menuitem" id="{{$nav.settings.4}}">{{$nav.settings.1}}</a>
          </div>
          {{if $nav.admin}}
  	  			<div class="col-6">
			    		<a class="dropdown-item" href="{{$nav.admin.0}}" title="{{$nav.admin.3}}" role="menuitem" id="{{$nav.admin.4}}">{{$nav.admin.1}}</a>
            </div>
					{{/if}}
          </div>
        </li>
				{{/if}}
        <!--end::Menu Body-->
        <!--begin::Menu Footer-->
        <li class="user-footer"> 
          <div class="row">
          {{if $nav.profiles}}
            <div class="col-6">
              <a href="{{$nav.profiles.0}}" class="dropdown-item">{{$nav.profiles.1}}</a> 
            </div>
          {{/if}}
          {{if $nav.logout}}
            <div class="col-6">
            <a href="{{$nav.logout.0}}" class="btn btn-default btn-flat">{{$nav.logout.1}}</a>
            </div>
          {{/if}}
          </div> <!--end::Row-->
        </li> <!--end::Menu Footer-->
        {{/if}}
				{{if ! $is_owner}}
        <li class="user-header text-bg-secondary"> <img src="{{$userinfo.icon}}" class="bg-dark shadow" alt="User Image">
          <p>
            {{$userinfo.name}}
          </p>
        </li> <!--end::User Image--> <!--begin::Menu Body-->
        <!--begin::Menu Footer-->
        <li class="user-footer"> 
          <a href="{{$nav.rusermenu.0}}" class="btn btn-default btn-flat">{{$nav.rusermenu.1}}</a> 
          <a href="{{$nav.rusermenu.2}}" class="btn btn-default btn-flat float-end">{{$nav.rusermenu.3}}</a>
        </li> <!--end::Menu Footer-->
        {{/if}}
      </ul>
    </li>
    {{/if}}
    <!--end::User Menu Dropdown-->
    {{if $nav.login && !$userinfo}}
      {{if $nav.loginmenu.1.4}}
      <li class="nav-item mt-1 ps-2 pe-1">
        <a class="btn btn-info btn-sm" href="#" title="{{$nav.loginmenu.1.3}}" data-bs-toggle="modal" data-bs-target="#nav-login">{{$nav.loginmenu.1.1}}</a>
      </li>
      {{else}}
      <li class="nav-item mt-1 px-1">
        <a class="btn btn-primary btn-sm" href="login" title="{{$nav.loginmenu.1.3}}">{{$nav.loginmenu.1.1}}</a>
      </li>
      {{/if}}
      {{if $nav.register}}
      <li class="nav-item mt-1 px-1">
        <a class="btn btn-success btn-sm" href="{{$nav.register.0}}" title="{{$nav.register.3}}">{{$nav.register.1}}</a>
      </li>
      {{/if}}
    {{/if}}
    </ul> <!--end::End Navbar Links-->
  </div> <!--end::Container-->
</nav>


