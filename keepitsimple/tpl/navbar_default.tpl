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


