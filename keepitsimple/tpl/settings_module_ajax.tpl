<form class="mb-0" id="settings_module_ajax_form" action="{{$action_url}}" method="post" autocomplete="off">
	<input type='hidden' name='form_security_token' value='{{$form_security_token}}'>
	<input type='hidden' name='aj' value='1'>
	{{foreach $features as $feature}}
	{{include file="field_checkbox.tpl" field=$feature}}
	{{/foreach}}
	<div class="settings-submit-wrapper float-end">
		<button id="settings_module_ajax_submit" type="submit" name="submit" class="btn btn-primary">{{$submit}}</button>
	</div>
</form>

