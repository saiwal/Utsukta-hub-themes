        <!-- site header
        ================================================== -->
        <header class="s-header header">

            <div class="header__top">
                <div class="header__logo">
                    <a class="site-logo" href="/">
                    {{$banner}}
                    </a>
                </div>
            </div>

            <nav class="header__nav-wrap">

                <ul class="header__nav">
                    <!-- Pinned user apps -->
                      {{if $navbar_apps.0}}
                      {{foreach $navbar_apps as $navbar_app}}
                        {{$navbar_app}}
                      {{/foreach}}
                      {{/if}}
                      <!-- Channel apps; needs fixing -->
                      {{if $channel_apps.0}}
                      {{foreach $channel_apps as $channel_app}}
                      {{$channel_app}}
                      {{/foreach}}
                      {{/if}}
                </ul> <!-- end header__nav -->

                <ul class="header__social">
                    <li class="ss-facebook">
                        <a href="https://facebook.com/">
                            <span class="screen-reader-text">Facebook</span>
                        </a>
                    </li>
                    <li class="ss-twitter">
                        <a href="#0">
                            <span class="screen-reader-text">Twitter</span>
                        </a>
                    </li>
                    <li class="ss-dribbble">
                        <a href="#0">
                            <span class="screen-reader-text">Instagram</span>
                        </a>
                    </li>
                    <li class="ss-behance">
                        <a href="#0">
                            <span class="screen-reader-text">Behance</span>
                        </a>
                    </li>
                </ul>

            </nav> <!-- end header__nav-wrap -->

            <!-- menu toggle -->
            <a href="#0" class="header__menu-toggle">
                <span>Menu</span>
            </a>

        </header> <!-- end s-header -->


