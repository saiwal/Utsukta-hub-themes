<div class="generic-content-wrapper">
	<div class="section-title-wrapper">
		<h2>{{$ptitle}}</h2>
	</div>
	<form action="settings/display" id="settings-form" method="post" autocomplete="off" >
		<input type='hidden' name='form_security_token' value='{{$form_security_token}}'>

		<div class="accordion" id="settings" role="tablist" aria-multiselectable="true">
			{{if $theme}}
			<div class="accordion-item">
				<div class="section-subtitle-wrapper" role="tab" id="theme-settings-title">
					<h2 class="accordion-header">
						<button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#theme-settings-content" aria-expanded="true" aria-controls="theme-settings-content">
							{{$d_tset}}
						</button>
					</h2>
				</div>
				<div id="theme-settings-content" class="accordion-collapse collapse show" data-bs-parent="#settings" >
					<div class="section-content-tools-wrapper accordion-body">
						{{if $theme}}
							{{include file="field_themeselect.tpl" field=$theme}}
						{{/if}}
						{{if $schema}}
							{{include file="field_select.tpl" field=$schema}}
						{{/if}}
						<div class="settings-submit-wrapper" >
							<button type="submit" name="submit" class="btn btn-primary">{{$submit}}</button>
						</div>
					</div>
				</div>
			</div>
			{{/if}}
			{{if $theme_config}}
			<div class="accordion-item">
				<div class="section-subtitle-wrapper" role="tab" id="custom-settings-title">
					<h2 class="accordion-header">
						<button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#custom-settings-content"  aria-controls="custom-settings-content">
							{{$d_ctset}}
						</button>
					</h2>
				</div>
				<div id="custom-settings-content" class="accordion-collapse collapse{{if !$theme}} in{{/if}}" data-bs-parent="#settings" >
					<div class="section-content-tools-wrapper accordion-body">
						{{$theme_config}}
					</div>
				</div>
			</div>
			{{/if}}
			<div class="accordion-item">
				<div class="section-subtitle-wrapper" role="tab" id="content-settings-title">
					<h2 class="accordion-header">
						<button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#content-settings-content" aria-controls="content-settings-content">
							{{$d_cset}}
						</button>
					</h2>
				</div>
				<div id="content-settings-content" class="accordion-collapse collapse{{if !$theme && !$theme_config}} in{{/if}}" data-bs-parent="#settings">
					<div class="section-content-wrapper accordion-body"
						{{include file="field_input.tpl" field=$ajaxint}}
						{{include file="field_input.tpl" field=$itemspage}}
						{{include file="field_checkbox.tpl" field=$nosmile}}
						{{include file="field_checkbox.tpl" field=$title_tosource}}
						{{include file="field_checkbox.tpl" field=$user_scalable}}
						{{include file="field_checkbox.tpl" field=$preload_images}}
						{{include file="field_checkbox.tpl" field=$start_menu}}
						<div class="settings-submit-wrapper" >
							<button type="submit" name="submit" class="btn btn-primary">{{$submit}}</button>
						</div>
					</div>
				</div>
			</div>
		</div>
	</form>
</div>
