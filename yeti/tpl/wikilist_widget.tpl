<div id="wiki_list" class="card mb-3">
  <div class="card-header">
	<h3 class="card-title">{{$header}}</h3>
  </div>
  <div class="card-body">
	<ul class="nav nav-pills flex-column">
		{{foreach $wikis as $wiki}}
		<li class="nav-item"><a class="nav-link" href="/wiki/{{$channel}}/{{$wiki.urlName}}/Home" title="{{$view}}">{{$wiki.title}}</a></li> 
		{{/foreach}}
	</ul>
  </div>
</div>
