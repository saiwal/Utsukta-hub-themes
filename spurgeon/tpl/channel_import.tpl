<div class="generic-content-wrapper">
	<div class="section-title-wrapper app-content-header">
		<h3>{{$title}}</h3>
		<div class="clear"></div>
	</div>
	<div class="section-content-wrapper">

		<form action="import" method="post" enctype="multipart/form-data" id="import-channel-form">
		<input type="hidden" name="form_security_token" value="{{$form_security_token}}">
		<div id="import-desc" class="section-content-info-wrapper">{{$desc}}</div>

		<label for="import-filename" id="label-import-filename" class="import-label" >{{$label_filename}}</label>
		<input type="file" name="filename" id="import-filename" class="import-input" value="" />
		<div id="import-filename-end" class="import-field-end"></div>

		<div id="import-choice" class="section-content-info-wrapper">{{$choice}}</div>

		{{include file="field_input.tpl" field=$old_address}}
		{{include file="field_input.tpl" field=$email}}
		{{include file="field_password.tpl" field=$password}}
		{{include file="field_checkbox.tpl" field=$import_posts}}

		<div id="import-common-desc" class="section-content-info-wrapper">{{$common}}</div>

		{{include file="field_checkbox.tpl" field=$make_primary}}
		{{* include file="field_checkbox.tpl" field=$moving *}}
		{{include file="field_input.tpl" field=$newname}}

		<div id="import-common-desc" class="section-content-info-wrapper">{{$pleasewait}}</div>

		<input type="submit" class="btn btn-primary" name="submit" id="import-submit-button" value="{{$submit}}" />
		<div id="import-submit-end" class="import-field-end"></div>

		</form>
	</div>
</div>
