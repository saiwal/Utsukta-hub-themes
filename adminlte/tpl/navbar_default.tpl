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

      <li class="nav-item dropdown">
        <a class="nav-link" data-bs-toggle="dropdown" href="#"> <i class="bi bi-bell-fill"></i> <span class="navbar-badge badge text-bg-info">15</span> </a>
      {{if !$sys_only}}
        <div id="notifications_wrapper" class="mb-4">
          <div id="no_notifications" class="d-xl-none">
            {{$no_notifications}}<span class="jumping-dots"><span class="dot-1">.</span><span class="dot-2">.</span><span class="dot-3">.</span></span>
          </div>
          <div id="nav-notifications-template" rel="template" class="d-none">
            <a class="list-group-item list-group-item-action notification {6}" href="{0}" title="{13}" data-b64mid="{7}" data-notify_id="{8}" data-thread_top="{9}" data-contact_name="{2}" data-contact_addr="{3}" data-when="{5}">
              <img data-src="{1}" loading="lazy" class="rounded float-start me-2 menu-img-2">
              <div class="text-nowrap">
                <div class="d-flex justify-content-between align-items-center lh-sm">
                  <div class="text-truncate pe-1">
                    <strong title="{2} - {3}">{2}</strong>
                  </div>
                  <small class="notifications-autotime opacity-75" title="{5}"></small>
                </div>
                <div class="text-truncate">{4}</div>
              </div>
            </a>
          </div>
          <div id="nav-notifications-forums-template" rel="template" class="d-none">
            <a class="list-group-item list-group-item-action justify-content-between align-items-center d-flex notification notification-forum" href="{0}" title="{4} - {3}" data-b64mid="{7}" data-notify_id="{8}" data-thread_top="{9}" data-contact_name="{2}" data-contact_addr="{3}" data-b64mids='{12}'>
              <div>
                <img class="menu-img-1" data-src="{1}" loading="lazy">
                <span>{2}</span>
              </div>
              <span class="badge bg-secondary">{10}</span>
            </a>
          </div>
          <div id="notifications" class="border border-top-0 rounded navbar-nav collapse">
            {{foreach $notifications as $notification}}
            <div class="rounded-top rounded-bottom border border-start-0 border-end-0 border-bottom-0 list-group list-group-flush collapse {{$notification.type}}-button">
              <a id="notification-link-{{$notification.type}}" class="collapsed list-group-item justify-content-between align-items-center d-flex fakelink stretched-link notification-link" href="#" title="{{$notification.title}}" data-bs-target="#nav-{{$notification.type}}-sub" data-bs-toggle="collapse" data-sse_type="{{$notification.type}}">
                <div>
                  <i class="bi bi-{{$notification.icon}} generic-icons-nav"></i>
                  {{$notification.label}}
                </div>
                <span class="badge bg-{{$notification.severity}} {{$notification.type}}-update"></span>
              </a>
            </div>
            <div id="nav-{{$notification.type}}-sub" class="rounded-bottom border border-start-0 border-end-0 border-bottom-0 list-group list-group-flush collapse notification-content" data-bs-parent="#notifications" data-sse_type="{{$notification.type}}">
              {{if $notification.viewall}}
              <a class="list-group-item list-group-item-action text-decoration-none" id="nav-{{$notification.type}}-see-all" href="{{$notification.viewall.url}}">
                <i class="bi bi-box-arrow-up-right generic-icons-nav"></i> {{$notification.viewall.label}}
              </a>
              {{/if}}
              {{if $notification.markall}}
              <div class="list-group-item list-group-item-action cursor-pointer" id="nav-{{$notification.type}}-mark-all" onclick="markRead('{{$notification.type}}'); return false;">
                <i class="bi bi-check-circle generic-icons-nav"></i> {{$notification.markall.label}}
              </div>
              {{/if}}
              {{if $notification.filter}}
              {{if $notification.filter.posts_label}}
              <div class="list-group-item list-group-item-action cursor-pointer" id="tt-{{$notification.type}}-only">
                <i class="bi bi-funnel generic-icons-nav"></i> {{$notification.filter.posts_label}}
              </div>
              {{/if}}
              {{if $notification.filter.name_label}}
              <div class="list-group-item clearfix notifications-textinput" id="cn-{{$notification.type}}-only">
                <div class="text-muted notifications-textinput-filter"><i class="bi bi-filter"></i></div>
                <input id="cn-{{$notification.type}}-input" type="text" class="notification-filter form-control form-control-sm" placeholder="{{$notification.filter.name_label}}">
                <div id="cn-{{$notification.type}}-input-clear" class="text-muted notifications-textinput-clear d-none"><i class="bi bi-x-lg"></i></div>
              </div>
              {{/if}}
              {{/if}}
              <div id="nav-{{$notification.type}}-menu" class="list-group list-group-flush"></div>
              <div id="nav-{{$notification.type}}-loading" class="list-group-item" style="display: none;">
                {{$loading}}<span class="jumping-dots"><span class="dot-1">.</span><span class="dot-2">.</span><span class="dot-3">.</span></span>
              </div>
            </div>
            {{/foreach}}
          </div>
        </div>
        {{/if}}

        <div class="dropdown-menu dropdown-menu-lg dropdown-menu-end">
          <span class="dropdown-item dropdown-header">15 Notifications</span>
          <div class="dropdown-divider"></div>
          <a href="#" class="dropdown-item"> <i class="bi bi-envelope me-2"></i> 4 new messages
            <span class="float-end text-secondary fs-7">3 mins</span> 
          </a>
          <div class="dropdown-divider"></div>
          <a href="#" class="dropdown-item"> <i class="bi bi-people-fill me-2"></i> 8 friend requests
            <span class="float-end text-secondary fs-7">12 hours</span> 
          </a>
          <div class="dropdown-divider"></div>
          <a href="#" class="dropdown-item"> <i class="bi bi-file-earmark-fill me-2"></i> 3 new reports
            <span class="float-end text-secondary fs-7">2 days</span> 
          </a>
          <div class="dropdown-divider"></div> 
          <a href="#" class="dropdown-item dropdown-footer"> See All Notifications</a>
        </div>
      </li>

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

