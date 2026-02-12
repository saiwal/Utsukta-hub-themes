<div class="section-content-wrapper-np">
	{{if $tiles}}
	<table id="cloud-index" class="table table-hover">
		<tr id="new-upload-progress-bar-0"></tr> {{* this is needed to append the upload files in the right order *}}
	</table>
	<div class="row row-cols-2 row-cols-md-4">
		{{if $parentpath}}
		<div class="col mb-4">
			<div class="card h-100">
				<a href="{{$parentpath}}" class="text-decoration-none">
					<div class="d-flex align-items-center justify-content-center m-1" style="height: 4.5rem;">
						<i class="bi bi-arrow-90deg-up" style="font-size: 4rem"></i>
					</div>
					<div class="card-footer text-center">
						<small class="text-muted text-truncate">..</small>
					</div>
				</a>
			</div>
		</div>
		{{/if}}
		{{foreach $entries as $item}}
		<div class="col mb-4">
			<div class="card h-100">
				<a href="{{$item.rel_path}}" title="{{$item.name}}" class="text-decoration-none stretched-link file_link"  data-id="{{$item.attach_id}}" data-type="{{$item.type}}">
					<div class="d-flex align-items-center justify-content-center m-1" style="height: 4.5rem;">
						{{if $item.photo_icon}}
						<img src="{{$item.photo_icon}}" class="rounded" alt="{{$item.photo_icon}}" title="{{$item.size_formatted}}" style="max-height: 4rem; width: auto; max-width: 100%;">
						{{else}}
						<i class="bi {{$item.icon_from_type}}" title="{{$item.size_formatted}}" style="font-size: 4rem"></i>
						{{/if}}
					</div>
					<div class="card-footer text-truncate text-center">
						<small class="text-muted">{{$item.name}}</small>
					</div>
				</a>
			</div>
		</div>
		{{/foreach}}
	</div>
	{{else}}
    <div class="card mb-3">
      <div class="card-body">
	<table id="cloud-index" class="table table-hover">
    <thead>
		<tr>
			<th width="1%">{{* multi tool checkbox *}}</th>
			<th width="1%">{{* icon *}}</th>
			<th width="93%">{{$name}}</th>
			<th width="1%">{{* categories *}}</th>
			<th width="1%">{{* lock icon *}}</th>
			<th width="1%">{{* tools icon *}}</th>
			<th width="1%" class="d-none d-md-table-cell">{{$size}}</th>
			<th width="1%" class="d-none d-md-table-cell">{{$lastmod}}</th>
		</tr>
    </thead>
		{{if $parentpath}}
		<tr id="cloud-index-up" class="cloud-index{{if ! $is_root_folder}} attach-drop{{/if}}"{{if ! $is_root_folder}} data-folder="{{$folder_parent}}"/{{/if}}>
			<td></td>
			<td><i class="bi bi-arrow-90deg-up"></i></td>
			<td colspan="6"><a href="{{$parentpath}}" title="{{$parent}}" class="p-2" draggable="false">..</a></td>
		</tr>
		{{/if}}
		{{if $channel_id && $is_owner && $entries.0}}
		<tr id="cloud-multi-actions">
			<td colspan="2">
				<div class="form-check form-check-inline">
					<input class="form-check-input" type="checkbox" id="cloud-multi-tool-select-all" value="" title="{{$select_all_label}}">
				</div>
			</td>
			<td colspan="3">
				<div class="form-check form-check-inline">
					<label class="form-check-label" for="cloud-multi-tool-select-all">{{$select_all_label}}</label>
				</div>
			</td>
			<td colspan="3">
				{{if $is_owner}}
				<div class="dropdown">
					<button class="btn btn-warning btn-sm" id="multi-dropdown-button" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
						<i class="bi fa-ellipsis-v d-table-cell"></i><span class="d-none d-md-table-cell">{{$bulk_actions_label}}</span>
					</button>
					<div class="dropdown-menu dropdown-menu-end" aria-labelledby="dropdown-button">
						{{if $is_owner}}
						<a id="cloud-multi-tool-perms-btn" class="dropdown-item" href="#"><i class="bi bi-lock"></i> {{$adjust_permissions_label}}</a>
						{{/if}}
						<a id="cloud-multi-tool-move-btn" class="dropdown-item" href="#"><i class="bi fa-copy"></i> {{$move_copy_label}}</a>
						<a id="cloud-multi-tool-categories-btn" class="dropdown-item" href="#"><i class="bi fa-asterisk"></i> {{$categories_label}}</a>
						<a id="cloud-multi-tool-download-btn" class="dropdown-item" href="#"><i class="bi bi-download"></i> {{$download_label}}</a>
						<a id="cloud-multi-tool-delete-btn" class="dropdown-item" href="#"><i class="bi bi-trash"></i> {{$delete_label}}</a>
					</div>
				</div>
				{{else if $is_admin}}
				<div class="dropdown">
					<button class="btn btn-warning btn-sm" id="multi-dropdown-button" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
						<i class="bi fa-ellipsis-v d-table-cell"></i><span class="d-none d-md-table-cell">{{$bulk_actions_label}}</span>
					</button>
					<div class="dropdown-menu dropdown-menu-end" aria-labelledby="dropdown-button">
						<a id="cloud-multi-tool-delete-btn" class="dropdown-item" href="#"><i class="bi bi-trash"></i> {{$admin_delete_label}}</a>
					</div>
				</div>
				{{/if}}
			</td>
		</tr>
		<tr id="cloud-multi-tools">
			<td id="attach-multi-edit-panel" colspan="8">
				<form id="attach_multi_edit_form" action="attach_edit" method="post" class="acl-form" data-form_id="attach_multi_edit_form" data-allow_cid='{{$allow_cid}}' data-allow_gid='{{$allow_gid}}' data-deny_cid='{{$deny_cid}}' data-deny_gid='{{$deny_gid}}'>
					<input type="hidden" name="channel_id" value="{{$channel_id}}" />
					<input id="multi-perms" type="hidden" name="permissions" value="0">
					<input type="hidden" name="return_path" value="{{$return_path}}">
					<div id="cloud-multi-tool-move" class="cloud-multi-tool">
						{{include file="field_select.tpl" field=$newfolder}}
						{{include file="field_checkbox.tpl" field=$copy}}
					</div>
					<div id="cloud-multi-tool-categories" class="cloud-multi-tool">
						{{include file="field_input.tpl" field=$categories}}
					</div>
					<div id="cloud-multi-tool-submit" class="cloud-multi-tool">
						{{if $is_owner}}
						{{include file="field_checkbox.tpl" field=$recurse}}
						{{/if}}
						<div id="attach-multi-submit" class="mb-3">
							<button id="cloud-multi-tool-cancel-btn" class="btn btn-secondary btn-sm cloud-multi-tool-cancel-btn" type="button">
									{{$cancel_label}}
							</button>
							<div id="attach-multi-edit-perms" class="btn-group float-end">
								{{if $is_owner}}
								<button id="multi-dbtn-acl" class="btn btn-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#aclModal" title="{{$permset}}" type="button">
									<i id="multi-jot-perms-icon" class="bi bi-{{$lockstate}} jot-icons jot-perms-icon"></i>
								</button>
								{{/if}}
								<button id="multi-dbtn-submit" class="btn btn-primary btn-sm" type="submit" name="submit">
									{{$submit_label}}
								</button>
							</div>
						</div>
					</div>
				</form>
			</td>
		</tr>
		{{/if}}
		<tr id="new-upload-progress-bar-0"></tr> {{* this is needed to append the upload files in the right order *}}
		{{foreach $entries as $item}}
		<tr id="cloud-index-{{$item.attach_id}}" class="cloud-index{{if $item.collection}} attach-drop{{/if}}"{{if $item.collection}} data-folder="{{$item.resource}}"{{/if}} data-id="{{$item.attach_id}}" draggable="true">
			<td>
				{{if $channel_id && $is_owner}}
				<div class="form-check form-check-inline">
					<input class="form-check-input cloud-multi-tool-checkbox" type="checkbox" id="cloud-multi-tool-checkbox-{{$item.attach_id}}" name="attach_ids[]" value="{{$item.attach_id}}">
				</div>
				{{/if}}
			</td>
			<td><i class="bi {{$item.icon_from_type}} generic-icons" title="{{$item.type}}"></i></td>
			<td><a href="{{$item.rel_path}}" class="p-2 file_link" data-id="{{$item.attach_id}}" data-type="{{$item.type}}" draggable="false">{{$item.name}}</a></td>
			<td>{{$item.terms}}</td>
			<td class="cloud-index-tool p-2">
				{{if $item.lockstate == 'lock'}}
				<i class="bi bi-lock lockview" data-bs-toggle="dropdown" onclick="lockview('attach',{{$item.attach_id}});"></i>
				<ul id="panel-{{$item.attach_id}}" class="lockview-panel dropdown-menu dropdown-menu-end"></ul>
				{{/if}}
			</td>
			<td class="cloud-index-tool">
				{{if ($is_owner || ($item.is_creator && $has_perms)) && $item.attach_id}}
				<div class="dropdown">
					<button class="btn btn-link btn-sm" id="dropdown-button-{{$item.attach_id}}" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
						<i class="bi bi-three-dots-vertical"></i>
					</button>
					<div class="dropdown-menu dropdown-menu-end" aria-labelledby="dropdown-button-{{$item.attach_id}}">
						<a id="cloud-tool-info-btn-{{$item.attach_id}}" class="dropdown-item cloud-tool-info-btn" href="#" data-id="{{$item.attach_id}}"><i class="bi bi-info-lg generic-icons-nav"></i>{{$info_label}}</a>
						{{if $is_owner}}
						<a id="cloud-tool-perms-btn-{{$item.attach_id}}" class="dropdown-item cloud-tool-perms-btn" href="#" data-id="{{$item.attach_id}}"><i class="bi bi-{{$item.lockstate}} generic-icons-nav"></i>{{$adjust_permissions_label}}</a>
						{{/if}}
						<a id="cloud-tool-rename-btn-{{$item.attach_id}}" class="dropdown-item cloud-tool-rename-btn" href="#" data-id="{{$item.attach_id}}"><i class="bi bi-pencil generic-icons-nav"></i>{{$rename_label}}</a>
						<a id="cloud-tool-move-btn-{{$item.attach_id}}" class="dropdown-item cloud-tool-move-btn" href="#" data-id="{{$item.attach_id}}"><i class="bi bi-copy generic-icons-nav"></i>{{$move_copy_label}}</a>
						<a id="cloud-tool-categories-btn-{{$item.attach_id}}" class="dropdown-item cloud-tool-categories-btn" href="#" data-id="{{$item.attach_id}}"><i class="bi bi-asterisk generic-icons-nav"></i>{{$categories_label}}</a>
						{{if $item.collection}}
						<a id="cloud-tool-dir-download-btn-{{$item.attach_id}}" class="dropdown-item cloud-tool-dir-download-btn" href="#" data-id="{{$item.attach_id}}"><i class="bi bi-download generic-icons-nav"></i>{{$download_label}}</a>
						{{else}}
						{{if $is_owner}}
						<a id="cloud-tool-share-btn-{{$item.attach_id}}" class="dropdown-item cloud-tool-share-btn" href="/rpost?attachment=[attachment]{{$item.resource}},{{$item.revision}}[/attachment]" data-id="{{$item.attach_id}}"><i class="bi bi-arrow-90deg-right generic-icons-nav"></i>{{$post_label}}</a>
						{{/if}}
						<a download="{{$item.name}}" id="cloud-tool-download-btn-{{$item.attach_id}}" class="dropdown-item cloud-tool-download-btn" href="/attach/{{$item.resource}}" data-id="{{$item.attach_id}}"><i class="bi bi-download generic-icons-nav"></i>{{$download_label}}</a>
						{{/if}}
						<a id="cloud-tool-delete-btn-{{$item.attach_id}}" class="dropdown-item cloud-tool-delete-btn" href="#" data-id="{{$item.attach_id}}"><i class="bi bi-trash generic-icons-nav"></i>{{$delete_label}}</a>
					</div>
				</div>
				{{else}}
				{{if $is_admin || $item.attach_id}}
				<div class="dropdown">
					<button class="btn btn-link btn-sm" id="dropdown-button-{{$item.attach_id}}" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
						<i class="bi bi-three-dots-vertical"></i>
					</button>
					<div class="dropdown-menu dropdown-menu-end" aria-labelledby="dropdown-button-{{$item.attach_id}}">
						{{if $item.collection}}
						<a id="cloud-tool-dir-download-btn-{{$item.attach_id}}" class="dropdown-item cloud-tool-dir-download-btn" href="#" data-id="{{$item.attach_id}}"><i class="bi bi-download"></i> {{$download_label}}</a>
						{{else}}
						<a id="cloud-tool-download-btn-{{$item.attach_id}}" class="dropdown-item cloud-tool-download-btn" href="/attach/{{$item.resource}}" data-id="{{$item.attach_id}}"><i class="bi bi-download"></i> {{$download_label}}</a>
						{{/if}}
						{{if $is_admin}}
						<a id="cloud-tool-delete-btn-{{$item.attach_id}}" class="dropdown-item cloud-tool-delete-btn" href="#" data-id="{{$item.attach_id}}"><i class="bi bi-trash"></i> {{$admin_delete_label}}</a>
						{{/if}}
					</div>
				</div>
				{{/if}}
			</td>
			{{/if}}
			<td class="d-none d-md-table-cell p-2">{{$item.size_formatted}}</td>
			<td class="d-none d-md-table-cell p-2">{{$item.last_modified}}</td>
		</tr>
		{{if ($is_owner || ($item.is_creator && $has_perms)) && $item.attach_id}}
		<tr id="cloud-tools-{{$item.attach_id}}" class="cloud-tools">
			<td id="attach-edit-panel-{{$item.attach_id}}" class="attach-edit-panel" colspan="8">
				<form id="attach_edit_form_{{$item.attach_id}}" action="attach_edit" method="post" class="acl-form" data-form_id="attach_edit_form_{{$item.attach_id}}" data-allow_cid='{{$item.allow_cid}}' data-allow_gid='{{$item.allow_gid}}' data-deny_cid='{{$item.deny_cid}}' data-deny_gid='{{$item.deny_gid}}'>
					<input type="hidden" name="attach_id" value="{{$item.attach_id}}" />
					<input type="hidden" name="channel_id" value="{{$channel_id}}" />
					<input type="hidden" name="return_path" value="{{$return_path}}">
					<div id="cloud-tool-rename-{{$item.attach_id}}" class="cloud-tool">
						{{include file="field_input.tpl" field=$item.newfilename}}
					</div>
					<div id="cloud-tool-info-{{$item.attach_id}}" class="cloud-tool">
						{{if ! $item.collection}}
						<div class="mb-3">
							<label for="attach-code-input-{{$item.attach_id}}">{{$attach_bbcode_label}}</label>
							<input type="text" class="form-control" id="attach-code-input-{{$item.attach_id}}" name="attach-code-input-{{$item.attach_id}}" value="{{$item.attach_bbcode}}" onclick="this.select();" />
						</div>
						{{if $item.embed_bbcode}}
						<div class="mb-3">
							<label for="embed-code-input-{{$item.attach_id}}">{{$embed_bbcode_label}}</label>
							<input type="text" class="form-control" id="embed-code-input-{{$item.attach_id}}" name="embed-code-input-{{$item.attach_id}}" value="{{$item.embed_bbcode}}" onclick="this.select();" />
						</div>
						{{/if}}
						{{/if}}
						<div class="mb-3">
							<label for="link-code-input-{{$item.attach_id}}">{{$link_bbcode_label}}</label>
							<input type="text" class="form-control" id="link-code-input-{{$item.attach_id}}" name="link-code-input-{{$item.attach_id}}" value="{{$item.link_bbcode}}" onclick="this.select();" />
						</div>
						<div class="mb-3">
							<button id="cloud-tool-close-btn-{{$item.attach_id}}" class="btn btn-secondary btn-sm cloud-tool-cancel-btn" type="button" data-id="{{$item.attach_id}}">
								{{$close_label}}
							</button>
						</div>
					</div>
					<div id="cloud-tool-move-{{$item.attach_id}}" class="cloud-tool">
						{{include file="field_select.tpl" field=$item.newfolder}}
						{{include file="field_checkbox.tpl" field=$item.copy}}
					</div>
					<div id="cloud-tool-categories-{{$item.attach_id}}" class="cloud-tool">
						{{include file="field_input.tpl" field=$item.categories}}
					</div>
					<div id="cloud-tool-submit-{{$item.attach_id}}" class="cloud-tool">
						{{if $is_owner}}
						{{if !$item.collection}}{{include file="field_checkbox.tpl" field=$item.notify}}{{/if}}
						{{if $item.collection}}{{include file="field_checkbox.tpl" field=$item.recurse}}{{/if}}
						{{/if}}
						<div id="attach-submit-{{$item.attach_id}}" class="mb-3">
							<button id="cloud-tool-cancel-btn-{{$item.attach_id}}" class="btn btn-secondary btn-sm cloud-tool-cancel-btn" type="button" data-id="{{$item.attach_id}}">
									{{$cancel_label}}
							</button>
							<div id="attach-edit-perms-{{$item.attach_id}}" class="btn-group float-end">
								{{if $is_owner}}
								<button id="dbtn-acl-{{$item.attach_id}}" class="btn btn-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#aclModal" title="{{$permset}}" type="button">
									<i id="jot-perms-icon-{{$item.attach_id}}" class="bi bi-{{$item.lockstate}} jot-icons jot-perms-icon"></i>
								</button>
								{{/if}}
								<button id="dbtn-submit-{{$item.attach_id}}" class="btn btn-primary btn-sm" type="submit" name="submit">
									{{$submit_label}}
								</button>
							</div>
						</div>
					</div>
				</form>
			</td>
		</tr>
		{{/if}}
		{{/foreach}}
	</table>
	</div>
	</div>
{{/if}}
</div>
