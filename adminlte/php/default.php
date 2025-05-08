<?php
/**
 *   * Name: default
 *   * Description: AdminLTE default 2-column layout
 *   * Version: 1.1
 *   * Author: Saiwal
 *   * Maintainer: Saiwal
 *   * ContentRegion: aside, right_aside_wrapper
 *   * ContentRegion: right_aside, left_aside_wrapper
 *   * ContentRegion: content, region_2
 */
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?php if (x($page, 'title')) echo $page['title'] ?></title>
  <script>
    var baseurl = "<?php echo z_root() ?>";
  </script>
  <?php if (x($page, 'htmlhead')) echo $page['htmlhead'] ?>
</head>

<body class="layout-fixed sidebar-expand-md sidebar-mini app-loaded sidebar-open">
  <div class="app-wrapper">

    <header><?php if(x($page,'header')) echo $page['header']; ?></header>
    
    <?php echo x($page, 'topnav') ? $page['topnav'] : (x($page, 'nav') ? $page['nav'] : ''); ?>

    <!-- Content Wrapper. Contains page content -->
    <main class="app-main px-1 py-3" style="min-height: calc(100vh - 56px);">
      <div class="container-xl">
        <div class="row">
        <div id="region_2" class="col-12 col-md-12 col-lg-8">
          <?php if (x($page, 'content')) echo $page['content']; ?>
        </div>

        <div class="d-lg-block col-lg-4 sticky-column pe-0">
          <div class="offcanvas-lg offcanvas-end" tabindex="-1" id="offcanvasResponsive" aria-labelledby="offcanvasResponsiveLabel">
            <div class="offcanvas-header mt-2">
              <button type="button" class="btn-close" data-bs-dismiss="offcanvas" data-bs-target="#offcanvasResponsive" aria-label="Close"></button>
            </div>
            <div class="offcanvas-body px-0">
              <div class="container row pe-0">
                <div id="region_1" class="pe-0">
                <div id="left_aside_wrapper">
                <?php if (x($page, 'right_aside')) echo $page['right_aside']; ?>
                </div>
                </div>
                <div id="region_3" class="pe-0">
                <div id="right_aside_wrapper">
                <?php if (x($page, 'aside')) echo $page['aside']; ?>
              </div>
              </div>
            </div>
          </div>
        </div>
        </div>
      </div>
    </main>
    <!-- /.content-wrapper -->

  </div>
<!-- Search Modal -->
<div class="modal fade" id="searchModal" tabindex="-1" aria-labelledby="searchModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="searchModalLabel">Search</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form class="d-flex" method="get" action="{{$nav.search.4}}" role="search">
                    <input class="form-control form-control-sm me-2" id="nav-search-text" type="text" value="" placeholder="{{$help}}" name="search" title="{{$nav.search.3}}" />
                    <button type="submit" class="btn btn-outline-secondary btn-sm"><i class="bi bi-search"></i></button>
                </form>
                <div id="nav-search-spinner" class="spinner-wrapper d-none">
                    <div class="spinner s"></div>
                </div>
            </div>
        </div>
    </div>
</div>    
  <!-- ./wrapper -->
  <script>
    // Color Mode Toggler
    (() => {
      "use strict";

      const storedTheme = localStorage.getItem("theme");

      const getPreferredTheme = () => {
        if (storedTheme) {
          return storedTheme;
        }

        return window.matchMedia("(prefers-color-scheme: dark)").matches ?
          "dark" :
          "light";
      };

      const setTheme = function(theme) {
        if (theme === "auto" && window.matchMedia("(prefers-color-scheme: dark)").matches) {
          document.documentElement.setAttribute("data-bs-theme", "dark");
        } else {
          document.documentElement.setAttribute("data-bs-theme", theme);
        }
      };

      const showActiveTheme = (theme, focus = false) => {
        const themeSwitcher = document.querySelector("#bd-theme");

        if (!themeSwitcher) {
          return;
        }

        const btnToActive = document.querySelector(`[data-bs-theme-value="${theme}"]`);
        const inputToCheck = document.querySelector(`#bd-theme input[data-bs-theme-value="${theme}"]`);

        for (const element of document.querySelectorAll("[data-bs-theme-value]")) {
          element.classList.remove("active");
          element.setAttribute("aria-pressed", "false");
        }

        btnToActive.classList.add("active");
        btnToActive.setAttribute("aria-pressed", "true");

        // Update the checked state of the radio button
        if (inputToCheck) {
          inputToCheck.checked = true;
        }

        if (focus) {
          btnToActive.focus();
        }
      };

      setTheme(getPreferredTheme());

      window
        .matchMedia("(prefers-color-scheme: dark)")
        .addEventListener("change", () => {
          if (storedTheme !== "light" || storedTheme !== "dark") {
            setTheme(getPreferredTheme());
          }
        });

      window.addEventListener("DOMContentLoaded", () => {
        showActiveTheme(getPreferredTheme());

        for (const toggle of document.querySelectorAll("[data-bs-theme-value]")) {
          toggle.addEventListener("click", () => {
            const theme = toggle.getAttribute("data-bs-theme-value");
            localStorage.setItem("theme", theme);
            setTheme(theme);
            showActiveTheme(theme, true);
          });
        }
      });
    })();
  </script>
  <script>
    const SELECTOR_SIDEBAR_WRAPPER = ".sidebar-wrapper";
    const Default = {
      scrollbarTheme: "os-theme-light",
      scrollbarAutoHide: "leave",
      scrollbarClickScroll: true,
    };
    document.addEventListener("DOMContentLoaded", function() {
      const sidebarWrapper = document.querySelector(SELECTOR_SIDEBAR_WRAPPER);
      if (
        sidebarWrapper &&
        typeof OverlayScrollbarsGlobal?.OverlayScrollbars !== "undefined"
      ) {
        OverlayScrollbarsGlobal.OverlayScrollbars(sidebarWrapper, {
          scrollbars: {
            theme: Default.scrollbarTheme,
            autoHide: Default.scrollbarAutoHide,
            clickScroll: Default.scrollbarClickScroll,
          },
        });
      }
    });
  </script> <!--end::OverlayScrollbars Configure-->
</body>

</html>
