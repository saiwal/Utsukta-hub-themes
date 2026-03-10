<nav class="app-header navbar navbar-expand  bg-body-tertiary border-0 sticky-top shadow"> 
  <!--begin::Container-->
  <div class="container-fluid"> <!--begin::Start Navbar Links-->
    <ul class="navbar-nav">
      <li class="nav-item" id="toggle-sidebar">
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
<!-- Search Button in Navbar -->
<li class="nav-item" id="nav-search-btn">
    <a class="nav-link" href="#" title="{{$nav.search.3}}" data-bs-toggle="modal" data-bs-target="#searchModal"><i class="bi bi-search generic-icons"></i></a>
</li>

 			{{if $localuser || $nav.pubs}}
      <!--Notification icon-->
      <li class="nav-item dropdown">
        <a id="notifications-btn-1" class="nav-link notifications-btn" data-bs-toggle="dropdown" href="#" aria-expanded="true">
          <i id="notifications-btn-icon-1" class="bi bi-bell notifications-btn-icon"></i>
        </a>
        <div class="dropdown-menu dropdown-menu-lg dropdown-menu-end " data-bs-popper="static">
      {{include "notifications_widget_topnav.tpl"}}
        </div>
      </li>

			{{/if}}

      <!-- right sidebar button on smaller screen-->
      <li class="nav-item">
        <a class="nav-link d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#offcanvasResponsive"
          aria-controls="offcanvasResponsive"><i class="bi bi-layout-text-sidebar"></i></a>
      </li>

    </ul> <!--end::End Navbar Links-->
  </div> <!--end::Container-->
</nav>

{{include "sidebar.tpl"}}

