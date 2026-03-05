<div class="generic-content-wrapper card">
	<div class="card-header">
		{{$title}}
	</div>
	<div class="section-content-wrapper card-body">
		<form action="register" method="post" id="register-form">
			<input type='hidden' name='form_security_token' value='{{$form_security_token}}'>
			{{if $now}}
			<div class="section-content-danger-wrapper alert-box alert-box--danger">
				<div class="h3">{{$now}}</div>
			</div>
			{{/if}}
			{{if $reg_is || $other_sites || $msg}}
			<div class="section-content-warning-wrapper alert-box alert-box--warning">
				<div id="register-desc" class="descriptive-paragraph">{{$msg}}</div>
				<div id="register-desc" class="descriptive-paragraph">{{$reg_is}}</div>
				<div id="register-sites" class="descriptive-paragraph">{{$other_sites}}</div>
			</div>
			{{/if}}
			{{if $registertext}}
			<div class="section-content-info-wrapper alert-box alert-box--info ">
				<div id="register-text" class="descriptive-paragraph">{{$registertext}}</div>
			</div>
			{{/if}}
    
        
			{{if $invitations}}
			<div class="section-content-info-wrapper">
			<a id="zar014" href="javascript:;" style="display: none;">{{$haveivc}}</a>
			<div id="zar015">
				<div class="position-relative">
					<div id="invite-spinner" class="spinner-wrapper position-absolute" style="top: 2.5rem; right: 0.5rem;"><div class="spinner s"></div></div>
					{{include file="field_input.tpl" field=[$invite_code.0,$invite_code.1,"","",""]}}
				</div>
			</div>
			</div>
			{{/if}}

			{{if $auto_create}}
			<div class="position-relative">
				<div id="name-spinner" class="spinner-wrapper position-absolute" style="top: 2.5rem; right: 0.75rem;"><div class="spinner s"></div></div>
				{{include file="field_input.tpl" field=$name}}
			</div>
			<div class="position-relative">
				<div id="nick-hub" class="position-absolute" style="top: 2.3rem; right: 0.75rem;"><span class="text-muted">{{$nickhub}}</span></div>
				<div id="nick-spinner" class="spinner-wrapper position-absolute" style="top: 2.5rem; right: 0.75rem;"><div class="spinner s"></div></div>
				{{include file="field_input.tpl" field=$nickname}}
			</div>
			{{/if}}
			{{include file="field_input.tpl" field=$email}}
			{{include file="field_password.tpl" field=$pass1}}
			{{include file="field_password.tpl" field=$pass2}}
			{{if $register_msg}}
			{{include file="field_textarea.tpl" field=$register_msg}}
			{{/if}}
			{{if $enable_tos}}
			{{include file="field_checkbox.tpl" field=$tos}}
			{{else}}
			<input type="hidden" name="tos" value="1" />
			{{/if}}
			<button class="btn btn-primary" type="submit" name="submit" id="newchannel-submit-button" value="{{$submit}}" {{$atform}}>{{$submit}}</button>
			<div id="register-submit-end" class="register-field-end"></div>
		</form>
	</div>
</div>
