	<div class="widget widget_tags">
    <h3 class="h6">{{$title}} </h3>
    <div class="tagcloud group">
      {{foreach $tags as $tag}}
		    <a href="{{$baseurl}}{{$tag.0}}">{{$tag.0}}</a>
      {{/foreach}}
		</div>
    </div>


