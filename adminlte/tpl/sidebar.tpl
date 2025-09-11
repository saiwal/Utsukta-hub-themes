<!--begin::Sidebar-->
<aside class="app-sidebar bg-dark shadow d-flex flex-column" data-bs-theme="dark">
  <!--begin::Sidebar Brand-->
  <div class="sidebar-brand border-0">
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
  <div class="sidebar-wrapper d-flex flex-column">
    <nav class="mt-2">
      <!--begin::Sidebar Menu-->
      <ul class="nav sidebar-menu flex-column" data-lte-toggle="treeview" role="menu" data-accordion="false">
        <li class="nav-header pb-3 pt-1"><div class="d-flex justify-content-center">
          <div id="bd-theme" class="btn-group" role="group" aria-label="Basic radio toggle button group">
            <input type="radio" class="btn-check" name="btnradio" id="btnradio1" autocomplete="off" checked
              data-bs-theme-value="auto">
            <label class="btn btn-sm btn-dark" for="btnradio1"><i
                class="bi bi-circle-half me-1"></i>Auto</label>

            <input type="radio" class="btn-check" name="btnradio" id="btnradio2" autocomplete="off"
              data-bs-theme-value="dark">
            <label class="btn btn-sm btn-dark" for="btnradio2"><i
                class="bi bi-moon-fill me-1"></i>Dark</label>

            <input type="radio" class="btn-check" name="btnradio" id="btnradio3" autocomplete="off"
              data-bs-theme-value="light">
            <label class="btn btn-sm btn-dark" for="btnradio3"><i
                class="bi bi-sun-fill me-1"></i>Light</label>
          </div></div>
        </li>
        <!-- Pinned user apps -->
        {{if $navbar_apps.0}}
        <li class="nav-item menu-open">
          <a href="#" class="nav-link"> <i class="nav-icon bi bi-pin-angle-fill"></i>
            <p>{{$pinned_apps}}<i class="nav-arrow bi bi-chevron-right"></i></p>
          </a>
          <ul id="nav-app-bin-container" class="nav nav-treeview" style="display: block; box-sizing: border-box;">
            {{foreach $navbar_apps as $navbar_app}}
            {{$navbar_app|replace:'fa':'generic-icons-nav fa'}}
            {{/foreach}}
          </ul>
        </li>
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
          <a href="#" class="nav-link"> <i class="nav-icon bi bi-star-fill"></i>
            <p>{{$featured_apps}}<i class="nav-arrow bi bi-chevron-right"></i></p>
          </a>
          <ul id="app-bin-container" data-token="{{$form_security_token}}" class="nav nav-treeview"
            style="display: none; box-sizing: border-box;">
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
  <div class="d-flex justify-content-around bg-body-tertiary p-2">
      <a href="/siteinfo" class="btn btn-dark btn-sm"><i class="bi bi-info-circle"> Siteinfo</i></a>
  </div>
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
      let app_str = '';
      $('#app-bin-container a').each(function () {
        if (app_str.length) {
          app_str = app_str.concat(',', $(this).text());
        }
        else {
          app_str = app_str.concat($(this).text());
        }
      });
      $.post(
        'pconfig',
        {
          'aj': 1,
          'cat': 'system',
          'k': 'app_order',
          'v': app_str,
          'form_security_token': $('#app-bin-container').data('token')
        }
      );

    }
  });
  var nav_app_bin_container = document.getElementById('nav-app-bin-container');
  new Sortable(nav_app_bin_container, {
    animation: 150,
    delay: 200,
    delayOnTouchOnly: true,
    onEnd: function (e) {
      let nav_app_str = '';
      $('#nav-app-bin-container a').each(function () {
        if (nav_app_str.length) {
          nav_app_str = nav_app_str.concat(',', $(this).text());
        }
        else {
          nav_app_str = nav_app_str.concat($(this).text());
        }
      });
      $.post(
        'pconfig',
        {
          'aj': 1,
          'cat': 'system',
          'k': 'app_pin_order',
          'v': nav_app_str,
          'form_security_token': $('#app-bin-container').data('token')
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
  <!-- Search Modal -->
  <div class="modal fade" id="searchModal" tabindex="-1" aria-labelledby="searchModalLabel" aria-hidden="true">
      <div class="modal-dialog modal-fullscreen-sm-down modal-dialog-scrollable">
          <div class="modal-content">
              <div class="modal-header">
                  <h5 class="modal-title" id="searchModalLabel">Search</h5>
                  <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
              </div>
              <div class="modal-body" id="search-autocomplete-results">
                  <form class="d-flex" method="get" action="{{$nav.search.4}}" role="search">
                      <input class="form-control form-control-sm me-2" id="nav-search-text" type="text" value="" placeholder="{{$nav.search.3}}" name="search" title="{{$nav.search.3}}" />
                      <button type="submit" class="btn btn-outline-secondary btn-sm"><i class="bi bi-search"></i></button>
                  </form>
                  <div id="nav-search-spinner" class="spinner-wrapper d-none">
                      <div class="spinner s"></div>
                  </div>
              </div>
          </div>
      </div>
  </div>    
<!-- JavaScript for Modal Focus -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const searchModal = document.getElementById('searchModal');
    const searchInput = document.getElementById('nav-search-text');

    // Focus input when modal opens
    searchModal.addEventListener('shown.bs.modal', function() {
        searchInput.focus();
    });
});
</script>
