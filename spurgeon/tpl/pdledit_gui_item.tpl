<div class="pdledit_gui_item card mb-3" data-src="{{$entry.src}}">
	<div class="card-header d-flex justify-content-between">
		<span class="text-uppercase">{{$entry.name}}</span>
		<div class="badge rounded-pill{{if $entry.type === 'widget'}} bg-info text-dark{{/if}}{{if $entry.type === 'content'}} bg-primary{{/if}}{{if $entry.type === 'menu'}} bg-secondary{{/if}}{{if $entry.type === 'block'}} bg-warning text-dark{{/if}}">
			{{$entry.type}}
		</div>
	</div>
	<div class="card-body">
		{{if $entry.desc}}
		<div class="mb-3 text-muted">{{$entry.desc}}</div>
		{{/if}}
		{{if $entry.type !== 'content'}}
		<button type="button" class="btn btn-sm btn-primary pdledit_gui_item_src{{if $disable_controls}} disabled{{/if}}">Edit</button>
		<button type="button" class="btn btn-sm btn-danger pdledit_gui_item_remove{{if $disable_controls}} disabled{{/if}}">Remove</button>
		<i class="bi bi-arrows-move m-2 float-end cursor-pointer pdledit_gui_item_handle"></i>
		{{/if}}
	</div>
</div>
