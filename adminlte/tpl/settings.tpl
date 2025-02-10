<div class="generic-content-wrapper">
	<div class="section-title-wrapper">
		<a title="{{$removechannel}}" class="btn btn-danger btn-sm float-end" href="removeme"><i class="bi bi-trash"></i>&nbsp;{{$removeme}}</a>
		<h2>{{$ptitle}}</h2>
		<div class="clear"></div>
	</div>
	{{$nickname_block}}
	<form action="settings" id="settings-form" method="post" autocomplete="off" class="acl-form" data-form_id="settings-form" data-allow_cid='{{$allow_cid}}' data-allow_gid='{{$allow_gid}}' data-deny_cid='{{$deny_cid}}' data-deny_gid='{{$deny_gid}}'>
		<input type='hidden' name='form_security_token' value='{{$form_security_token}}' />
		<div class="accordion" id="settings" role="tablist" aria-multiselectable="true">
			<div class="accordion-item">
				<div class="section-subtitle-wrapper" role="tab" id="basic-settings">
					<h2 class="accordion-header">
							<button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#settings-collapse" aria-expanded="true" aria-controls="settings-collapse">
							{{$h_basic}}
						</button>
					</h2>
				</div>
				<div id="basic-settings-collapse" class="accordion-collapse collapse show" data-bs-parent="#settings">
					<div class="section-content-tools-wrapper accordion-body">
						{{include file="field_select.tpl" field=$role}}
						{{include file="field_select_grouped.tpl" field=$timezone}}
						{{include file="field_input.tpl" field=$defloc}}
						{{include file="field_checkbox.tpl" field=$allowloc}}
						{{include file="field_checkbox.tpl" field=$adult}}
						{{include file="field_input.tpl" field=$photo_path}}
						{{include file="field_input.tpl" field=$attach_path}}
						{{include file="field_input.tpl" field=$expire}}

						{{include file="field_textarea.tpl" field=$message_filter_excl}}
						{{include file="field_textarea.tpl" field=$message_filter_incl}}

						{{if $basic_addon}}
						{{$basic_addon}}
						{{/if}}
						<div class="settings-submit-wrapper" >
							<button type="submit" name="submit" class="btn btn-primary">{{$submit}}</button>
						</div>
					</div>
				</div>
			</div>
			<div class="accordion-item">
				<div class="section-subtitle-wrapper" role="tab" id="notification-settings">
					<h2 class="accordion-header">
							<button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#notification-settings-collapse" aria-expanded="true" aria-controls="notification-settings-collapse">
							{{$h_not}}
						</a>
					</h2>
				</div>
				<div id="notification-settings-collapse" class="accordion-collapse collapse" data-bs-parent="#settings">
					<div class="section-content-tools-wrapper accordion-body">
						<div id="settings-notifications">

							<div id="desktop-notifications-info" class="section-content-warning-wrapper" style="display: none;">
								{{$desktop_notifications_info}}<br>
								<a id="desktop-notifications-request" href="#">{{$desktop_notifications_request}}</a>
							</div>

							{{include file="field_input.tpl" field=$mailhost}}

							<h3>{{$activity_options}}</h3>
							<div class="group">
								{{*not yet implemented *}}
								{{*include file="field_checkbox.tpl" field=$post_joingroup*}}
								{{include file="field_checkbox.tpl" field=$post_newfriend}}
								{{include file="field_checkbox.tpl" field=$post_profilechange}}
							</div>
							<h3>{{$lbl_not}}</h3>
							<div class="group">
								{{include file="field_intcheckbox.tpl" field=$notify1}}
								{{include file="field_intcheckbox.tpl" field=$notify2}}
								{{include file="field_intcheckbox.tpl" field=$notify3}}
								{{include file="field_intcheckbox.tpl" field=$notify4}}
								{{*include file="field_intcheckbox.tpl" field=$notify9*}}
								{{include file="field_intcheckbox.tpl" field=$notify5}}
								{{include file="field_intcheckbox.tpl" field=$notify6}}
								{{include file="field_intcheckbox.tpl" field=$notify7}}
								{{include file="field_intcheckbox.tpl" field=$notify8}}
							</div>
							<h3>{{$lbl_vnot}}</h3>
							<div class="group">
								{{include file="field_intcheckbox.tpl" field=$vnotify1}}
								{{include file="field_intcheckbox.tpl" field=$vnotify2}}
								{{include file="field_intcheckbox.tpl" field=$vnotify3}}
								{{include file="field_intcheckbox.tpl" field=$vnotify4}}
								{{include file="field_intcheckbox.tpl" field=$vnotify5}}
								{{include file="field_intcheckbox.tpl" field=$vnotify6}}
								{{include file="field_intcheckbox.tpl" field=$vnotify10}}
								{{include file="field_intcheckbox.tpl" field=$vnotify7}}
								{{include file="field_intcheckbox.tpl" field=$vnotify8}}
								{{include file="field_intcheckbox.tpl" field=$vnotify9}}
								{{if $vnotify11}}
									{{include file="field_intcheckbox.tpl" field=$vnotify11}}
								{{/if}}
								{{include file="field_intcheckbox.tpl" field=$vnotify12}}
								{{if $vnotify13}}
									{{include file="field_intcheckbox.tpl" field=$vnotify13}}
								{{/if}}
								{{include file="field_intcheckbox.tpl" field=$vnotify14}}
								{{include file="field_intcheckbox.tpl" field=$vnotify15}}
								{{include file="field_intcheckbox.tpl" field=$always_show_in_notices}}
								{{include file="field_intcheckbox.tpl" field=$update_notices_per_parent}}
								{{include file="field_input.tpl" field=$evdays}}
							</div>
						</div>
						{{if $notify_addon}}
						{{$notify_addon}}
						{{/if}}
						<div class="settings-submit-wrapper" >
							<button type="submit" name="submit" class="btn btn-primary">{{$submit}}</button>
						</div>
					</div>
				</div>
			</div>
		</div>
	</form>
	{{$aclselect}}
</div>
