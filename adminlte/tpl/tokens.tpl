<div class="generic-content-wrapper">
	<div class="section-title-wrapper app-content-header">
		<h3>{{$title}}</h3>
		<div class="clear"></div>
	</div>
	<div class="section-content-tools-wrapper">
		<div class="section-content-info-wrapper">
			{{$desc}}
		</div>

		<form action="tokens" id="settings-account-form" method="post" autocomplete="off" >
			<input type='hidden' name='form_security_token' value='{{$form_security_token}}'>
			{{if $atoken}}<input type="hidden" name="atoken_id" value="{{$atoken.atoken_id}}" />{{/if}}
			{{include file="field_input.tpl" field=$name}}
			{{include file="field_input.tpl" field=$token}}
			{{include file="field_input.tpl" field=$expires}}
			{{include file="field_select.tpl" field=$permcat}}

			<div class="clearfix">
				{{if $atoken}}
				<button type="submit" name="delete" class="btn btn-danger">{{$delete}}</button>
				{{/if}}
				<button type="submit" name="submit" class="btn btn-primary float-end">{{$submit}}</button>
			</div>
	</div>
</div>
