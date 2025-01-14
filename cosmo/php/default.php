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

<body class="layout-fixed sidebar-expand-lg sidebar-mini bg-body-tertiary app-loaded sidebar-open">
  <div class="app-wrapper">

    <?php if (x($page, 'nav')) echo $page['nav']; ?>

    <!-- Content Wrapper. Contains page content -->
    <main class="app-main px-3 py-3" style="min-height: calc(100vh - 56px);">
      <div class="row">

        <div class="col-md-4 d-md-block col-lg-4 col-xl-3">
          <div class="offcanvas-md offcanvas-start" tabindex="-1" id="offcanvasResponsive" aria-labelledby="offcanvasResponsiveLabel">
            <div class="offcanvas-header">
              <button type="button" class="btn-close" data-bs-dismiss="offcanvas" data-bs-target="#offcanvasResponsive" aria-label="Close"></button>
            </div>
            <div class="offcanvas-body">
              <div class="container">
                <?php if (x($page, 'aside')) echo $page['aside']; ?>
              </div>
            </div>
          </div>
        </div>

        <div class="col-12 col-md-8 col-lg-8 col-xl-6">
          <?php if (x($page, 'content')) echo $page['content']; ?>
        </div>

        <div class="d-none d-xl-block col-3">
          <?php if (x($page, 'right_aside')) echo $page['right_aside']; ?>
        </div>

      </div>
    </main>
    <!-- /.content-wrapper -->

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
