<div class="m-1"><div class="float-start me-3">
	<a href="{{$href}}" title="{{$link_label}}" target="_blank">
		<img src="{{$img_src}}" class="rounded-circle shadow" style="width: 3rem; height: 3rem;" />
	</a>
</div>
<a href="{{$href}}">
	<div class="text-truncate h5 m-0"><strong>{{if $is_group}}<i class="bi bi-chat-quote" title="{{$group_label}}"></i> {{/if}}{{$name}}</strong></div>
  <div class="text-truncate text-muted">{{$addr}}</div></a>
</div>
