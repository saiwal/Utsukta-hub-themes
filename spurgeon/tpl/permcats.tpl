<div class="generic-content-wrapper">
	<div class="section-title-wrapper app-content-header">
		<h3>{{$title}}</h3>
	</div>
	<div class="section-content-tools-wrapper">
    <div class="card mb-3">
    <div class="card-body">
		<form action="permcats/{{$return_path}}" id="settings-permcats-form" method="post" autocomplete="off" >
			<input type="hidden" name="form_security_token" value="{{$form_security_token}}">
			<input type="hidden" name="return_path" value="{{$return_path}}">

			{{if $is_system_role}}
			<input type="hidden" name="is_system_role" value="1">
			<input type="hidden" name="name" value="{{$is_system_role}}">
			{{/if}}

			{{include file="field_input.tpl" field=$name}}
			{{include file="field_checkbox.tpl" field=$default_role}}

			{{$group_select}}

			<div class="section-subtitle-wrapper" id="perms-tool">
				<h3>
					{{$permlbl}}
				</h3>
			</div>
			<div class="section-content-warning-wrapper">
			{{$permnote}}
			</div>
			<table id="" class="table table-hover">
				{{foreach $perms as $prm}}
				{{include file="field_acheckbox.tpl" field=$prm}}
				{{/foreach}}
			</table>
			<div class="clearfix">
				{{if !$is_system_role && $return_path}}
				<button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#delete-modal">{{$delet_role_button}}</button>
				{{/if}}
				<button type="submit" name="submit" class="btn btn-primary float-end">{{$submit}}</button>
			</div>
		</form>
	</div>
	</div>
	</div>
</div>
{{if !$is_system_role && $return_path}}
<div id="delete-modal" class="modal" tabindex="-1">
	<div class="modal-dialog">
		<div class="modal-content">
			<div class="modal-header">
				<div class="h5">
					{{$delete_label}}
				</div>
				<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
			</div>
			<form action="permcats" id="delete-permcat-form" method="post">
				<input type="hidden" name="form_security_token" value="{{$form_security_token}}">
				<input type="hidden" name="deleted_role" value="{{$current_role}}">
				<div id="edit-modal-body" class="modal-body">
					{{include file="field_select.tpl" field=$delete_role_select}}
				</div>
				<div class="modal-footer">
					<button id="" type="submit" class="btn btn-danger">{{$delet_role_button}}</button>
				</div>
			</form>
		</div>
	</div>
</div>
{{/if}}
