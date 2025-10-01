<div class="generic-content-wrapper-styled" id='adminpage'>
	<div class="section-title-wrapper app-content-header">
	<h3 class="border-0">{{$title}}</h3>
	</div>
  <div class="card mb-3">
    <div class="card-header">{{$page}}</div>
<div class="card-body">
	<form action="{{$baseurl}}/admin/security" method="post">

	<input type='hidden' name='form_security_token' value='{{$form_security_token}}'>


	{{include file="field_checkbox.tpl" field=$block_public}}
	{{include file="field_checkbox.tpl" field=$cloud_noroot}}
	{{include file="field_checkbox.tpl" field=$cloud_disksize}}
	{{include file="field_checkbox.tpl" field=$transport_security}}
	{{include file="field_checkbox.tpl" field=$content_security}}
	{{include file="field_checkbox.tpl" field=$embed_sslonly}}

	{{include file="field_checkbox.tpl" field=$thumbnail_security}}
	{{include file="field_checkbox.tpl" field=$inline_pdf}}

	{{include file="field_textarea.tpl" field=$allowed_email}}
	{{include file="field_textarea.tpl" field=$not_allowed_email}}

	{{include file="field_textarea.tpl" field=$whitelisted_sites}}
	{{include file="field_textarea.tpl" field=$blacklisted_sites}}

	{{include file="field_textarea.tpl" field=$whitelisted_channels}}
	{{include file="field_textarea.tpl" field=$blacklisted_channels}}

	{{include file="field_textarea.tpl" field=$embed_allow}}
	{{include file="field_textarea.tpl" field=$embed_deny}}
	{{if $trusted_directory_servers}}
		{{include file="field_textarea.tpl" field=$trusted_directory_servers}}
	{{/if}}


	<div class="admin-submit-wrapper float-end">
		<input type="submit" name="submit" class="btn btn-primary" value="{{$submit}}" />
	</div>

	</form>
    </div>
    </div>
</div>
