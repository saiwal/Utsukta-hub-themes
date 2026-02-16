<div class="hero">

    <div class="hero__slider swiper-container swiper-container-fade" id="{{$swiper_id}}">

        <div class="swiper-wrapper">
            {{foreach $items as $item}}
            <article class="hero__slide swiper-slide">
                <div class="hero__entry-image" style="background-image: url('{{$item.image}}');"></div>
                <div class="hero__entry-text">
                    <div class="hero__entry-text-inner">
                        {{if $item.categories}}
                        <div class="hero__entry-meta">
                            <span class="cat-links">
                                {{foreach $item.categories as $cat}}
                                <a href="{{$cat.link}}">{{$cat.name}}</a>
                                {{/foreach}}
                            </span>
                        </div>
                        {{/if}}
                        
                        <h2 class="hero__entry-title">
                            <a href="{{$item.link}}">
                                {{$item.title}}
                            </a>
                        </h2>
                        
                        {{if $item.excerpt}}
                        <p class="hero__entry-desc">
                            {{$item.excerpt}}
                        </p>
                        {{/if}}
                        
                        <a class="hero__more-link" href="{{$item.link}}">{{$read_more}}</a>
                    </div>
                </div>
            </article>
            {{/foreach}}
        </div>

        <div class="swiper-pagination swiper-pagination-clickable swiper-pagination-bullets"></div>

    </div>

    <a href="#bricks" class="hero__scroll-down smoothscroll">
        <svg width="24" height="24" fill="none" viewBox="0 0 24 24">
            <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M10.25 6.75L4.75 12L10.25 17.25"></path>
            <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19.25 12H5"></path>
        </svg>
        <span>{{$scroll_text}}</span>
    </a>

</div>
<style>
.masonry {
}
.s-content{
	padding-top: 0;
}
.s-header__nav-wrap{ 
  margin-left: 50%;
} 
.s-header__branding a{
  color: white;
}
.bricks {  
	padding-top: var(--vspace-3);
}
</style>
