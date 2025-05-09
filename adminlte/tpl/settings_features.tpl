<div class="generic-content-wrapper">
	<div class="section-title-wrapper app-content-header">
		<h3>{{$title}}</h3>
	</div>
	<form action="settings/features" method="post" autocomplete="off">
	<input type='hidden' name='form_security_token' value='{{$form_security_token}}'>

	<div class="accordion" id="settings" role="tablist" aria-multiselectable="true">
		{{foreach $features as $g => $f}}
		<div class="accordion-item">
			<div class="section-subtitle-wrapper" role="tab" id="{{$g}}-settings-title">
				<h2 class="accordion-header">
					<button class="accordion-button collapsed" type="button"  data-bs-toggle="collapse" data-bs-target="#{{$g}}-settings-content" aria-controls="{{$g}}-settings-collapse">
						{{$f.0}}
					</button>
				</h2>
			</div>
			<div id="{{$g}}-settings-content" class="accordion-collapse collapse{{if $g == 'general'}} show{{/if}}" data-bs-parent="#settings">
				<div class="section-content-tools-wrapper accordion-body">
					{{foreach $f.1 as $fcat}}
						{{include file="field_checkbox.tpl" field=$fcat}}
					{{/foreach}}
					<div class="settings-submit-wrapper" >
						<button type="submit" name="submit" class="btn btn-primary">{{$submit}}</button>
					</div>
				</div>
			</div>
		</div>
		{{/foreach}}
	</div>
</div>
