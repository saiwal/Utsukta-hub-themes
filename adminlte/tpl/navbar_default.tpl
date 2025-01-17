<nav class="app-header navbar navbar-expand bg-body border-0 sticky-top"> <!--begin::Container-->
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
        <h1 class="h6 mb-2 text-white lh-1">{{$sel.name}}</h1>
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
    <ul class="navbar-nav ml-auto">
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
      <li class="nav-item d-none d-md-block">
        <div class="navbar-search-block">
          <form class="form-inline" method="get" action="{{$nav.search.4}}" role="search">
            <input class="form-control form-control-sm mt-1 me-2" id="nav-search-text" type="text" value=""
              placeholder="{{$help}}" name="search" title="{{$nav.search.3}}" onclick="this.submit();"
              onblur="closeMenu('nav-search'); openMenu('nav-search-btn');" />
          </form>
        </div>
      </li>

      <!-- notificattion button for smaller screens-->
      {{if $localuser || $nav.pubs}}
      <li id="notifications-btn" class="nav-item d-xl-none">
        <a class="nav-link notifications-btn" href="#"><i id="notifications-btn-icon"
            class="bi bi-exclamation-circle notifications-btn-icon generic-icons"></i></a>
      </li>
      {{/if}}

      <!-- user dowpdown menu-->
      {{if $userinfo}}
      <!--begin::User Menu Dropdown-->
      <li class="nav-item dropdown user-menu"> <a href="#" class="nav-link dropdown-toggle" data-bs-toggle="dropdown">
          <img src="{{$userinfo.icon}}" class="user-image rounded-circle shadow" alt="User Image"></a>
        <ul class="dropdown-menu dropdown-menu-lg dropdown-menu-end"> <!--begin::User Image-->
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
          <li class="user-footer">
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
      <li class="nav-item mt-1 ps-2 pe-1">
        <a class="btn btn-info btn-sm" href="#" title="{{$nav.loginmenu.1.3}}" data-bs-toggle="modal"
          data-bs-target="#nav-login">{{$nav.loginmenu.1.1}}</a>
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

      <!-- right sidebar button on smaller screen-->
      <li class="nav-item">
        <a class="nav-link d-md-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#offcanvasResponsive"
          aria-controls="offcanvasResponsive"><i class="bi bi-layout-text-sidebar"></i></a>
      </li>

    </ul> <!--end::End Navbar Links-->
  </div> <!--end::Container-->
</nav>


<!--begin::Sidebar-->
<aside class="app-sidebar bg-dark shadow" data-bs-theme="dark">
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
      <ul class="nav sidebar-menu flex-column" data-lte-toggle="treeview" role="menu" data-accordion="false">
        <li class="nav-header">
          <div id="bd-theme" class="btn-group" role="group" aria-label="Basic radio toggle button group">
            <input type="radio" class="btn-check" name="btnradio" id="btnradio1" autocomplete="off" checked
              data-bs-theme-value="auto">
            <label class="btn btn-sm btn-outline-primary" for="btnradio1"><i
                class="bi bi-circle-half me-2"></i>Auto</label>

            <input type="radio" class="btn-check" name="btnradio" id="btnradio2" autocomplete="off"
              data-bs-theme-value="dark">
            <label class="btn btn-sm btn-outline-primary" for="btnradio2"><i
                class="bi bi-moon-fill me-2"></i>Dark</label>

            <input type="radio" class="btn-check" name="btnradio" id="btnradio3" autocomplete="off"
              data-bs-theme-value="light">
            <label class="btn btn-sm btn-outline-primary" for="btnradio3"><i
                class="bi bi-sun-fill me-2"></i>Light</label>
          </div>
        </li>
        <li class="nav-header d-block d-md-none">
          <div class="navbar-search-block">
            <form class="form-inline" method="get" action="{{$nav.search.4}}" role="search">
              <input class="form-control form-control-sm mt-1 me-2" id="nav-search-text" type="text" value=""
                placeholder="{{$help}}" name="search" title="{{$nav.search.3}}" onclick="this.submit();"
                onblur="closeMenu('nav-search'); openMenu('nav-search-btn');" />
            </form>
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
          <ul id="app-bin-container" data-token="{{$form_security_token}}" class="nav nav-treeview" style="display: none; box-sizing: border-box;">
            {{foreach $nav_apps as $nav_app}}
            {{$nav_app}}
            {{/foreach}}
          </ul>
        </li>
        <li class="nav-header"><a class="nav-link" href="/apps"><i class="bi bi-plus-lg"></i>
            <p>{{$addapps}}</p>
          </a></li>
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

{{if $is_owner}}
<script>
	var nav_app_bin_container = document.getElementById('app-bin-container');
	new Sortable(nav_app_bin_container, {
		animation: 150,
		delay: 200,
		delayOnTouchOnly: true,
		onEnd: function (e) {
			let nav_app_str = '';
			$('#app-bin-container a').each(function () {
				if(app_str.length) {
					app_str = app_str.concat(',', $(this).text());
				}
				else {
					app_str = app_str.concat($(this).text());
				}
			});
			$.post(
				'pconfig',
				{
					'aj' : 1,
					'cat' : 'system',
					'k' : 'app_order',
					'v' : app_str,
					'form_security_token' : $('#app-bin-container').data('token')
				}
			);

		}
	});

	var papp, app_icon, app_url;
	$(document).on('dragstart', function (e) {
		papp = e.target.dataset.papp || null;
		app_icon = e.target.dataset.icon || null;
		app_url = e.target.dataset.url || null;
		app_name = e.target.dataset.name || null;
	});

</script>
{{/if}}
