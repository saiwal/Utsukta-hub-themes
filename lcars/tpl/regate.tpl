<div class="generic-content-wrapper">
	<div class="section-title-wrapper app-content-header">
		<h3>{{$title}}</h3>
	</div>
	<div class="section-content-wrapper">
		{{if $now}}
		<div class="section-content-danger-wrapper">
			<div class="h3">{{$now}}</div>
		</div>
		{{else}}
		<div class="section-content-info-wrapper">
			{{$desc}} {{$id}}
			<div class="h3">{{$pin}}</div>
			{{if $email_extra}}<b>{{$email_extra}}</b>{{/if}}

		</div>

		<form action="regate/{{$did2}}" method="post">
			<input type='hidden' name='form_security_token' value='{{$form_security_token}}'>
			{{include file="field_input.tpl" field=[$acpin.0,$acpin.1,"","","",$atform]}}

			<div class="float-end submit-wrapper">
				<button type="submit" name="submit" class="btn btn-primary" {{$atform}}>{{$submit}}</button>
			</div>

			{{if $resend}}
			<div class="resend-email" >
				<button type="submit" name="resend" class="btn btn-warning" {{$atform}}>{{$resend}}</button>
			</div>
			{{/if}}

		</form>
		{{/if}}
		<div class="clearfix"></div>
	</div>
</div>
<script>
	$('.register_date').each( function () {
		var date = new Date($(this).data('utc'));
		$(this).html(date.toLocaleString(undefined, {weekday: 'short', hour: 'numeric', minute: 'numeric'}));
	});
</script>
