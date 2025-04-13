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

      <!-- Navbar Search -->
      <li class="nav-item">
        <a class="nav-link d-md-none" href="/search"><i class="bi bi-search"></i></a>
        <div class="navbar-search-block d-none d-md-block">
          <form class="form-inline" method="get" action="{{$nav.search.4}}" role="search">
            <input class="form-control me-sm-2" id="nav-search-text" type="text" value=""
              placeholder="{{$help}}" name="search" title="{{$nav.search.3}}" onclick="this.submit();"
              onblur="closeMenu('nav-search'); openMenu('nav-search-btn');" />
          </form>
        </div>
      </li>
      <!-- notificattion button for smaller screens-->
      {{if $localuser || $nav.pubs}}
      <li class="nav-item dropdown">
        <a class="nav-link" data-bs-toggle="dropdown" href="#" aria-expanded="false">
          <i class="bi bi-bell-fill"></i>
        </a>
        <div class="dropdown-menu dropdown-menu-lg dropdown-menu-end">
          <span class="dropdown-item dropdown-header">15 Notifications</span>
          <div class="dropdown-divider"></div>
          <a href="#" class="dropdown-item">
            <i class="bi bi-envelope me-2"></i> 4 new messages
            <span class="float-right text-secondary fs-7">3 mins</span>
          </a>
          <div class="dropdown-divider"></div>
          <a href="#" class="dropdown-item">
            <i class="bi bi-people-fill me-2"></i> 8 friend requests
            <span class="float-right text-secondary fs-7">12 hours</span>
          </a>
          <div class="dropdown-divider"></div>
          <a href="#" class="dropdown-item">
            <i class="bi bi-file-earmark-fill me-2"></i> 3 new reports
            <span class="float-right text-secondary fs-7">2 days</span>
          </a>
          <div class="dropdown-divider"></div>
          <a href="#" class="dropdown-item dropdown-footer"> See All Notifications </a>
        </div>
      </li>
      {{/if}}

      {{if $localuser || $nav.pubs}}
      <li id="notifications-btn" class="nav-item dropdown"> <a class="nav-link" data-bs-toggle="dropdown" href="#" aria-expanded="false" data-bs-auto-close="outside"> <i id="notifications-btn-icon" class="bi bi-bell"></i> <span class="navbar-badge badge text-bg-warning"></span> </a>
          <div class="dropdown-menu dropdown-menu-lg dropdown-menu-end" data-bs-popper="static"> 
        {{if !$sys_only}}
        <div id="notifications_wrapper" class="ms-3 me-3 small">
          <div class="border-top-0 border-start-0 border-end-0 border-bottom-0 list-group list-group-flush">
            <p id="no_notifications" class="list-group-item">{{$no_notifications}} </p>
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
          <div id="notifications" class="navbar-nav row mb-0">
            {{foreach $notifications as $notification}}
            <div class="border border-start-0 border-end-0 border-bottom-0 list-group list-group-flush collapse {{$notification.type}}-button">
              <a id="notification-link-{{$notification.type}}" class="collapsed list-group-item justify-content-between align-items-center d-flex fakelink stretched-link notification-link" href="#" title="{{$notification.title}}" data-bs-target="#nav-{{$notification.type}}-sub" data-bs-toggle="collapse" data-sse_type="{{$notification.type}}">
                <div>
                  <i class="bi bi-{{$notification.icon}} generic-icons-nav"></i>
                  {{$notification.label}}
                </div>
                <span class="badge bg-{{$notification.severity}} {{$notification.type}}-update"></span>
              </a>
            </div>
            <div id="nav-{{$notification.type}}-sub" class="pe-0 rounded-bottom border border-start-0 border-end-0 border-bottom-0 list-group list-group-flush collapse notification-content" data-bs-parent="#notifications" data-sse_type="{{$notification.type}}">
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
      </li>
			{{/if}}

      <!-- user dowpdown menu-->
      {{if $userinfo}}
      <!--begin::User Menu Dropdown-->
      <li class="nav-item dropdown user-menu"> <a href="#" class="d-block link-body-emphasis text-decoration-none dropdown-toggle ps-2" data-bs-toggle="dropdown">
          <img src="{{$userinfo.icon}}" class="rounded-circle shadow img-size-32" alt="User Image"></a>

        <ul class="dropdown-menu dropdown-menu-lg dropdown-menu-end" style="overflow-y: auto; overflow-x:hidden; max-height: 80vh;"> <!--begin::User Image-->
          {{if $is_owner}}
          <!--begin::Menu Body-->
          <li class="user-body p-0">
            <!--begin::Profile Row-->
            <div class="row">
              {{foreach $nav.usermenu as $usermenu}}
              <div class="col-12"><a href="{{$usermenu.0}}" class="dropdown-item">{{$usermenu.1}}</a> </div>
              {{/foreach}}
              {{if $nav.group}}
              <div class="col-12"><a href="{{$nav.group.0}}" class="dropdown-item">{{$nav.group.1}}</a>
              </div>
              {{/if}}
            </div> <!--end::Row-->
          </li>
          {{if $nav.manage}}
          <li class="user-body p-0">
            <!--begin::Channels Row-->
            <div class="row">
              <div class="col-12"><a href="{{$nav.manage.0}}" class="dropdown-item">{{$nav.manage.1}}</a>
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
                  <i
                    class="bi bi-circle{{if $localuser == $chan.channel_id}}-fill text-success{{else}} text-disabled{{/if}}"></i>
                  {{$chan.channel_name}}
                </a></div>
              {{/foreach}}
          </li>
          {{/if}}
          {{if $nav.settings}}
          <li class="user-body p-0">
            <div class="row">
              <div class="col-12">
                <a class="dropdown-item" href="{{$nav.settings.0}}" title="{{$nav.settings.3}}" role="menuitem"
                  id="{{$nav.settings.4}}">{{$nav.settings.1}}</a>
              </div>
              {{if $nav.admin}}
              <div class="col-12">
                <a class="dropdown-item" href="{{$nav.admin.0}}" title="{{$nav.admin.3}}" role="menuitem"
                  id="{{$nav.admin.4}}">{{$nav.admin.1}}</a>
              </div>
              {{/if}}
            </div>
          </li>
          {{/if}}
          <!--end::Menu Body-->
          <!--begin::Menu Footer-->
          <li class="user-body p-0">
            <div class="row">
              {{if $nav.profiles}}
              <div class="col-12">
                <a href="{{$nav.profiles.0}}" class="dropdown-item">{{$nav.profiles.1}}</a>
              </div>
              {{/if}}
              {{if $nav.logout}}
              <div class="col-12">
                <a href="{{$nav.logout.0}}" class="dropdown-item">{{$nav.logout.1}}</a>
              </div>
              {{/if}}
            </div> <!--end::Row-->
          </li> <!--end::Menu Footer-->
          {{/if}}
          {{if ! $is_owner}}
          <!--begin::Menu Footer-->
          <li class="user-footer">
            <div class="col-12">
              <a href="{{$nav.rusermenu.0}}" class="dropdown-item">{{$nav.rusermenu.1}}</a>
            </div>
            <div class="col-12">
              <a href="{{$nav.rusermenu.2}}" class="dropdown-item">{{$nav.rusermenu.3}}</a>
            </div>
          </li> <!--end::Menu Footer-->
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


