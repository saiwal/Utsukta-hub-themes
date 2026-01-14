<div class="generic-content-wrapper">
	<div class="section-title-wrapper app-content-header">
		<div class="float-end">
			{{if $cancel}}
			<button id="dbtn-cancel" class="btn btn-warning btn-sm" onclick="itemCancel(); return false;">{{$cancel}}</button>
			{{/if}}
			{{if $delete}}
			<a  href="item/drop/{{$id}}" id="delete-btn" class="btn btn-sm btn-danger" onclick="return confirmDelete();"><i class="bi bi-trash"></i>&nbsp;{{$delete}}</a>
			{{/if}}
		</div>
		<h3>{{$title}}</h3>
		<div class="clear"></div>
	</div>
	<div id="webpage-editor" class="section-content-tools-wrapper rounded-bottom">
		{{$editor}}
	</div>
</div>
