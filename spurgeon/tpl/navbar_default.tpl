<div class="s-header__branding">
  <p class="site-title">
    <a href="/" rel="home">{{$banner}}</a>
  </p>
</div>

<div class="row s-header__navigation">

  <nav class="s-header__nav-wrap">

    <h3 class="s-header__nav-heading">Navigate to</h3>

    <ul class="s-header__nav flex-wrap">
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
      <li class="has-children"><a class="">{{$featured_apps}}</a>
        <ul class="sub-menu">
          {{foreach $nav_apps as $nav_app}}
          {{$nav_app}}
          {{/foreach}}
        </ul>
      </li>
			<li><a href="/apps"><i class="bi bi-plus-lg"></i>{{$addapps}}</a>
			</li>

      {{else}}
      <!-- System apps   -->
      <li class="has-children"><a class="">{{$sysapps}}</a>
        <ul class="sub-menu">
          {{foreach $nav_apps as $nav_app}}
          {{$nav_app}}
          {{/foreach}}
        </ul>
      </li>
      {{/if}}
      {{if $userinfo}}
      <li class="has-children"><a id="user-toggle"><i class="bi bi-person-lines-fill"></i></a>
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
          <li><a href="{{$nav.rusermenu.0}}" >{{$nav.rusermenu.1}}</a></li>
          <li><a href="{{$nav.rusermenu.2}}" >{{$nav.rusermenu.3}}</a></li>
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
    </ul> <!-- end s-header__nav -->
  </nav> <!-- end s-header__nav-wrap -->

</div> <!-- end s-header__navigation -->

<div class="s-header__search">

  <div class="s-header__search-inner">
    <div class="row">

      <form role="search" method="get" class="s-header__search-form" action="{{$nav.search.4}}">
        <label>
          <span class="u-screen-reader-text">Search for:</span>
          <input type="search" class="s-header__search-field" id="nav-search-text" placeholder="{{$nav.search.3}}" value="" name="search" title="{{$nav.search.3}}" autocomplete="off">
        </label>
        <input type="submit" class="s-header__search-submit" value="Search">
      </form>

      <a title="Close Search" class="s-header__search-close">Close</a>

    </div> <!-- end row -->
  </div> <!-- s-header__search-inner -->

</div> <!-- end s-header__search -->

<a class="s-header__menu-toggle" href="#0"><span>Menu</span></a>
<a class="s-header__search-trigger" href="#">
  <svg width="24" height="24" fill="none" viewBox="0 0 24 24">
    <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
      d="M19.25 19.25L15.5 15.5M4.75 11C4.75 7.54822 7.54822 4.75 11 4.75C14.4518 4.75 17.25 7.54822 17.25 11C17.25 14.4518 14.4518 17.25 11 17.25C7.54822 17.25 4.75 14.4518 4.75 11Z">
    </path>
  </svg>
</a>


