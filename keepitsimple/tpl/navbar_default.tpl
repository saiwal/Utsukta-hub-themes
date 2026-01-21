<div class="row position-relative">
	<div class="s-header__content column">
		<h1 class="s-header__logotext">
			<a href="/" title="">
				{{$banner}}
			</a>
		</h1>
		<p class="s-header__tagline">
      {{if $userinfo}}
      {{if $sel.name}}
      {{if $sitelocation}}
        <span class="h6">{{$sel.name}} •</span>
        <span>{{$sitelocation}}</span>
      {{else}}
        <a class="h6" aria-current="page" href="{{$url}}">{{$sel.name}}</a>
      {{/if}}

      {{if $settings_url}}
        <a href="{{$settings_url}}/?f=&rpath={{$url}}" class="h6"><i class="bi bi-gear"></i></a>
      {{/if}}
      {{/if}}
      {{/if}}

		</p>
	</div>
	<div class="widget widget--search position-absolute top-0 end-0 w-50" id="search-autocomplete-results">
		<h3 class="h6">Search</h3>
		<form action="{{$nav.search.4}}" method="get" role="search">
			<input type="text" value="{{$nav.search.3}}" onblur="if(this.value == '') { this.value = '{{$nav.search.3}}'; }"
												 onfocus="if (this.value == '{{$nav.search.3}}') { this.value = ''; }" class="text-search" id="nav-search-text" name="search">
			<input type="submit" class="submit-search">
		</form>
	</div>

</div> <!-- end row -->
<nav class="s-header__nav-wrap">

	<div class="row">

		<ul class="s-header__nav"> <!-- Pinned user apps -->
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
			<!-- Featured apps -->
			<li class="has-children"><a href="#0">{{$featured_apps}}</a>
				<ul>
					{{foreach $nav_apps as $nav_app}}
					{{$nav_app}}
					{{/foreach}}
				</ul>
			</li>
			{{else}}
			<!-- System apps   -->
			<li class="has-children"><a href="#0">{{$sysapps}}</a>
				<ul>
					{{foreach $nav_apps as $nav_app}}
					{{$nav_app}}
					{{/foreach}}
				</ul>
			</li>
			{{/if}}
			<li class="has-children"><a href="#" id="user-toggle"><i class="bi bi-person-lines-fill"></i></a>
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

		</ul> <!-- end #nav -->

	</div>

</nav> <!-- end #nav-wrap -->

<a class="header-menu-toggle" href="#0" title="Menu"><span>Menu</span></a>
