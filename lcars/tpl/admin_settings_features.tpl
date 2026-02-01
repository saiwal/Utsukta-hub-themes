<div class="generic-content-wrapper">
	<div class="section-title-wrapper app-content-header">
		<div class="lcars-text-bar"><span>{{$title}}</span></div>
	</div>
	<form action="admin/features" method="post" autocomplete="off">
	<input type='hidden' name='form_security_token' value='{{$form_security_token}}'>
	<div class="accordion" id="settings" role="tablist" aria-multiselectable="true">
		{{foreach $features as $g => $f}}
		<div class="accordion-item">
			<div class="section-subtitle-wrapper" role="tab" id="{{$g}}-settings-title">
				<h2 class="accordion-header mt-0">
					<button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#{{$g}}-settings-content" aria-expanded="false" aria-controls="{{$g}}-settings-collapse">
						{{$f.0}}
					</button>
				</h2>
			</div>
			<div id="{{$g}}-settings-content" class="accordion-collapse collapse{{if $g == 'general'}} show{{/if}}" data-bs-parent="#settings">
				<div class="section-content-tools-wrapper accordion-body">
					{{foreach $f.1 as $fcat}}
						{{include file="field_checkbox.tpl" field=$fcat.0}}
						{{include file="field_checkbox.tpl" field=$fcat.1}}
					{{/foreach}}
					<div class="settings-submit-wrapper buttons the-end" >
						<button type="submit" name="submit" class="flush">{{$submit}}</button>
					</div>
				</div>
			</div>
		</div>
		{{/foreach}}
	</div>
</div>
