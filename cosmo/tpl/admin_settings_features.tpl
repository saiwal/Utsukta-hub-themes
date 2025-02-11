<div class="generic-content-wrapper">
	<div class="section-title-wrapper app-content-header">
		<h3>{{$title}}</h3>
	</div>
	<form action="admin/features" method="post" autocomplete="off">
	<input type='hidden' name='form_security_token' value='{{$form_security_token}}'>
	<div class="panel-group" id="settings" role="tablist" aria-multiselectable="true">
		{{foreach $features as $g => $f}}
		<div class="panel">
			<div class="section-subtitle-wrapper" role="tab" id="{{$g}}-settings-title">
				<h3>
					<a data-bs-toggle="collapse" data-bs-target="#{{$g}}-settings-content" href="#" aria-expanded="true" aria-controls="{{$g}}-settings-collapse">
						{{$f.0}}
					</a>
				</h3>
			</div>
			<div id="{{$g}}-settings-content" class="panel-collapse collapse{{if $g == 'general'}} show{{/if}}" data-bs-parent="#settings" role="tabpanel" aria-labelledby="{{$g}}-settings-title">
				<div class="section-content-tools-wrapper">
					{{foreach $f.1 as $fcat}}
						{{include file="field_checkbox.tpl" field=$fcat.0}}
						{{include file="field_checkbox.tpl" field=$fcat.1}}
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
