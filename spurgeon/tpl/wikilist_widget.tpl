<div id="wiki_list">
	<div class="h5">{{$header}}</div>
	<ul class="list-unstyled">
		{{foreach $wikis as $wiki}}
		<li><a href="/wiki/{{$channel}}/{{$wiki.urlName}}/Home" title="{{$view}}">{{$wiki.title}}</a></li> 
		{{/foreach}}
	</ul>
</div>
