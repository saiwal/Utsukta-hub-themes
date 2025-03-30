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


	  <header><?php if(x($page,'header')) echo $page['header']; ?></header>
    <div id="page" class="s-pagewrap ss-home">


    <?php if (x($page, 'nav')) echo $page['nav']; ?>

        <section id="content" class="s-content app-main">


            <div class="hero">

                <div class="hero__slider swiper-container">

                    <div class="swiper-wrapper">
                        <article class="hero__slide swiper-slide">
                            <div class="hero__entry-image" style="background-image: url('images/thumbs/featured/featured-01_2000.jpg');"></div>
                            <div class="hero__entry-text">
                                <div class="hero__entry-text-inner">
                                    <div class="hero__entry-meta">
                                        <span class="cat-links">
                                            <a href="category.html">Inspiration</a>
                                        </span>
                                    </div>
                                    <h2 class="hero__entry-title">
                                        <a href="single-standard.html">
                                            Understanding and Using Negative Space.
                                        </a>
                                    </h2>
                                    <p class="hero__entry-desc">
                                    Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor 
                                    incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis 
                                    nostrud corporis est laudantium voluptatum consectetur adipiscing. 
                                    </p>
                                    <a class="hero__more-link" href="single-standard.html">Read More</a>
                                </div>
                            </div>
                        </article>
                        <article class="hero__slide swiper-slide">
                            <div class="hero__entry-image" style="background-image: url('images/thumbs/featured/featured-02_2000.jpg');"></div>
                            <div class="hero__entry-text">
                                <div class="hero__entry-text-inner">
                                    <div class="hero__entry-meta">
                                        <span class="cat-links">
                                            <a href="category.html">Health</a>
                                        </span>
                                    </div>
                                    <h2 class="hero__entry-title">
                                        <a href="single-standard.html">
                                            10 Reasons Why Being in Nature Is Good For You.
                                        </a>
                                    </h2>
                                    <p class="hero__entry-desc">
                                    Voluptas harum sequi rerum quasi quisquam. Est tenetur ut doloribus in aliquid animi nostrum. Tempora 
                                    quibusdam ad nulla. Quis autem possimus dolores est est fugiat saepe vel aut. Earum consequatur.
                                    </p>
                                    <a class="hero__more-link" href="single-standard.html">Read More</a>
                                </div>
                            </div>
                        </article>
                        <article class="hero__slide swiper-slide">
                            <div class="hero__entry-image" style="background-image: url('images/thumbs/featured/featured-03_2000.jpg');"></div>
                            <div class="hero__entry-text">
                                <div class="hero__entry-text-inner">
                                    <div class="hero__entry-meta">
                                        <span class="cat-links">
                                            <a href="category.html">Lifestyle</a>
                                        </span>
                                    </div>
                                    <h2 class="hero__entry-title">
                                        <a href="single-standard.html">
                                            Six Relaxation Techniques to Reduce Stress.
                                        </a>
                                    </h2>
                                    <p class="hero__entry-desc">
                                    Quasi consequatur quia excepturi ullam velit. Repellat velit vel occaecati neque perspiciatis quibusdam nulla. 
                                    Unde et earum. Nostrum nulla optio debitis odio modi. Quis autem possimus dolores est est fugiat saepe vel aut.
                                    </p>
                                    <a class="hero__more-link" href="single-standard.html">Read More</a>
                                </div>
                            </div>
                        </article>
                    </div> <!-- swiper-wrapper -->

                    <div class="swiper-pagination"></div>
    
                </div> <!-- end hero slider -->

                <a href="#bricks" class="hero__scroll-down smoothscroll">
                    <svg width="24" height="24" fill="none" viewBox="0 0 24 24">
                        <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M10.25 6.75L4.75 12L10.25 17.25"></path>
                        <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19.25 12H5"></path>
                    </svg>
                    <span>Scroll</span>
                </a>

            </div> <!-- end hero -->


            <div id="bricks" class="bricks">

                <div class="masonry">

                    <div class="bricks-wrapper" data-animate-block>

                        <div class="grid-sizer"></div>

                        <article class="brick entry" data-animate-el>
        
                            <div class="entry__thumb">
                                <a href="single-standard.html" class="thumb-link">
                                    <img src="images/thumbs/masonry/statue-600.jpg" 
                                        srcset="images/thumbs/masonry/statue-600.jpg 1x, images/thumbs/masonry/statue-1200.jpg 2x" alt="">
                                </a>
                            </div> <!-- end entry__thumb -->
        
                            <div class="entry__text">
                                <div class="entry__header">
                                    <div class="entry__meta">
                                        <span class="cat-links">
                                            <a href="#">Design</a>
                                        </span>
                                        <span class="byline">
                                            By:
                                            <a href="#0">Naruto Uzumaki</a>
                                        </span>
                                    </div>
                                    <h1 class="entry__title"><a href="single-standard.html">Just a Normal Simple Blog Post.</a></h1>
                                 </div>
                                <div class="entry__excerpt">
                                    <p>
                                    Lorem ipsum Sed eiusmod esse aliqua sed incididunt aliqua incididunt mollit id et sit proident dolor nulla 
                                    sed commodo est ad minim elit reprehenderit nisi officia aute incididunt velit sint in aliqua cillum in.
                                    </p>
                                </div>
                                <a class="entry__more-link" href="#0">Read More</a>
                            </div> <!-- end entry__text -->
                        
                        </article> <!-- end article -->

                        <article class="brick entry" data-animate-el>
        
                            <div class="entry__thumb">
                                <a href="single-standard.html" class="thumb-link">
                                    <img src="images/thumbs/masonry/beetle-600.jpg" 
                                        srcset="images/thumbs/masonry/beetle-600.jpg 1x, images/thumbs/masonry/beetle-1200.jpg 2x" alt="">
                                </a>
                            </div> <!-- end entry__thumb -->
        
                            <div class="entry__text">
                                <div class="entry__header">
                                    <div class="entry__meta">
                                        <span class="cat-links">
                                            <a href="#">Lifestyle</a>
                                        </span>
                                        <span class="post-date">
                                            By:
                                            <a href="#0">Sasuke Uchiha</a>
                                        </span>
                                    </div>
                                    <h1 class="entry__title"><a href="single-standard.html">Throwback To The Good Old Days.</a></h1>
                                </div>
                                <div class="entry__excerpt">
                                    <p>
                                    Ipsam odio corrupti et dolores odit aliquid quo. Dolore consectetur a sit modi quam debitis non omnis. Enim ullam 
                                    voluptatem ipsum soluta sed debitis nihil quasi. Et et et sit. Lorem ipsum Sed eiusmod esse aliqua sed incididunt.
                                    </p>
                                </div>
                                <a class="entry__more-link" href="#0">Read More</a>
                            </div> <!-- end entry__text -->
                        
                        </article> <!-- end article -->

                        <article class="brick entry" data-animate-el>
            
                            <div class="entry__thumb">
                                <a href="single-standard.html" class="thumb-link">
                                    <img src="images/thumbs/masonry/grayscale-600.jpg" 
                                        srcset="images/thumbs/masonry/grayscale-600.jpg 1x, images/thumbs/masonry/grayscale-1200.jpg 2x" alt="">
                                </a>
                            </div> <!-- end entry__thumb -->
            
                            <div class="entry__text">
                                <div class="entry__header">
                                    <div class="entry__meta">
                                        <span class="cat-links">
                                            <a href="#">Design</a>
                                        </span>
                                        <span class="byline">
                                            By:
                                            <a href="#0">Naruto Uzumaki</a>
                                        </span>
                                    </div>
                                    <h1 class="entry__title"><a href="single-standard.html">5  Grayscale Coloring Techniques.</a></h1>
                                </div>
                                <div class="entry__excerpt">
                                    <p>
                                    Quo saepe magni magnam expedita nobis. Rerum assumenda necessitatibus tempora dolorem. Harum animi tempora odio natus et 
                                    et perferendis possimus. Aut quo mollitia libero molestiae aut molestiae voluptate tempore. Eius voluptatem eligendi .
                                    </p>
                                </div>
                                <a class="entry__more-link" href="#0">Read More</a>
                            </div> <!-- end entry__text -->
                            
                        </article> <!-- end article -->

                        <article class="brick entry" data-animate-el>
            
                            <div class="entry__thumb">
                                <a href="single-standard.html" class="thumb-link">
                                    <img src="images/thumbs/masonry/woodcraft-600.jpg" 
                                        srcset="images/thumbs/masonry/woodcraft-600.jpg 1x, images/thumbs/masonry/woodcraft-1200.jpg 2x" alt="">
                                </a>
                            </div> <!-- end entry__thumb -->
            
                            <div class="entry__text">
                                <div class="entry__header">
                                    <div class="entry__meta">
                                        <span class="cat-links">
                                            <a href="#">Lifestyle</a>
                                        </span>
                                        <span class="byline">
                                            By:
                                            <a href="#0">Shikamaru Nara</a>
                                        </span>
                                    </div>
                                    <h1 class="entry__title"><a href="single-standard.html">What Minimalism Really Looks Like.</a></h1>
                                 </div>
                                <div class="entry__excerpt">
                                    <p>
                                    Lorem ipsum Sed eiusmod esse aliqua sed incididunt aliqua incididunt mollit id et sit proident dolor nulla sed commodo 
                                    est ad minim elit reprehenderit nisi officia aute incididunt velit sint in aliqua cillum in consequat consequat.
                                    </p>
                                </div>
                                <a class="entry__more-link" href="#0">Read More</a>
                            </div> <!-- end entry__text -->
            
                        </article> <!-- end entry -->
        
                        <article class="brick entry" data-animate-el>
        
                            <div class="entry__thumb">
                                <a href="single-standard.html" class="thumb-link">
                                    <img src="images/thumbs/masonry/tulips-600.jpg" 
                                        srcset="images/thumbs/masonry/tulips-600.jpg 1x, images/thumbs/masonry/tulips-1200.jpg 2x" alt="">
                                </a>
                            </div>  <!-- end entry__thumb -->
        
                            <div class="entry__text">
                                <div class="entry__header">
                                    <div class="entry__meta">
                                        <span class="cat-links">
                                            <a href="#">Health</a>
                                        </span>
                                        <span class="byline">
                                            By:
                                            <a href="#0">Kakashi Hatake</a>
                                        </span>
                                    </div>
                                    <h1 class="entry__title"><a href="single-standard.html">10 Interesting Facts About Caffeine.</a></h1>
                                </div>
                                <div class="entry__excerpt">
                                    <p>
                                    Consequatur amet voluptatem aliquid fuga. Consequatur tempora eos earum deleniti repellendus ducimus. Qui ipsum voluptas 
                                    sed et ad dignissimos explicabo maxime dolor. Rerum quia et. Suscipit similique et. Atque tenetur provident. Excepturi 
                                    autem unde.
                                    </p>
                                </div>
                                <a class="entry__more-link" href="#0">Read More</a>
                            </div> <!-- end entry__text -->
                        
                        </article> <!-- end article -->

                        

                        <article class="brick entry" data-animate-el>
        
                            <div class="entry__thumb">
                                <a href="single-standard.html" class="thumb-link">
                                    <img src="images/thumbs/masonry/red-and-blue-600.jpg" 
                                        srcset="images/thumbs/masonry/red-and-blue-600.jpg 1x, images/thumbs/masonry/red-and-blue-1200.jpg 2x" alt="">
                                </a>
                            </div> <!-- end entry__thumb -->
        
                            <div class="entry__text">
                                <div class="entry__header">
                                    <div class="entry__meta">
                                        <span class="cat-links">
                                            <a href="#">Work</a> 
                                            <a href="#">Design</a>
                                        </span>
                                        <span class="byline">
                                            By:
                                            <a href="#0">Shikamaru Nara</a>
                                        </span>
                                    </div>
                                    <h1 class="entry__title"><a href="single-standard.html">Red and Blue Photo Effects.</a></h1>
                                </div>
                                <div class="entry__excerpt">
                                    <p>
                                    Lorem ipsum Sed eiusmod esse aliqua sed incididunt aliqua incididunt mollit id et sit proident dolor nulla sed commodo 
                                    est ad minim elit reprehenderit nisi officia aute incididunt velit sint in aliqua cillum in consequat consequat in
                                    culpa in anim.
                                    </p>
                                </div>
                                <a class="entry__more-link" href="#0">Read More</a>
                            </div> <!-- end entry__text -->
                        
                        </article> <!-- end article -->
        
                        <article class="brick entry" data-animate-el>
                
                            <div class="entry__thumb">
                                <a href="single-standard.html" class="thumb-link">
                                    <img src="images/thumbs/masonry/white-lamp-600.jpg" 
                                        srcset="images/thumbs/masonry/white-lamp-600.jpg 1x, images/thumbs/masonry/white-lamp-1200.jpg 2x" alt="">
                                </a>
                            </div> <!-- end entry__thumb -->
                
                            <div class="entry__text">
                                <div class="entry__header">
                                    <div class="entry__meta">
                                        <span class="cat-links">
                                            <a href="#">Lifestyle</a>
                                            <a href="#">Work</a>
                                        </span>
                                        <span class="byline">
                                            By:
                                            <a href="#0">Naruto Uzumaki</a>
                                        </span>
                                    </div>
                                    <h1 class="entry__title"><a href="single-standard.html">10 Practical Ways to Be Minimalist.</a></h1>
                                </div>
                                <div class="entry__excerpt">
                                    <p>
                                    Ratione qui voluptas reprehenderit facilis soluta ut nam. Distinctio cum excepturi et. Aperiam blanditiis voluptatem.
                                    A esse sunt nesciunt voluptate. Architecto voluptas id rerum placeat nostrum et optio. Placeat occaecati voluptas.
                                    </p>
                                </div>
                                <a class="entry__more-link" href="#0">Read More</a>
                            </div> <!-- end entry__text -->
                                
                        </article> <!-- end article -->
        
                        <article class="brick entry" data-animate-el>
        
                            <div class="entry__thumb">
                                <a href="single-standard.html" class="thumb-link">
                                    <img src="images/thumbs/masonry/books-600.jpg" 
                                        srcset="images/thumbs/masonry/books-600.jpg 1x, images/thumbs/masonry/books-1200.jpg 2x" alt="">
                                </a>
                            </div> <!-- end entry__thumb -->
        
                            <div class="entry__text">
                                <div class="entry__header">
                                    <div class="entry__meta">
                                        <span class="cat-links">
                                            <a href="#">Health</a>
                                            <a href="#">Lifestyle</a>
                                        </span>
                                        <span class="byline">
                                            By:
                                            <a href="#0">Sakura Haruno</a>
                                        </span>
                                    </div>
                                    <h1 class="entry__title"><a href="single-standard.html">What Does Reading Do to Your Brain?</a></h1>
                                </div>
                                <div class="entry__excerpt">
                                    <p>
                                    Lorem ipsum Sed eiusmod esse aliqua sed incididunt aliqua incididunt mollit id et sit proident dolor nulla sed 
                                    commodo est ad minim elit reprehenderit nisi officia aute incididunt velit sint in aliqua cillum in consequat.
                                    </p>
                                </div>
                                <a class="entry__more-link" href="#0">Read More</a>
                            </div> <!-- end entry__text -->
                        
                        </article> <!-- end article -->
        
                        <article class="brick entry" data-animate-el>
        
                            <div class="entry__thumb">
                                <a href="single-standard.html" class="thumb-link">
                                    <img src="images/thumbs/masonry/lamp-600.jpg" 
                                        srcset="images/thumbs/masonry/lamp-600.jpg 1x, images/thumbs/masonry/lamp-1200.jpg 2x" alt="">
                                </a>
                            </div> <!-- end entry__thumb -->
        
                            <div class="entry__text">
                                <div class="entry__header">
                                    <div class="entry__meta">
                                        <span class="cat-links">
                                            <a href="#">Design</a>
                                            <a href="#">Photography</a>
                                        </span>
                                        <span class="byline">
                                            By:
                                            <a href="#0">Shikamaru Narra</a>
                                        </span>
                                    </div>
                                    <h1 class="entry__title"><a href="single-standard.html">Symmetry In Modern Design.</a></h1>
                                </div>
                                <div class="entry__excerpt">
                                    <p>
                                    Praesentium vel similique laboriosam repudiandae mollitia error. Inventore numquam occaecati omnis beatae fugiat. 
                                    Porro sed numquam doloribus dolores exercitationem recusandae culpa. Sint vel vel quia quis. Non velit eum ea 
                                    tempora quas sapiente.
                                    </p>
                                </div>
                                <a class="entry__more-link" href="#0">Read More</a>
                            </div> <!-- end entry__text -->
                        
                        </article> <!-- end article -->

                        <article class="brick entry" data-animate-el>
        
                            <div class="entry__thumb">
                                <a href="single-standard.html" class="thumb-link">
                                    <img src="images/thumbs/masonry/clock-600.jpg" 
                                        srcset="images/thumbs/masonry/clock-600.jpg 1x, images/thumbs/masonry/clock-1200.jpg 2x" alt="">
                                </a>
                            </div> <!-- end entry__thumb -->
        
                            <div class="entry__text">
                                <div class="entry__header">
                                    <div class="entry__meta">
                                        <span class="cat-links">
                                            <a href="#">Lifestyle</a>
                                            <a href="#">Work</a>
                                        </span>
                                        <span class="byline">
                                            By:
                                            <a href="#0">Sasuke Uchiha</a>
                                        </span>
                                    </div>
                                    <h1 class="entry__title"><a href="single-standard.html">10 Tips for Managing Time Effectively.</a></h1>
                                </div>
                                <div class="entry__excerpt">
                                    <p>
                                    Lorem ipsum Sed eiusmod esse aliqua sed incididunt aliqua incididunt mollit id et sit proident dolor nulla 
                                    sed commodo est ad minim elit reprehenderit nisi officia aute incididunt velit sint in aliqua cillum in anim.
                                    </p>
                                </div>
                                <a class="entry__more-link" href="#0">Read More</a>
                            </div> <!-- end entry__text -->
                        
                        </article> <!-- end article -->

                        

                        <article class="brick entry" data-animate-el>
        
                            <div class="entry__thumb">
                                <a href="single-standard.html" class="thumb-link">
                                    <img src="images/thumbs/masonry/phone-and-keyboard-600.jpg" 
                                        srcset="images/thumbs/masonry/phone-and-keyboard-600.jpg 1x, images/thumbs/masonry/phone-and-keyboard-1200.jpg 2x" alt="">
                                </a>
                            </div> <!-- end entry__thumb -->
        
                            <div class="entry__text">
                                <div class="entry__header">
                                    <div class="entry__meta">
                                        <span class="cat-links">
                                            <a href="https://www.dreamhost.com/r.cgi?287326">Dreamhost</a>
                                        </span>
                                        <span class="byline">
                                            By:
                                            <a href="#0">StyleShout</a>
                                        </span>
                                    </div>
                                    <h1 class="entry__title"><a href="https://www.dreamhost.com/r.cgi?287326">Need Web Hosting for Your Websites?</a></h1>
                                </div>
                                <div class="entry__excerpt">
                                    <p>
                                    Need hosting? We would highly recommend DreamHost.
                                    Enjoy 100% in-house support, guaranteed performance and uptime, 1-click installs, and a super-intuitive control 
                                    panel to make managing your websites and projects easy.
                                    </p>
                                </div>
                                <a class="entry__more-link" href="https://www.dreamhost.com/r.cgi?287326">Learn More</a>
                            </div> <!-- end entry__text -->
                        
                        </article> <!-- end article -->

                        <article class="brick entry" data-animate-el>
        
                            <div class="entry__thumb">
                                <a href="single-standard.html" class="thumb-link">
                                    <img src="images/thumbs/masonry/wheel-600.jpg" 
                                        srcset="images/thumbs/masonry/wheel-600.jpg 1x, images/thumbs/masonry/wheel-1200.jpg 2x" alt="">
                                </a>
                            </div> <!-- end entry__thumb -->
        
                            <div class="entry__text">
                                <div class="entry__header">
                                    <div class="entry__meta">
                                        <span class="cat-links">
                                            <a href="#">Photography</a> 
                                        </span>
                                        <span class="byline">
                                            By:
                                            <a href="#0">Naruto Uzumaki</a>
                                        </span>
                                    </div>
                                    <h1 class="entry__title"><a href="single-standard.html">Black And White Photography Tips.</a></h1>
                                </div>
                                <div class="entry__excerpt">
                                    <p>
                                    Voluptatem maiores aut delectus accusamus et explicabo et. Enim sunt quo odio sit. Hic consequatur et quia voluptas saepe. 
                                    Vel nostrum incidunt ab eum distinctio recusandae. Labore dolore consequatur occaecati iste ex consectetur et perferendis.
                                    </p>
                                </div>
                                <a class="entry__more-link" href="#0">Read More</a>
                            </div> <!-- end entry__text -->
                        
                        </article> <!-- end article -->

                    </div> <!-- end bricks-wrapper -->

                </div> <!-- end masonry-->


                <div class="row pagination">
                    <div class="column lg-12">
                        <nav class="pgn">
                            <ul>
                                <li>
                                    <a class="pgn__prev" href="#0">
                                        <svg width="24" height="24" fill="none" viewBox="0 0 24 24">
                                            <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M10.25 6.75L4.75 12L10.25 17.25"></path>
                                            <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19.25 12H5"></path>
                                        </svg>
                                    </a>
                                </li>
                                <li><a class="pgn__num" href="#0">1</a></li>
                                <li><span class="pgn__num current">2</span></li>
                                <li><a class="pgn__num" href="#0">3</a></li>
                                <li><a class="pgn__num" href="#0">4</a></li>
                                <li><a class="pgn__num" href="#0">5</a></li>
                                <li><span class="pgn__num dots">…</span></li>
                                <li><a class="pgn__num" href="#0">8</a></li>
                                <li>
                                    <a class="pgn__next" href="#0">
                                        <svg width="24" height="24" fill="none" viewBox="0 0 24 24">
                                            <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M13.75 6.75L19.25 12L13.75 17.25"></path>
                                            <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 12H4.75"></path>
                                        </svg>
                                    </a>
                                </li>
                            </ul>
                        </nav> <!-- end pgn -->
                    </div> <!-- end column -->
                </div> <!-- end pagination -->

            </div> <!-- end bricks -->

        </section> <!-- end s-content -->


        <footer id="colophon" class="s-footer">

            <div class="row s-footer__subscribe">
                <div class="column lg-12">

                    <h2>
                    Subscribe to Our Newsletter.
                    </h2>
                    <p>
                    Subscribe now to get all latest updates
                    </p>

                    <form id="mc-form" class="mc-form">
                        <input type="email" name="EMAIL" id="mce-EMAIL" class="u-fullwidth text-center" placeholder="Your Email Address" title="The domain portion of the email address is invalid (the portion after the @)." pattern="^([^\x00-\x20\x22\x28\x29\x2c\x2e\x3a-\x3c\x3e\x40\x5b-\x5d\x7f-\xff]+|\x22([^\x0d\x22\x5c\x80-\xff]|\x5c[\x00-\x7f])*\x22)(\x2e([^\x00-\x20\x22\x28\x29\x2c\x2e\x3a-\x3c\x3e\x40\x5b-\x5d\x7f-\xff]+|\x22([^\x0d\x22\x5c\x80-\xff]|\x5c[\x00-\x7f])*\x22))*\x40([^\x00-\x20\x22\x28\x29\x2c\x2e\x3a-\x3c\x3e\x40\x5b-\x5d\x7f-\xff]+|\x5b([^\x0d\x5b-\x5d\x80-\xff]|\x5c[\x00-\x7f])*\x5d)(\x2e([^\x00-\x20\x22\x28\x29\x2c\x2e\x3a-\x3c\x3e\x40\x5b-\x5d\x7f-\xff]+|\x5b([^\x0d\x5b-\x5d\x80-\xff]|\x5c[\x00-\x7f])*\x5d))*(\.\w{2,})+$" required>
                        <input type="submit" name="subscribe" value="Subscribe" class="btn--small btn--primary u-fullwidth">
                        <!-- <div style="position: absolute; left: -5000px;" aria-hidden="true"><input type="text" name="b_cdb7b577e41181934ed6a6a44_9a91cfe7b3" tabindex="-1" value=""></div> -->
                        <div class="mc-status"></div>
                    </form>

                </div>
            </div> <!-- end s-footer__subscribe -->

            <div class="row s-footer__main">

                <div class="column lg-5 md-6 tab-12 s-footer__about">
                    <h4>Spurgeon</h4>

                    <p>
                    Lorem ipsum dolor sit amet, consectetur 
                    adipiscing elit, sed do eiusmod tempor 
                    incididunt ut labore et dolore magna aliqua. 
                    Ut enim ad minim veniam, quis nostrud exercitation 
                    ullamco laboris nisi ut aliquip ex ea commodo 
                    </p>
                </div> <!-- end s-footer__about -->

                <div class="column lg-5 md-6 tab-12">
                    <div class="row">
                        <div class="column lg-6">
                            <h4>Categories</h4>
                            <ul class="link-list">
                                <li><a href="category.html">Lifestyle</a></li>
                                <li><a href="category.html">Workplace</a></li>
                                <li><a href="category.html">Inspiration</a></li>
                                <li><a href="category.html">Design</a></li>
                                <li><a href="category.html">Health</a></li>
                                <li><a href="category.html">Photography</a></li>
                            </ul>
                        </div>
                        <div class="column lg-6">
                            <h4>Site Links</h4>
                            <ul class="link-list">
                                <li><a href="index.html">Home</a></li>
                                <li><a href="category.html">Categories</a></li>
                                <li><a href="category.html">Blog</a></li>
                                <li><a href="about.html">About</a></li>
                                <li><a href="about.html">Contact</a></li>
                                <li><a href="#0">Terms & Policy</a></li>
                            </ul>
                        </div>
                    </div>
                </div>

            </div> <!-- end s-footer__main -->

            <div class="row s-footer__bottom">

                <div class="column lg-7 md-6 tab-12">
                    <ul class="s-footer__social">
                        <li>
                            <a href="#0">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" style="fill: rgba(0, 0, 0, 1);transform: ;msFilter:;"><path d="M12.001 2.002c-5.522 0-9.999 4.477-9.999 9.999 0 4.99 3.656 9.126 8.437 9.879v-6.988h-2.54v-2.891h2.54V9.798c0-2.508 1.493-3.891 3.776-3.891 1.094 0 2.24.195 2.24.195v2.459h-1.264c-1.24 0-1.628.772-1.628 1.563v1.875h2.771l-.443 2.891h-2.328v6.988C18.344 21.129 22 16.992 22 12.001c0-5.522-4.477-9.999-9.999-9.999z"></path></svg>
                                <span class="u-screen-reader-text">Facebook</span>
                            </a>
                        </li>
                        <li>
                            <a href="#0">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" style="fill:rgba(0, 0, 0, 1);transform:;-ms-filter:"><path d="M19.633,7.997c0.013,0.175,0.013,0.349,0.013,0.523c0,5.325-4.053,11.461-11.46,11.461c-2.282,0-4.402-0.661-6.186-1.809 c0.324,0.037,0.636,0.05,0.973,0.05c1.883,0,3.616-0.636,5.001-1.721c-1.771-0.037-3.255-1.197-3.767-2.793 c0.249,0.037,0.499,0.062,0.761,0.062c0.361,0,0.724-0.05,1.061-0.137c-1.847-0.374-3.23-1.995-3.23-3.953v-0.05 c0.537,0.299,1.16,0.486,1.82,0.511C3.534,9.419,2.823,8.184,2.823,6.787c0-0.748,0.199-1.434,0.548-2.032 c1.983,2.443,4.964,4.04,8.306,4.215c-0.062-0.3-0.1-0.611-0.1-0.923c0-2.22,1.796-4.028,4.028-4.028 c1.16,0,2.207,0.486,2.943,1.272c0.91-0.175,1.782-0.512,2.556-0.973c-0.299,0.935-0.936,1.721-1.771,2.22 c0.811-0.088,1.597-0.312,2.319-0.624C21.104,6.712,20.419,7.423,19.633,7.997z"></path></svg>
                                <span class="u-screen-reader-text">Twitter</span>
                            </a>
                        </li>
                        <li>
                            <a href="#0">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" style="fill:rgba(0, 0, 0, 1);transform:;-ms-filter:"><path d="M11.999,7.377c-2.554,0-4.623,2.07-4.623,4.623c0,2.554,2.069,4.624,4.623,4.624c2.552,0,4.623-2.07,4.623-4.624 C16.622,9.447,14.551,7.377,11.999,7.377L11.999,7.377z M11.999,15.004c-1.659,0-3.004-1.345-3.004-3.003 c0-1.659,1.345-3.003,3.004-3.003s3.002,1.344,3.002,3.003C15.001,13.659,13.658,15.004,11.999,15.004L11.999,15.004z"></path><circle cx="16.806" cy="7.207" r="1.078"></circle><path d="M20.533,6.111c-0.469-1.209-1.424-2.165-2.633-2.632c-0.699-0.263-1.438-0.404-2.186-0.42 c-0.963-0.042-1.268-0.054-3.71-0.054s-2.755,0-3.71,0.054C7.548,3.074,6.809,3.215,6.11,3.479C4.9,3.946,3.945,4.902,3.477,6.111 c-0.263,0.7-0.404,1.438-0.419,2.186c-0.043,0.962-0.056,1.267-0.056,3.71c0,2.442,0,2.753,0.056,3.71 c0.015,0.748,0.156,1.486,0.419,2.187c0.469,1.208,1.424,2.164,2.634,2.632c0.696,0.272,1.435,0.426,2.185,0.45 c0.963,0.042,1.268,0.055,3.71,0.055s2.755,0,3.71-0.055c0.747-0.015,1.486-0.157,2.186-0.419c1.209-0.469,2.164-1.424,2.633-2.633 c0.263-0.7,0.404-1.438,0.419-2.186c0.043-0.962,0.056-1.267,0.056-3.71s0-2.753-0.056-3.71C20.941,7.57,20.801,6.819,20.533,6.111z M19.315,15.643c-0.007,0.576-0.111,1.147-0.311,1.688c-0.305,0.787-0.926,1.409-1.712,1.711c-0.535,0.199-1.099,0.303-1.67,0.311 c-0.95,0.044-1.218,0.055-3.654,0.055c-2.438,0-2.687,0-3.655-0.055c-0.569-0.007-1.135-0.112-1.669-0.311 c-0.789-0.301-1.414-0.923-1.719-1.711c-0.196-0.534-0.302-1.099-0.311-1.669c-0.043-0.95-0.053-1.218-0.053-3.654 c0-2.437,0-2.686,0.053-3.655c0.007-0.576,0.111-1.146,0.311-1.687c0.305-0.789,0.93-1.41,1.719-1.712 c0.534-0.198,1.1-0.303,1.669-0.311c0.951-0.043,1.218-0.055,3.655-0.055c2.437,0,2.687,0,3.654,0.055 c0.571,0.007,1.135,0.112,1.67,0.311c0.786,0.303,1.407,0.925,1.712,1.712c0.196,0.534,0.302,1.099,0.311,1.669 c0.043,0.951,0.054,1.218,0.054,3.655c0,2.436,0,2.698-0.043,3.654H19.315z"></path></svg>
                                <span class="u-screen-reader-text">Instagram</span>
                            </a>
                        </li>
                        <li>
                            <a href="#0">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" style="fill: rgba(0, 0, 0, 1);transform: ;msFilter:;"><path d="M11.99 2C6.472 2 2 6.473 2 11.99c0 4.232 2.633 7.85 6.35 9.306-.088-.79-.166-2.006.034-2.868.182-.78 1.172-4.966 1.172-4.966s-.299-.599-.299-1.484c0-1.388.805-2.425 1.808-2.425.853 0 1.264.64 1.264 1.407 0 .858-.546 2.139-.827 3.327-.235.994.499 1.805 1.479 1.805 1.775 0 3.141-1.872 3.141-4.575 0-2.392-1.719-4.064-4.173-4.064-2.843 0-4.512 2.132-4.512 4.335 0 .858.331 1.779.744 2.28a.3.3 0 0 1 .069.286c-.076.315-.245.994-.277 1.133-.044.183-.145.222-.335.134-1.247-.581-2.027-2.405-2.027-3.871 0-3.151 2.289-6.045 6.601-6.045 3.466 0 6.159 2.469 6.159 5.77 0 3.444-2.171 6.213-5.184 6.213-1.013 0-1.964-.525-2.29-1.146l-.623 2.374c-.225.868-.834 1.956-1.241 2.62a10 10 0 0 0 2.958.445c5.517 0 9.99-4.473 9.99-9.99S17.507 2 11.99 2"></path></svg>
                                <span class="u-screen-reader-text">Pinterest</span>
                            </a>
                        </li>
                    </ul>
                </div>
                <div class="column lg-5 md-6 tab-12">
                    <div class="ss-copyright">
                        <span>© Copyright Spurgeon 2021</span> 
                        <span>Design by <a href="https://www.styleshout.com/">StyleShout</a></span>
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
<script src="<?php echo $this->theme_url; ?>/js/main.js"></script>
<script src="https://hub.utsukta.org/view/theme/spurgeon/js/main.js"></script>
<script src="https://hub.utsukta.org/view/theme/spurgeon/js/plugins.js"></script>
</body>
</html>

