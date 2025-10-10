<div id="wiki_list" class="mb-3">
	<div class="h4">{{$header}}</div>
	<ul class="nav nav-pills flex-column">
		{{foreach $wikis as $wiki}}
		<li class="nav-item"><a class="nav-link" href="/wiki/{{$channel}}/{{$wiki.urlName}}/Home" title="{{$view}}">{{$wiki.title}}</a></li> 
		{{/foreach}}
	</ul>
</div>
