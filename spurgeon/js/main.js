/* ===================================================================
 * Spurgeon 1.0.0 - Main JS
 *
 * ------------------------------------------------------------------- */

(function(html) {

    'use strict';


   /* preloader
    * -------------------------------------------------- */
    const ssPreloader = function() {

        const siteBody = document.querySelector('body');
        const preloader = document.querySelector('#preloader');
        if (!preloader) return;

        html.classList.add('ss-preload');
        
        window.addEventListener('load', function() {
            html.classList.remove('ss-preload');
            html.classList.add('ss-loaded');

            preloader.addEventListener('transitionend', function afterTransition(e) {
                if (e.target.matches('#preloader'))  {
                    // siteBody.classList.add('ss-show');
                    e.target.style.display = 'none';
                    preloader.removeEventListener(e.type, afterTransition);
                }
            });
        });

    }; // end ssPreloader


   /* mobile menu
    * ---------------------------------------------------- */ 
    const ssMobileMenu = function() {

        const toggleButton = document.querySelector('.s-header__menu-toggle');
        const mainNavWrap = document.querySelector('.s-header__nav-wrap');
        const mainNav = document.querySelector('.s-header__nav');
        const parentMenus = mainNav.querySelectorAll('.has-children');
        const siteBody = document.querySelector('body');

        if (!(toggleButton && mainNavWrap)) return;

        toggleButton.addEventListener('click', function(e) {
            e.preventDefault();
            toggleButton.classList.toggle('is-clicked');
            siteBody.classList.toggle('menu-is-open');

            scrollLock.getScrollState() ? scrollLock.disablePageScroll(mainNavWrap) : scrollLock.enablePageScroll(mainNavWrap);
        });

        // open (or close) submenu items in mobile view menu. 
        // close all the other open submenu items.
        mainNav.addEventListener('click', function(e) {

            //check if the right element clicked
            if (!e.target.closest('.has-children')) return;
            else {

                //check if element contains active class
                if (!e.target.closest('.has-children').classList.contains('sub-menu-is-open')) {

                    parentMenus.forEach(function(current) {
                        current.classList.remove('sub-menu-is-open');
                    });

                    // add is-active class on cliked accordion
                    e.target.closest('.has-children').classList.add('sub-menu-is-open');

                } else {

                    // remove is-active class on cliked accordion
                    e.target.closest('.has-children').classList.remove('sub-menu-is-open');
                }
            }
        });

        window.addEventListener('resize', function() {

            // above 1200px
            if (window.matchMedia('(min-width: 1201px)').matches) {
                if (siteBody.classList.contains('menu-is-open')) siteBody.classList.remove('menu-is-open');
                if (toggleButton.classList.contains('is-clicked')) toggleButton.classList.remove('is-clicked');
                if (!scrollLock.getScrollState()) scrollLock.enablePageScroll();

                parentMenus.forEach(function(current) {
                    current.classList.remove('sub-menu-is-open');
                });
            }
        });

    }; // end ssMobileMenu


    /* search
    * ------------------------------------------------------ */
    const ssSearch = function() {

        const searchWrap = document.querySelector('.s-header__search');
        const searchTrigger = document.querySelector('.s-header__search-trigger');

        if (!(searchWrap && searchTrigger)) return;

        const searchField = searchWrap.querySelector('.s-header__search-field');
        const closeSearch = searchWrap.querySelector('.s-header__search-close');
        const siteBody = document.querySelector('body');

        searchTrigger.addEventListener('click', function(e) {

            e.preventDefault();
            e.stopPropagation();
            siteBody.classList.add('search-is-visible');

            scrollLock.getScrollState() ? scrollLock.disablePageScroll(searchWrap) : scrollLock.enablePageScroll(searchWrap);

            setTimeout(function(){
                searchWrap.querySelector('.s-header__search-field').focus();
            }, 100);
        });

        closeSearch.addEventListener('click', function(e) {

            e.stopPropagation();

            if(siteBody.classList.contains('search-is-visible')) {

                siteBody.classList.remove('search-is-visible');
                setTimeout(function(){
                    searchWrap.querySelector('.s-header__search-field').blur();
                }, 100);

                scrollLock.getScrollState() ? scrollLock.disablePageScroll(searchWrap) : scrollLock.enablePageScroll(searchWrap);
            }
        });

        searchWrap.addEventListener('click', function(e) {
            if( !(e.target.matches('.s-header__search-inner')) ) {
                closeSearch.dispatchEvent(new Event('click'));
            }
        });

        searchField.addEventListener('click', function(e) {
            e.stopPropagation();
        })

        searchField.setAttribute('placeholder', 'Search for...');
        searchField.setAttribute('autocomplete', 'off');

    }; // end ssSearch


    /* masonry
    * ------------------------------------------------------ */
    const ssMasonry = function() {

        const containerBricks = document.querySelector('.bricks-wrapper');
        if (!containerBricks) return;

        imagesLoaded(containerBricks, function() {

            const msnry = new Masonry(containerBricks, {
                itemSelector: '.entry',
                columnWidth: '.grid-sizer',
                percentPosition: true,
                resize: true
            });

        });

    }; // end ssMasonry


   /* animate masonry elements if in viewport
    * ------------------------------------------------------ */
    const ssAnimateBricks = function() {

        const animateBlocks = document.querySelectorAll('[data-animate-block]');
        const pageWrap = document.querySelector('.s-pagewrap');
        if (!(pageWrap && animateBlocks)) return;

        // on homepage do animate on scroll
        if (pageWrap.classList.contains('ss-home')) {
            window.addEventListener('scroll', animateOnScroll);
        }
        // animate on load
        else {
            window.addEventListener('load', function(){
                doAnimate(animateBlocks[0]);
            });
        }

        // do animate
        function doAnimate(current) {
            const els = current.querySelectorAll('[data-animate-el]');
            const p = new Promise(function(resolve, reject) {

                els.forEach(function(el, index, array) {
                    const dly = index * 200;

                    el.style.setProperty('--transition-delay', dly + 'ms');
                    if (index === array.length -1) resolve();
                });

            });
            
            p.then(function() {
                current.classList.add('ss-animated');
            });
        }

        // animate on scroll 
        function animateOnScroll() {

            let scrollY = window.pageYOffset;

            animateBlocks.forEach(function(current) {

                const viewportHeight = window.innerHeight;
                const triggerTop = (current.offsetTop + (viewportHeight * .1)) - viewportHeight;
                const blockHeight = current.offsetHeight;
                const blockSpace = triggerTop + blockHeight;
                const inView = scrollY > triggerTop && scrollY <= blockSpace;
                const isAnimated = current.classList.contains('ss-animated');

                if (inView && (!isAnimated)) {
                    doAnimate(current);
                }

            });
        }

    }; // end ssAnimateOnScroll


   /* swiper
    * ------------------------------------------------------ */ 
    const ssSwiper = function() {

        const mySwiper = new Swiper('.swiper-container', {

            slidesPerView: 1,
            effect: 'fade',
            speed: 1000,
            pagination: {
                el: '.swiper-pagination',
                clickable: true, 
                renderBullet: function (index, className) {
                    return '<span class="' + className + '">' + (index + 1) + '</span>';
                }
            }

        });

    }; // end ssSwiper


   /* alert boxes
    * ------------------------------------------------------ */
    const ssAlertBoxes = function() {

        const boxes = document.querySelectorAll('.alert-box');
  
        boxes.forEach(function(box){

            box.addEventListener('click', function(event) {
                if (event.target.matches('.alert-box__close')) {
                    event.stopPropagation();
                    event.target.parentElement.classList.add('hideit');

                    setTimeout(function(){
                        box.style.display = 'none';
                    }, 500)
                }
            });
        })

    }; // end ssAlertBoxes


    /* Back to Top
    * ------------------------------------------------------ */
    const ssBackToTop = function() {

        const pxShow = 900;
        const goTopButton = document.querySelector(".ss-go-top");

        if (!goTopButton) return;

        // Show or hide the button
        if (window.scrollY >= pxShow) goTopButton.classList.add("link-is-visible");

        window.addEventListener('scroll', function() {
            if (window.scrollY >= pxShow) {
                if(!goTopButton.classList.contains('link-is-visible')) goTopButton.classList.add("link-is-visible")
            } else {
                goTopButton.classList.remove("link-is-visible")
            }
        });

    }; // end ssBackToTop


   /* smoothscroll
    * ------------------------------------------------------ */
    const ssMoveTo = function(){

        const easeFunctions = {
            easeInQuad: function (t, b, c, d) {
                t /= d;
                return c * t * t + b;
            },
            easeOutQuad: function (t, b, c, d) {
                t /= d;
                return -c * t* (t - 2) + b;
            },
            easeInOutQuad: function (t, b, c, d) {
                t /= d/2;
                if (t < 1) return c/2*t*t + b;
                t--;
                return -c/2 * (t*(t-2) - 1) + b;
            },
            easeInOutCubic: function (t, b, c, d) {
                t /= d/2;
                if (t < 1) return c/2*t*t*t + b;
                t -= 2;
                return c/2*(t*t*t + 2) + b;
            }
        }

        const triggers = document.querySelectorAll('.smoothscroll');
        
        const moveTo = new MoveTo({
            tolerance: 0,
            duration: 1200,
            easing: 'easeInOutCubic',
            container: window
        }, easeFunctions);

        triggers.forEach(function(trigger) {
            moveTo.registerTrigger(trigger);
        });

    }; // end ssMoveTo


   /* Initialize
    * ------------------------------------------------------ */
    (function ssInit() {

        ssPreloader();
        ssMobileMenu();
        ssSearch();
        ssMasonry();
        ssAnimateBricks();
        ssSwiper();
        ssAlertBoxes();
        ssBackToTop();
        ssMoveTo();

    })();

})(document.documentElement);