<div id="chatroom_list" class="mb-3">
  <div class="h4 mt-0">
  {{$header}}
	<ul class="nav nav-pills flex-column">
		<li class="nav-item"><a class="nav-link" href="{{$baseurl}}/chat/{{$nickname}}">{{$overview}}</a></li>
		{{foreach $items as $item}}
		<li class="nav-item"><a class="nav-link" href="{{$baseurl}}/chat/{{$nickname}}/{{$item.cr_id}}"><span class="badge bg-secondary float-end">{{$item.cr_inroom}}</span>{{$item.cr_name}}</a></li>
		{{/foreach}}
	</ul>
</div>

