	<div class="widget widget_tags">
    <div class="h6">{{$title}} </div>
    <div class="tagcloud group">
      {{foreach $tags as $tag}}
		    <a href="{{$baseurl}}{{$tag.0}}">{{$tag.0}}</a>
      {{/foreach}}
		</div>
    </div>


