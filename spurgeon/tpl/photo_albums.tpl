<div id="side-bar-photos-albums" class="mb-3">
	<div class="h4">{{$title}}</div>
	<ul class="flex-column" style="list-style: none;">
		<li class="h6 m-0"><a class="" href="{{$baseurl}}/photos/{{$nick}}" title="{{$title}}" >{{$recent}}</a></li>
		{{if $albums}}
		{{foreach $albums as $al}}
		{{if $al.shorttext}}
		<li class="h6 m-0"><a class="" href="{{$baseurl}}/photos/{{$nick}}/album/{{$al.bin2hex}}"><span class="badge bg--primary float-end">{{$al.total}}</span>{{$al.shorttext}}</a></li>
		{{/if}}
		{{/foreach}}
		{{/if}}
	</ul>
</div>
