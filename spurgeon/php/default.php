<?php

/**
 * * Name: Default
 *   * Description: Spurgeons default layout with left column
 *   * Version: 1.0
 *   * Author: Saiwal
 *   * Maintainer: Saiwal
 *   * ContentRegion: aside, right_aside_wrapper
 *   * ContentRegion: right_aside, left_aside_wrapper
 *   * ContentRegion: content, region_2
 */
?>
<!DOCTYPE html>
<html lang="en" class="no-js" >
<head>

    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php if (x($page, 'title')) echo $page['title'] ?></title>
    <script>
      var baseurl = "<?php echo z_root() ?>";
    </script>
    <script>
        document.documentElement.classList.remove('no-js');
        document.documentElement.classList.add('js');
    </script>


    <?php if (x($page, 'htmlhead')) echo $page['htmlhead'] ?>
</head>


<body id="top">

    <div id="preloader">
        <div id="loader" class="dots-fade">
            <div></div>
            <div></div>
            <div></div>
        </div>
    </div>


    <div id="page" class="s-pagewrap">
	    <header><?php if (x($page, 'header')) echo $page['header']; ?></header>
  
      <header id="masthead" class="s-header">
        <?php if (x($page, 'nav')) echo $page['nav']; ?>
      </header>

        <?php if (x($page, 'chan_hero')) echo $page['chan_hero']; ?>
      <section id="content" class="s-content s-content--page">
          <div id="region_2" class="row entry-wrap">
            <div class="column lg-12">
              <?php if (x($page, 'content')) echo $page['content']; ?>
            </div>
          </div>

      </section>


        <footer id="colophon" class="s-footer">
        <div class="row s-footer__main">
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

              <?php if (x($page, 'footer')) echo $page['footer']; ?>
          </div> <!-- end s-footer__main -->

          <div class="row s-footer__bottom">

                <div class="column lg-12 tab-12">
                    <div class="ss-copyright">
                        <span>© Copyright Spurgeon 2021</span> 
                        <span>Design by <a href="https://www.styleshout.com/">StyleShout</a>, Adapted for Hubzilla</span>
                    </div>
                </div>

          </div> <!-- end s-footer__bottom -->
           
          <div class="ss-go-top">
                <a class="smoothscroll" title="Back to Top" href="#top">
                    <svg width="24" height="24" fill="none" viewBox="0 0 24 24">
                        <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17.25 10.25L12 4.75L6.75 10.25"/>
                        <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 19.25V5.75"/>
                    </svg>
                </a>
          </div> <!-- end ss-go-top -->

        </footer><!-- end s-footer -->
    </div>
<script src="/view/theme/spurgeon/js/plugins.js"></script>
    <script src="/view/theme/spurgeon/js/scripts.js"></script>
    <script>
document.addEventListener("DOMContentLoaded", function () {
  const sidebar = document.getElementById("sidebar-column");
  const toggle = document.getElementById("sidebar-toggle");

  if (!sidebar || !toggle) return;

  toggle.addEventListener("click", function (e) {
    e.preventDefault(); // prevent page jump
    sidebar.classList.toggle("is-open");
    document.body.classList.toggle("no-scroll");
  });

  // Optional: close sidebar when clicking outside
  document.addEventListener("click", function (e) {
    if (
      sidebar.classList.contains("is-open") &&
      !sidebar.contains(e.target) &&
      !toggle.contains(e.target)
    ) {
      sidebar.classList.remove("is-open");
      document.body.classList.remove("no-scroll");
    }
  });
});
    </script>
</body>
</html>

