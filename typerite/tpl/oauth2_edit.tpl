<div class="generic-content-wrapper">
	<div class="section-title-wrapper app-content-header">
		<h3>{{$title}}</h3>
	</div>
<div class="section-content-tools-wrapper">
<form method="POST">
<input type='hidden' name='form_security_token' value='{{$form_security_token}}'>
{{include file="field_input.tpl" field=$name}}
{{include file="field_input.tpl" field=$secret}}
{{include file="field_input.tpl" field=$redirect}}
{{include file="field_input.tpl" field=$grant}}
{{include file="field_input.tpl" field=$scope}}

<div class="settings-submit-wrapper" >
<input type="submit" name="submit" class="settings-submit" value="{{$submit}}" />
<input type="submit" name="cancel" class="settings-submit" value="{{$cancel}}" />
</div>

</form>
</div>
</div>
