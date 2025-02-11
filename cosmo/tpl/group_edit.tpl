<div class="generic-content-wrapper">
	<div class="section-title-wrapper app-content-header">
		<div class="float-end">
			<button id="fullscreen-btn" type="button" class="btn btn-outline-secondary btn-sm" onclick="makeFullScreen();"><i class="bi fa-expand"></i></button>
			<button id="inline-btn" type="button" class="btn btn-outline-secondary btn-sm" onclick="makeFullScreen(false);"><i class="bi fa-compress"></i></button>
		</div>
		<h3>{{$title}}</h3>
	</div>

	<div id="group_tools" class="clearfix section-content-tools-wrapper">
		<form action="group/{{$gid}}" id="group-edit-form" method="post" >
			<input type='hidden' name='form_security_token' value='{{$form_security_token_edit}}'>
			{{include file="field_input.tpl" field=$gname}}
			{{include file="field_checkbox.tpl" field=$public}}
			{{include file="field_checkbox.tpl" field=$is_default_acl}}
			{{include file="field_checkbox.tpl" field=$is_default_group}}
			{{$pgrp_extras}}
			<a href="group/drop/{{$gid}}?t={{$form_security_token_drop}}" onclick="return confirmDelete();" class="btn btn-outline-danger">
				{{$delete}}
			</a>
			<button type="submit" name="submit" class="btn btn-primary float-end">{{$submit}}</button>
		</form>
	</div>
	<div class="section-content-info-wrapper">
		{{$desc}}
	</div>
	<div class="section-content-wrapper">
		<div id="group-update-wrapper" class="clearfix">
			{{include file="groupeditor.tpl"}}
		</div>
	</div>
</div>
