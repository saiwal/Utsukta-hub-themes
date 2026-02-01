<div class="generic-content-wrapper">
	<div class="section-title-wrapper app-content-header">
		<div class="lcars-text-bar"><span>{{$title}}</span></div>
		<div class="buttons the-end flush">
		<a title="{{$removeaccount}}" class="" href="removeaccount"><i class="bi bi-trash"></i>&nbsp;{{$removeme}}</a>
		</div>
		<div class="clear"></div>
	</div>
	<form action="settings/account" id="settings-account-form" method="post" autocomplete="off" >
		<input type='hidden' name='form_security_token' value='{{$form_security_token}}'>
		<div class="section-content-tools-wrapper">
			{{include file="field_input.tpl" field=$email}}
			{{if $email_hidden}}
			<input type='hidden' name='email' value='{{$email_hidden}}'>
			{{/if}}
			{{include file="field_password.tpl" field=$origpass}}
			{{include file="field_password.tpl" field=$password1}}
			{{include file="field_password.tpl" field=$password2}}

			<div class="settings-submit-wrapper buttons the-end" >
				<button type="submit" name="submit" class="btn btn-primary">{{$submit}}</button>
				<a href="/settings/multifactor" class="btn btn-success">{{$mfa}}</a>
			</div>
			{{$account_settings}}
		</div>
	</form>
</div>

