<!-- site header
        ================================================== -->
<header class="s-header header">

	<div class="header__top">
		<div class="header__logo">
			<a class="site-logo" href="/">
				{{$banner}}
			</a>
		</div>
	</div>

	<nav class="header__nav-wrap">

		<ul class="header__nav">
			<!-- Pinned user apps -->
			{{if $navbar_apps.0}}
			{{foreach $navbar_apps as $navbar_app}}
			{{$navbar_app}}
			{{/foreach}}
			{{/if}}
			<!-- Channel apps; needs fixing -->
			{{if $channel_apps.0}}
			{{foreach $channel_apps as $channel_app}}
			{{$channel_app}}
			{{/foreach}}
			{{/if}}
			{{if $is_owner}}
			<li class="has-children"><a href="" class="">{{$featured_apps}}</a>
				<ul class="sub-menu">
					{{foreach $nav_apps as $nav_app}}
					{{$nav_app}}
					{{/foreach}}
				</ul>
			</li>

			</li>
			<li><a href="/apps"><i class="bi bi-plus-lg"></i>{{$addapps}}</a>
			</li>


			{{else}}
			<!-- System apps   -->
			<li class="has-children"><a href="" class="">{{$sysapps}}</a>
				<ul class="sub-menu">
					{{foreach $nav_apps as $nav_app}}
					{{$nav_app}}
					{{/foreach}}
				</ul>
			</li>
			{{/if}}
      {{if $userinfo}}
			<li class="has-children"><a href="" class="" id="user-toggle"><i class="bi bi-person-lines-fill"></i></a>
				<ul class="sub-menu"> <!--begin::User Image-->
					{{if $is_owner}}
					<!--begin::Menu Body-->
					{{foreach $nav.usermenu as $usermenu}}
					<li><a href="{{$usermenu.0}}">{{$usermenu.1}}</a></li>
					{{/foreach}}
					{{if $nav.group}}
					<li><a href="{{$nav.group.0}}">{{$nav.group.1}}</a></li>
					{{/if}}
					{{if $nav.manage}}
					<li><a href="{{$nav.manage.0}}">{{$nav.manage.1}}</a></li>
					{{/if}}
					{{if $nav.channels}}
					{{foreach $nav.channels as $chan}}
					<li><a href="manage/{{$chan.channel_id}}">
							<i
								class="bi bi-circle{{if $localuser == $chan.channel_id}}-fill text-success{{else}} text-disabled{{/if}}"></i>
							{{$chan.channel_name}}
						</a></li>
					{{/foreach}}
					{{/if}}
					{{if $nav.settings}}
					<li><a href="{{$nav.settings.0}}" title="{{$nav.settings.3}}" role="menuitem"
							id="{{$nav.settings.4}}">{{$nav.settings.1}}</a></li>
					{{if $nav.admin}}
					<li><a href="{{$nav.admin.0}}" title="{{$nav.admin.3}}" role="menuitem"
							id="{{$nav.admin.4}}">{{$nav.admin.1}}</a></li>
					{{/if}}
					{{/if}}

					{{if $nav.profiles}}
					<li><a href="{{$nav.profiles.0}}">{{$nav.profiles.1}}</a></li>
					{{/if}}
					{{if $nav.logout}}
					<li><a href="{{$nav.logout.0}}">{{$nav.logout.1}}</a></li>
					{{/if}}
					{{/if}}
					{{if ! $is_owner}}
					<!--begin::Menu Footer-->
					<li><a href="{{$nav.rusermenu.0}}">{{$nav.rusermenu.1}}</a></li>
					<li><a href="{{$nav.rusermenu.2}}">{{$nav.rusermenu.3}}</a></li>
					{{/if}}
				</ul>
			</li>
			{{/if}}

					{{if $nav.login && !$userinfo}}
					{{if $nav.loginmenu.1.4}}
					<li>
						<a href="#" title="{{$nav.loginmenu.1.3}}" data-bs-toggle="modal"
							data-bs-target="#nav-login">{{$nav.loginmenu.1.1}}</a>
					</li>
					{{else}}
					<li>
						<a href="login" title="{{$nav.loginmenu.1.3}}">{{$nav.loginmenu.1.1}}</a>
					</li>
					{{/if}}
					{{if $nav.register}}
					<li>
						<a href="{{$nav.register.0}}" title="{{$nav.register.3}}">{{$nav.register.1}}</a>
					</li>
					{{/if}}
					{{/if}}

		</ul> <!-- end header__nav -->
		<ul class="header__social">
			<li class="ss-link">
				<a href="/siteinfo" class="nav-link">
		      <i class="bi bi-info-circle"></i> 
					<span class="screen-reader-text">Siteinfo</span>
				</a>
			</li>
			{{if $userinfo}}
      {{if $sel.name}}
      {{if $settings_url}}
      <li class="ss-link">
        <a href="{{$settings_url}}/?f=&rpath={{$url}}" class="nav-link"><i class="bi bi-gear"></i></a>
      </li>
      {{/if}}
      {{/if}}
      {{/if}}


		</ul>

	</nav> <!-- end header__nav-wrap -->

	<!-- menu toggle -->
	<a href="" class="header__menu-toggle">
		<span>Menu</span>
	</a>

</header> <!-- end s-header -->

<!-- search
        ================================================== -->
<div class="s-search">

	<div class="search-block">

		<form role="search" method="get" class="search-form" action="{{$nav.search.4}}">
			<label>
				<span class="hide-content">Search for:</span>
				<input type="search" class="search-field" id="nav-search-text" placeholder="{{$nav.search.3}}" value=""
					name="search" title="{{$nav.search.3}}" autocomplete="off">
			</label>
			<input type="submit" class="search-submit" value="Search">
		</form>

		<a title="Close Search" class="search-close">Close</a>

	</div> <!-- end search-block -->

	<!-- search modal trigger -->
	<a href="" class="search-trigger">
		<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
			style="fill:rgba(0, 0, 0, 1);transform:;-ms-filter:">
			<path
				d="M10,18c1.846,0,3.543-0.635,4.897-1.688l4.396,4.396l1.414-1.414l-4.396-4.396C17.365,13.543,18,11.846,18,10 c0-4.411-3.589-8-8-8s-8,3.589-8,8S5.589,18,10,18z M10,4c3.309,0,6,2.691,6,6s-2.691,6-6,6s-6-2.691-6-6S6.691,4,10,4z">
			</path>
		</svg>
		<span>Search</span>
	</a>
	<span class="search-line"></span>

</div> <!-- end s-search -->
