<header class="s-header">

        <div class="row">

            <div class="s-header__content column">
                <h1 class="s-header__logotext">
                    <a href="/" title="">{{$banner}}</a>
                </h1>
                <!--<p class="s-header__tagline">Put your awesome tagline here.</p>-->
            </div>

        </div> <!-- end row -->

       <nav class="s-header__nav-wrap">

           <div class="row">

                <ul class="s-header__nav">                    <!-- Pinned user apps -->
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
                </ul> <!-- end #nav -->

           </div> 

        </nav> <!-- end #nav-wrap -->

        <a class="header-menu-toggle" href="#0" title="Menu"><span>Menu</span></a>

    </header> <!-- Header End -->

