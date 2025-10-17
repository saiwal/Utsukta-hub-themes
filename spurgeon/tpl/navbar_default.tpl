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
      <li class="has-children"><a href="#0">{{$featured_apps}}</a>
        <ul class="sub-menu">
          {{foreach $nav_apps as $nav_app}}
          {{$nav_app}}
          {{/foreach}}
        </ul>
      </li>
      {{else}}
      <!-- System apps   -->
      <li class="has-children"><a href="#0">{{$sysapps}}</a>
        <ul class="sub-menu">
          {{foreach $nav_apps as $nav_app}}
          {{$nav_app}}
          {{/foreach}}
        </ul>
      </li>
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

      <a href="#0" title="Close Search" class="s-header__search-close">Close</a>

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
<a href="#" class="user-menu" aria-label="User Menu">
  <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
    <line x1="3" y1="6" x2="21" y2="6"></line>
    <line x1="3" y1="12" x2="21" y2="12"></line>
    <line x1="3" y1="18" x2="21" y2="18"></line>
  </svg>
</a>
