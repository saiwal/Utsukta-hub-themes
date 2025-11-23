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
      <li class="has-children"><a class="">{{$featured_apps}}</a>
        <ul class="sub-menu">
          {{foreach $nav_apps as $nav_app}}
          {{$nav_app}}
          {{/foreach}}
        </ul>
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
      <li class="has-children"><a class="" id="user-toggle"><i class="bi bi-person-lines-fill"></i></a>
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

    </ul> <!-- end header__nav -->


  </nav> <!-- end header__nav-wrap -->

  <!-- menu toggle -->
  <a href="#0" class="header__menu-toggle">
    <span>Menu</span>
  </a>

</header> <!-- end s-header -->
