<div class="generic-content-wrapper">
	<div class="clearfix section-title-wrapper app-content-header">
		<h3>{{$title}}</h3>
	</div>
	<div id="group_tools" class="clearfix section-content-tools-wrapper">
		<form action="group/new" id="group-edit-form" method="post" >
			<input type='hidden' name='form_security_token' value='{{$form_security_token}}'>
			{{include file="field_input.tpl" field=$gname}}
			{{include file="field_checkbox.tpl" field=$public}}
			{{include file="field_checkbox.tpl" field=$is_default_acl}}
			{{include file="field_checkbox.tpl" field=$is_default_group}}
			{{$pgrp_extras}}
			<button type="submit" name="submit" class="btn btn-primary float-end">{{$submit}}</button>
		</form>
	</div>
</div>