<!--begin::Sidebar-->
<aside class="app-sidebar bg-body-secondary shadow" data-bs-theme="dark">
  <!--begin::Sidebar Brand-->
  <div class="sidebar-brand">
    <!--begin::Brand Link-->
    <a href="/" class="brand-link">
      <!--begin::Brand Image-->
<!--      <img
        src="./assets/img/AdminLTELogo.png"
        alt="U"
        class="brand-image opacity-75 shadow"
      /> -->
      <!--end::Brand Image-->
      <!--begin::Brand Text-->
      <span class="brand-text fw-light">{{$banner}}</span>
      <!--end::Brand Text-->
    </a>
    <!--end::Brand Link-->
  </div>
  <!--end::Sidebar Brand-->
  <!--begin::Sidebar Wrapper-->
  <div class="sidebar-wrapper">
    <nav class="mt-2">
      <!--begin::Sidebar Menu-->
      <ul
        class="nav sidebar-menu flex-column"
        data-lte-toggle="treeview"
        role="menu"
        data-accordion="false"
      >
      <li class="nav-header">
        <div class="btn-group" role="group" aria-label="Basic radio toggle button group">
          <input type="radio" class="btn-check" name="btnradio" id="btnradio1" autocomplete="off" checked data-bs-theme-value="auto">
          <label class="btn btn-sm btn-outline-secondary" for="btnradio1"><i class="bi bi-circle-half me-2"></i>Auto</label>

          <input type="radio" class="btn-check" name="btnradio" id="btnradio2" autocomplete="off" data-bs-theme-value="dark">
          <label class="btn btn-sm btn-outline-secondary" for="btnradio2"><i class="bi bi-moon-fill me-2"></i>Dark</label>

          <input type="radio" class="btn-check" name="btnradio" id="btnradio3" autocomplete="off" data-bs-theme-value="light">
          <label class="btn btn-sm btn-outline-secondary" for="btnradio3"><i class="bi bi-sun-fill me-2"></i>Light</label>
        </div>        
      </li>

        <!-- Pinned user apps -->
        {{if $navbar_apps.0}}
        <li class="nav-header" aria-disabled="true">{{$pinned_apps}}</li>
        {{foreach $navbar_apps as $navbar_app}}
          {{$navbar_app|replace:'fa':'generic-icons-nav fa'}}
        {{/foreach}}
        {{/if}}

        <!-- Channel apps; needs fixing -->
        {{if $channel_apps.0}}
        <li class="nav-header" aria-disabled="true">{{$channelapps}}</li>
        {{foreach $channel_apps as $channel_app}}
          {{$channel_app}} <br>
        {{/foreach}}
        {{/if}}

        {{if $is_owner}}
        <!-- Starred user apps -->
        <li class="nav-item"> 
        <a href="#" class="nav-link"> <i class="nav-icon bi bi-star"></i>
          <p>{{$featured_apps}}<i class="nav-arrow bi bi-chevron-right"></i></p>
        </a>
        <ul class="nav nav-treeview" style="display: none; box-sizing: border-box;">
        {{foreach $nav_apps as $nav_app}}
          {{$nav_app}}
        {{/foreach}}
        </ul>
        </li>        
        <li class="nav-header"><a class="nav-link" href="/apps"><i class="bi bi-gear"></i><p>{{$addapps}}</p></a></li>
        {{else}}
        <li class="nav-header" aria-disabled="true">{{$sysapps}}</li>
        <!-- System apps -->
        {{foreach $nav_apps as $nav_app}}
          {{$nav_app}}
        {{/foreach}}
        {{/if}} 
      </ul>
      <!--end::Sidebar Menu-->
    </nav>
  </div>
  <!--end::Sidebar Wrapper-->
</aside>
<!--end::Sidebar-->

