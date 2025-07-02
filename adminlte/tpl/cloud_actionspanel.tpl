<input id="invisible-cloud-file-upload" type="file" name="files" style="visibility:hidden;position:absolute;top:-50;left:-50;width:0;height:0;" multiple>
<div id="files-mkdir-tools">
	<div class="section-content-tools-wrapper">
		<label for="files-mkdir">{{$folder_header}}</label>
		<form id="mkdir-form" method="post" action="file_upload" class="acl-form" data-form_id="mkdir-form" data-allow_cid='{{$allow_cid}}' data-allow_gid='{{$allow_gid}}' data-deny_cid='{{$deny_cid}}' data-deny_gid='{{$deny_gid}}'>
			<input type="hidden" name="folder" value="{{$folder}}" />
			<input type="hidden" name="channick" value="{{$channick}}" />
			<input type="hidden" name="return_url" value="{{$return_url}}" />
			<input id="files-mkdir" type="text" name="filename" class="form-control mb-3">
			<div class="float-end btn-group">
				<div class="btn-group">
					{{if $lockstate}}
					<button class="btn btn-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#aclModal" type="button">
						<i class="jot-perms-icon bi bi-{{$lockstate}}"></i>
					</button>
					{{/if}}
					<button class="btn btn-primary btn-sm float-end" type="submit" value="{{$folder_submit}}">{{$folder_submit}}</button>
				</div>
			</div>
		</form>
		<div class="clear"></div>
	</div>
	<hr class="m-0">
</div>
<div id="files-upload-tools">
	<div class="section-content-tools-wrapper ">
		{{if $quota.limit || $quota.used}}<div class="callout {{if $quota.warning}}callout-warning section-content-danger-wrapper{{else}}callout-info section-content-info-wrapper{{/if}} mb-2">{{if $quota.warning}}<strong>{{$quota.warning}} </strong>{{/if}}{{if $quota.desc}}{{$quota.desc}}{{/if}}</div>{{/if}}
		<form id="ajax-upload-files" method="post" action="#" enctype="multipart/form-data" class="acl-form" data-form_id="ajax-upload-files" data-allow_cid='{{$allow_cid}}' data-allow_gid='{{$allow_gid}}' data-deny_cid='{{$deny_cid}}' data-deny_gid='{{$deny_gid}}'>
			<input id="file-folder"type="hidden" name="folder" value="{{$folder}}" />
			<input type="hidden" name="channick" value="{{$channick}}" />
			<input type="hidden" name="return_url" value="{{$return_url}}" />
			{{include file="field_checkbox.tpl" field=$notify}}
			<div class="cloud-index attach-drop attach-drop-zone text-center p-4 mb-3" data-folder="{{$folder}}">
				<span class="text-muted">{{$drop_area_label}}</span>
			</div>
			<div class="float-end btn-group">
				<div class="btn-group">
					{{if $lockstate}}
					<button class="btn btn-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#aclModal" type="button">
						<i class="jot-perms-icon bi bi-{{$lockstate}}"></i>
					</button>
					{{/if}}
					<button id="upload-submit" class="btn btn-primary btn-sm float-end">{{$upload_submit}}</button>
				</div>
			</div>
		</form>
		<div class="clear"></div>
	</div>
	<hr class="m-0">
</div>
{{if $aclselect}}
{{$aclselect}}
{{/if}}
{{if $breadcrumbs_html}}
{{$breadcrumbs_html}}
<hr class="m-0">
{{/if}}
