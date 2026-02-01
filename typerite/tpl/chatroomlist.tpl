<div id="chatroom_list">
  <div class="h3">{{$header}}</div>
	<ul class="flex-column list-unstyled">
		<li class="nav-item"><a class="" href="{{$baseurl}}/chat/{{$nickname}}">{{$overview}}</a></li>
		{{foreach $items as $item}}
		<li class="nav-item"><a class="" href="{{$baseurl}}/chat/{{$nickname}}/{{$item.cr_id}}"><span class="badge bg-secondary float-end">{{$item.cr_inroom}}</span>{{$item.cr_name}}</a></li>
		{{/foreach}}
	</ul>
</div>

