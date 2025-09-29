<div id="chatroom_list" class="card mb-3">
  <div class="card-header">
  {{$header}}
  <div class="card-tools">
      <button type="button" class="btn btn-sm btn-tool" data-lte-toggle="card-collapse">
        <i data-lte-icon="expand" class="bi bi-plus-lg"></i>
        <i data-lte-icon="collapse" class="bi bi-dash-lg"></i>
      </button>
    </div>
  </div>
  <div class="card-body">
	<ul class="nav nav-pills flex-column">
		<li class="nav-item"><a class="nav-link" href="{{$baseurl}}/chat/{{$nickname}}">{{$overview}}</a></li>
		{{foreach $items as $item}}
		<li class="nav-item"><a class="nav-link" href="{{$baseurl}}/chat/{{$nickname}}/{{$item.cr_id}}"><span class="badge bg-secondary float-end">{{$item.cr_inroom}}</span>{{$item.cr_name}}</a></li>
		{{/foreach}}
	</ul>
  </div>
</div>

