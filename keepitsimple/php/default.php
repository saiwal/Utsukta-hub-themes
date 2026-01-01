<?php

/**
 * * Name: default
 *   * Description: keepitsimples default layout
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

    <!-- preloader
    ================================================== -->
    <div id="preloader">
        <div id="loader" class="dots-fade">
            <div></div>
            <div></div>
            <div></div>
        </div>
    </div>

	  <header><?php if (x($page, 'header')) echo $page['header']; ?></header>
    <!-- Header
    ================================================== -->
    <header class="s-header">

        <?php if (x($page, 'nav')) echo $page['nav']; ?>

    </header> <!-- Header End -->
    <!-- Content
    ================================================== -->
    <div class="s-content">

        <div class="row">

            <main id="main" class="s-content__main large-8 column">

                <section class="page-content">

                <?php if (x($page, 'content')) echo $page['content']; ?>
               
                </section> <!-- end page -->

           </main> <!-- end main -->


           <div id="sidebar" class="s-content__sidebar large-4 column">
             <div class="widget widget--search">
                  <h3 class="h6">Search</h3> 
                  <form action="#">
                     <input type="search" value="" onblur="if(this.value == '') { this.value = 'Search here...'; }" onfocus="if (this.value == 'Search here...') { this.value = ''; }" class="text-search" id="nav-search-text">
                     <input type="submit" value="Search" class="submit-search">
                  </form>
               </div>
              <?php if (x($page, 'right_aside')) echo $page['right_aside']; ?>
              <?php if (x($page, 'aside')) echo $page['aside']; ?>
              
           </div> <!-- end sidebar -->

       </div> <!-- end row -->

   </div> <!-- end content-wrap -->


    <!-- Footer
    ================================================== -->
    <footer class="s-footer">

        <div class="row s-footer__top">
            <div class="column">
                <ul class="s-footer__social">
                    <li><a href="#0"><i class="fab fa-facebook-f" aria-hidden="true"></i></a></li>
                    <li><a href="#0"><i class="fab fa-twitter" aria-hidden="true"></i></a></li>
                    <li><a href="#0"><i class="fab fa-youtube" aria-hidden="true"></i></a></li>
                    <li><a href="#0"><i class="fab fa-vimeo-v" aria-hidden="true"></i></a></li>
                    <li><a href="#0"><i class="fab fa-instagram" aria-hidden="true"></i></a></li>
                    <li><a href="#0"><i class="fab fa-linkedin" aria-hidden="true"></i></a></li>
                    <li><a href="#0"><i class="fab fa-skype" aria-hidden="true"></i></a></li>
                </ul>
            </div>
        </div> <!-- end footer__top -->

        <div class="row s-footer__bottom">

            <?php if (x($page, 'footer')) echo $page['footer']; ?>
           
            <div class="ss-copyright">
                <span>Design by <a href="https://www.styleshout.com/">StyleShout</a> Adapted for Hubzilla</span>
            </div>

        </div> <!-- end footer__bottom -->


        <div class="ss-go-top">
            <a class="smoothscroll" title="Back to Top" href="#top">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path d="M12 0l8 9h-6v15h-4v-15h-6z"/></svg>
            </a>
        </div> <!-- end ss-go-top -->

    </footer> <!-- end Footer-->

<script src="/view/theme/keepitsimple/js/maintr.js"></script>
</body>
</html>

