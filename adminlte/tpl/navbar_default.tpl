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

