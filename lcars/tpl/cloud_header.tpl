<div class="section-title-wrapper app-content-header clearfix">
	<div class="lcars-text-bar"><span>{{$header}}</span></div>
	<div class="buttons flush the-end">
		<button href="cloud_tiles/{{$cpath}}" class="btn btn-sm btn-outline-secondary"><i class="bi {{if $tiles}}bi-list{{else}}bi-grid{{/if}}"></i></button>
		{{if $actionspanel}}
		{{if $is_owner}}
		<button href="/sharedwithme" class="btn btn-sm btn-outline-secondary"><i class="bi bi-download"></i>&nbsp;{{$shared}}</button>
		{{/if}}
		<button id="files-create-btn" class="btn btn-sm btn-primary" onclick="openClose('files-mkdir-tools'); closeMenu('files-upload-tools');"><i class="bi bi-folder"></i>&nbsp;{{$create}}</button>
		<button id="files-upload-btn" class="btn btn-sm btn-success" onclick="openClose('files-upload-tools'); closeMenu('files-mkdir-tools');"><i class="bi bi-plus-lg"></i>&nbsp;{{$upload}}</button>
		{{/if}}
	</div>

</div>
{{if $actionspanel}}
	{{$actionspanel}}
{{/if}}
