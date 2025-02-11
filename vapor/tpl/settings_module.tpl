<div class="generic-content-wrapper">
	<div class="section-title-wrapper app-content-header">
		<h3>{{$title}}</h3>
	</div>
	<div class="section-content-wrapper">
		<form action="{{$action_url}}" method="post" autocomplete="off">
			<input type='hidden' name='form_security_token' value='{{$form_security_token}}'>
			{{if $rpath}}
			<input type='hidden' name='rpath' value='{{$rpath}}'>
			{{/if}}
			{{foreach $features as $feature}}
			{{include file="field_checkbox.tpl" field=$feature}}
			{{/foreach}}
			{{if $extra_settings_html}}
			{{$extra_settings_html}}
			{{/if}}
			<div class="settings-submit-wrapper" >
				<button type="submit" name="submit" class="btn btn-primary">{{$submit}}</button>
			</div>
		</form>
	</div>
</div>
