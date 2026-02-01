<div class="generic-content-wrapper">
	<div class="section-title-wrapper app-content-header">
		<h3>{{$title}}</h3>
	</div>
	<form action="admin/features" method="post" autocomplete="off">
	<input type='hidden' name='form_security_token' value='{{$form_security_token}}'>
	<div class="accordion accordion-flush" id="settings" role="tablist" aria-multiselectable="true">
		{{foreach $features as $g => $f}}
		<div class="accordion-item">
			<div class="section-subtitle-wrapper" role="tab" id="{{$g}}-settings-title">
				<h6 class="accordion-header mt-0">
					<button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#{{$g}}-settings-content" aria-expanded="false" aria-controls="{{$g}}-settings-collapse">
						{{$f.0}}
					</button>
				</h6>
			</div>
			<div id="{{$g}}-settings-content" class="accordion-collapse collapse{{if $g == 'general'}} show{{/if}}" data-bs-parent="#settings">
				<div class="section-content-tools-wrapper accordion-body">
					{{foreach $f.1 as $fcat}}
						{{include file="field_checkbox.tpl" field=$fcat.0}}
						{{include file="field_checkbox.tpl" field=$fcat.1}}
					{{/foreach}}
					<div class="settings-submit-wrapper d-flex justify-content-end" >
						<button type="submit" name="submit" class="btn btn-primary">{{$submit}}</button>
					</div>
				</div>
			</div>
		</div>
		{{/foreach}}
	</div>
</div>
