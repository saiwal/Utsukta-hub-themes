<a class="col text-center list-group-item{{if $class}} {{$class}}{{/if}} fakelink" href="{{if $click}}#{{else}}{{$url}}{{/if}}" {{if $click}}onclick="{{$click}}"{{/if}}>
	<img class="img-fluid img-size-48 m-1" src="{{$photo}}" title="{{$title}}" alt="" loading="lazy" />{{if $perminfo}}{{include "connstatus.tpl"}}{{/if}}
  <span class="d-block text-truncate">{{$name}}</span>
</a>
