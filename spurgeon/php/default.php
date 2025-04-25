<?php
/**
 *   * Name: default
 *   * Description: Spurgeons default layout
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
    <link rel="stylesheet" href="/view/theme/spurgeon/css/vendor.css" type="text/css" media="screen">
    <link rel="stylesheet" href="/view/theme/spurgeon/css/styles.css" type="text/css" media="screen">
    <script>
      var baseurl = "<?php echo z_root() ?>";
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


	  <header><?php if(x($page,'header')) echo $page['header']; ?></header>
    <div id="page" class="s-pagewrap">


    <?php if (x($page, 'nav')) echo $page['nav']; ?>

      <div id="content" class="s-content s-content--page app-main">
        <main>
          <div class="row entry-wrap">
            <div class="column lg-12">
              <?php if (x($page, 'aside')) echo $page['aside']; ?>
              <?php if (x($page, 'content')) echo $page['content']; ?>
            </div>
          </div>
        </main>
      </div>


        <footer id="colophon" class="s-footer">
          <div class="row s-footer__main">
            <?php if (x($page, 'footer')) echo $page['footer']; ?>
          </div> <!-- end s-footer__main -->

          <div class="row s-footer__bottom">

                <div class="column lg-7 md-6 tab-12">
                </div>
                <div class="column lg-5 md-6 tab-12">
                    <div class="ss-copyright">
                        <span>© Copyright Spurgeon 2021</span> 
                        <span>Design by <a href="https://www.styleshout.com/">StyleShout</a>, Adapted for Hubzilla by Saiwal</span>
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
</body>
</html>

