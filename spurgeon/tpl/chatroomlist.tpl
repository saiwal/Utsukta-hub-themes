<div id="chatroom_list">
  <div class="h5">{{$header}}</div>
	<ul class="list-unstyled">
		<li><a href="{{$baseurl}}/chat/{{$nickname}}">{{$overview}}</a></li>
		{{foreach $items as $item}}
		<li><a href="{{$baseurl}}/chat/{{$nickname}}/{{$item.cr_id}}"><span class="badge bg-secondary float-end">{{$item.cr_inroom}}</span>{{$item.cr_name}}</a></li>
		{{/foreach}}
	</ul>
</div>

