<div class="generic-content-wrapper">
	<div class="section-title-wrapper app-content-header">
		<h3>{{$title}}</h3>
	</div>
	<div class="section-content-tools-wrapper">
		{{if $secret}}
		<div class="section-content-info-wrapper">
			<div>{{$secret_text}}</div>
			<div><strong class="text-break">{{$secret}}</strong></div>
		</div>
		{{/if}}
		<img src="{{$qrcode}}" alt="{{$uri}}" title="{{$uri}}">
		<div id="mfa-test-wrapper" class="mb-3">
			<form action="" id="totp-test-form" method="post" autocomplete="off" >
				<div class="mb-3">
					<label for="totp_test">{{$test_title}}</label>
					<input type="text" id="totp_test" class="form-control" onfocus="totp_clear_code()"/>
					<small class="text-muted">{{$test_title_sub}}</small>
				</div>
				<button id="otp-test-submit" type="submit" name="submit" class="btn btn-primary" onclick="totp_test_code(); return false;">
					{{$test}}
				</button>
			</form>
		</div>
		<div id="mfa-submit-wrapper" class="{{if !$enable_mfa.2}}d-none{{/if}}">
			<form action="settings/multifactor" method="post">
				<input type='hidden' name='form_security_token' value='{{$form_security_token}}'>
				{{include file="field_password.tpl" field=$password}}
				{{include file="field_checkbox.tpl" field=$enable_mfa}}
				<div class="settings-submit-wrapper" >
					<button id="otp-enable-submit" type="b" name="submit" class="btn btn-primary">
						{{$submit}}
					</button>
				</div>
			</form>
		</div>
	</div>
</div>

<script>
	function totp_clear_code() {
		let box = document.getElementById('totp_test');
		box.value = '';
		box.focus();
	}

	function totp_test_code() {
		$.post(
			'totp_check',
			{totp_code: document.getElementById('totp_test').value},
			function(data) {
				if (data['status']) {
					toast('{{$test_pass}}', 'info');
					let e = document.getElementById('mfa-submit-wrapper');
					e.classList.remove('d-none');
					return;
				}
				toast('{{$test_fail}}', 'danger');
			}
		);
	}
</script>


