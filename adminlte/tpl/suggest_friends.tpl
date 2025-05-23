<li class="list-group-item d-flex align-items-center">
	<a href="{{$entry.url}}">
    <img src="{{$entry.photo}}" alt="{{$entry.name}}" class="rounded-circle me-3 img-size-64 img-thumbnail">
	</a>
  <div class="d-flex flex-column">
    <h6 class="mb-1">
		<a href="{{$entry.url}}" title="{{$entry.name}}">{{$entry.name}}</a>
    </h6>
    <div class="d-flex gap-2">
	{{if $entry.connlnk}}
      <a type="button" class="btn btn-sm btn-outline-success" title="{{$entry.conntxt}}" href="{{$entry.connlnk}}">{{$entry.conntxt}}</a>
	{{/if}}
      <a type="button" class="btn btn-sm btn-outline-warning" href="{{$entry.ignlnk}}" title="{{$entry.ignore}}" onclick="return confirmDelete();"><i class="bi bi-x-lg"></i></a>
    </div>
  </div>
</li>
