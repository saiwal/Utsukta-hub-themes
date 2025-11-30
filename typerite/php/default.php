<?php
/**
 *   * Name: default
 *   * Description: typerites default layout
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


<body class="ss-bg-white">

    <!-- preloader
    ================================================== -->
    <div id="preloader">
        <div id="loader" class="dots-fade">
            <div></div>
            <div></div>
            <div></div>
        </div>
    </div>

	  <header><?php if(x($page,'header')) echo $page['header']; ?></header>
    <div id="top" class="s-wrap site-wrapper">


        <?php if (x($page, 'nav')) echo $page['nav']; ?>

        <!-- site content
        ================================================== -->
        <div class="s-content content">
            <main class="row content__page">
                
                <section class="column large-full entry format-standard">

                  <?php if (x($page, 'content')) echo $page['content']; ?>
                 
                </section>

            </main>

        </div> <!-- end s-content -->


        <!-- footer
        ================================================== -->
        <footer class="s-footer footer">
            <div class="row">
                <div class="column large-full footer__content">
                    <div class="footer__copyright">
                        <span>© Copyright Typerite 2021</span> 
                        <span>Design by <a href="https://www.styleshout.com/">StyleShout</a></span>
                    </div>
                </div>
            </div>

            <div class="go-top">
                <a class="smoothscroll" title="Back to Top" href="#top"></a>
            </div>
        </footer>

    </div> <!-- end s-wrap -->

<script src="/view/theme/typerite/js/plugins.js"></script>
<script src="/view/theme/typerite/js/mainjs.js"></script>
</body>
</html>

