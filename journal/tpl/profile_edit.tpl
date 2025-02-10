<div class="generic-content-wrapper">
	<div class="section-title-wrapper">
		<div class="dropdown float-end" id="profile-edit-links">
			<button type="button" class="btn btn-outline-secondary btn-sm dropdown-toggle" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
				<i class="bi bi-gear"></i>&nbsp;{{$tools_label}}
			</button>
			<div class="dropdown-menu dropdown-menu-end">
				<a class="dropdown-item" href="profile_photo/{{$profile_id}}" id="profile-photo_upload-link" title="{{$profpic}}"><i class="bi bi-person"></i>&nbsp;{{$profpic}}</a>
				{{if $is_default}}
				<a class="dropdown-item" href="cover_photo" id="cover-photo_upload-link" title="{{$coverpic}}"><i class="bi bi-image"></i>&nbsp;{{$coverpic}}</a>
				{{/if}}
				{{if ! $is_default}}
				<a class="dropdown-item" href="profperm/{{$profile_id}}" id="profile-edit-visibility-link" title="{{$editvis}}"><i class="bi bi-pencil"></i>&nbsp;{{$editvis}}</a>
				{{/if}}
				<a class="dropdown-item" href="thing" id="profile-edit-thing-link" title="{{$addthing}}"><i class="bi bi-plus-lg"></i>&nbsp;{{$addthing}}</a>
				<div class="dropdown-divider"></div>
				<a class="dropdown-item" href="profile/{{$profile_id}}/view" id="profile-edit-view-link" title="{{$viewprof}}">{{$viewprof}}</a>
				{{if $multi_profiles}}
				<div class="dropdown-divider"></div>
				<a class="dropdown-item" href="{{$profile_clone_link}}" id="profile-edit-clone-link" title="{{$cr_prof}}">{{$cl_prof}}</a>
				{{/if}}
				{{if $exportable}}
				<div class="dropdown-divider"></div>
				<a class="dropdown-item" href="profiles/export/{{$profile_id}}">{{$lbl_export}}</a>
				<a class="dropdown-item" href="#" onClick="openClose('profile-upload-form'); return false;">{{$lbl_import}}</a>
				{{/if}}
				{{if ! $is_default}}
				<div class="dropdown-divider"></div>
				<a class="dropdown-item" href="{{$profile_drop_link}}" id="profile-edit-drop-link" title="{{$del_prof}}" onclick="return confirmDelete();"><i class="bi bi-trash"></i>&nbsp;{{$del_prof}}</a>
				{{/if}}
			</div>
		</div>
		<h3>{{$banner}}{{if $multi_profiles}}: {{$profile_name.2}}{{/if}}</h3>
		<div class="clear"></div>
	</div>
	<div class="section-content-tools-wrapper" id="profile-upload-form">
		<label id="profile-upload-choose-label" for="profile-upload-choose" >{{$lbl_import}}</label>
		<input id="profile-upload-choose" type="file" name="userfile">
	</div>

		<form id="profile-edit-form" name="form1" action="profiles/{{$profile_id}}" enctype="multipart/form-data" method="post" >
			<input type='hidden' name='form_security_token' value='{{$form_security_token}}'>

			{{if $is_default}}
			<div class="section-content-info-wrapper" class="callout callout-info mb-2">{{$default}}</div>
			{{/if}}

			<div class="accordion" id="profile-edit-wrapper" role="tablist" aria-multiselectable="true">
				<div class="accordion-item">
					<div class="section-subtitle-wrapper" role="tab" id="personal">
						<h2 class="accordion-header">
							<button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#personal-collapse" aria-expanded="true" aria-controls="personal-collapse">
								{{$basic}}
							</a>
						</h2>
					</div>
					<div id="personal-collapse" class="accordion-collapse collapse show" data-bs-parent="#profile-edit-wrapper">
						<div class="section-content-tools-wrapper accordion-body">
							{{if $multi_profiles}}
							{{include file="field_input.tpl" field=$profile_name}}
							{{else}}
							<input type="hidden" name="{{$profile_name.0}}" value="{{$profile_name.2}}">
							{{/if}}

							{{include file="field_input.tpl" field=$name}}

							{{if $fields.pdesc}}
							{{include file="field_input.tpl" field=$pdesc}}
							{{/if}}

							{{if $fields.gender}}
							<div id="profile-edit-gender-wrapper" class="mb-3 field select" >
							<label id="profile-edit-gender-label" for="gender-select" >{{$lbl_gender}}</label>
							{{if $advanced}}
							{{$gender}}
							{{else}}
							{{$gender_min}}
							{{/if}}
							</div>
							<div class="clear"></div>
							{{/if}}

							{{if $fields.dob}}
							{{$dob}}
							{{/if}}

							{{$profile_in_dir}}

							{{$suggestme}}

							{{if $show_presence}}
							{{include file="field_checkbox.tpl" field=$show_presence}}
							{{/if}}

							{{if $hide_friends}}
							{{include file="field_checkbox.tpl" field=$hide_friends}}
							{{/if}}

							<div class="mb-3" >
							<button type="submit" name="submit" class="btn btn-primary" value="{{$submit}}">{{$submit}}</button>
							</div>
							<div class="clear"></div>
						</div>
					</div>
				</div>

				{{if $fields.comms && $vcard}}

				<div id="template-form-vcard-tel" class="mb-3 form-vcard-tel">
					<select name="tel_type[]">
						<option value="CELL">{{$mobile}}</option>
						<option value="HOME">{{$home}}</option>
						<option value="WORK">{{$work}}</option>
						<option value="OTHER">{{$other}}</option>
					</select>
					<input type="text" name="tel[]" value="" placeholder="{{$tel_label}}">
					<i data-remove="vcard-tel" data-id="" class="bi bi-trash remove-field drop-icons fakelink"></i>
				</div>

				<div id="template-form-vcard-email" class="mb-3 form-vcard-email">
					<select name="email_type[]">
						<option value="HOME">{{$home}}</option>
						<option value="WORK">{{$work}}</option>
						<option value="OTHER">{{$other}}</option>
					</select>
					<input type="text" name="email[]" value="" placeholder="{{$email_label}}">
					<i data-remove="vcard-email" data-id="" class="bi bi-trash remove-field drop-icons fakelink"></i>
				</div>

				<div id="template-form-vcard-impp" class="mb-3 form-vcard-impp">
					<select name="impp_type[]">
						<option value="HOME">{{$home}}</option>
						<option value="WORK">{{$work}}</option>
						<option value="OTHER">{{$other}}</option>
					</select>
					<input type="text" name="impp[]" value="" placeholder="{{$impp_label}}">
					<i data-remove="vcard-impp" data-id="" class="bi bi-trash remove-field drop-icons fakelink"></i>
				</div>

				<div class="section-content-wrapper-np">
					<div id="vcard-cancel-{{$vcard.id}}" class="vcard-cancel vcard-cancel-btn" data-id="{{$vcard.id}}" data-action="cancel"><i class="bi bi-x-lg"></i></div>
					<div id="vcard-add-field-{{$vcard.id}}" class="dropdown float-end vcard-add-field">
						<button data-bs-toggle="dropdown" type="button" class="btn btn-outline-secondary btn-sm dropdown-toggle"><i class="bi bi-plus-lg"></i> {{$add_field}}</button>
						<ul class="dropdown-menu">
							<li class="add-vcard-tel"><a href="#" data-add="vcard-tel" data-id="{{$vcard.id}}" class="add-field" onclick="return false;">{{$tel_label}}</a></li>
							<li class="add-vcard-email"><a href="#" data-add="vcard-email" data-id="{{$vcard.id}}" class="add-field" onclick="return false;">{{$email_label}}</a></li>
							<li class="add-vcard-impp"><a href="#" data-add="vcard-impp" data-id="{{$vcard.id}}" class="add-field" onclick="return false;">{{$impp_label}}</a></li>
						</ul>
					</div>
					<div id="vcard-header-{{$vcard.id}}" class="vcard-header" data-id="{{$vcard.id}}" data-action="open">
						<i class="vcard-fn-preview bi fa-address-card-o"></i>
						<span id="vcard-preview-{{$vcard.id}}" class="vcard-preview">
							{{if $vcard.fn}}<span class="vcard-fn-preview">{{$vcard.fn}}</span>{{/if}}
							{{if $vcard.emails.0.address}}<span class="vcard-email-preview hidden-xs"><a href="mailto:{{$vcard.emails.0.address}}">{{$vcard.emails.0.address}}</a></span>{{/if}}
							{{if $vcard.tels.0}}<span class="vcard-tel-preview hidden-xs">{{$vcard.tels.0.nr}}{{if $is_mobile}} <a class="btn btn-outline-secondary btn-sm" href="tel:{{$vcard.tels.0.nr}}"><i class="bi fa-phone connphone"></i></a>{{/if}}</span>{{/if}}
						</span>
						<input id="vcard-fn-{{$vcard.id}}" class="vcard-fn" type="text" name="fn" value="{{$vcard.fn}}" size="{{$vcard.fn|count_characters:true}}" placeholder="{{$name_label}}">
					</div>
				</div>
				<div id="vcard-info-{{$vcard.id}}" class="vcard-info section-content-wrapper">

					<div class="vcard-tel mb-3">
						<div class="form-vcard-tel-wrapper">
							{{if $vcard.tels}}
							{{foreach $vcard.tels as $tel}}
							<div class="mb-3 form-vcard-tel">
								<select name="tel_type[]">
									<option value=""{{if $tel.type.0 != 'CELL' && $tel.type.0 != 'HOME' && $tel.type.0 != 'WORK' && $tel.type.0 != 'OTHER'}} selected="selected"{{/if}}>{{$tel.type.1}}</option>
									<option value="CELL"{{if $tel.type.0 == 'CELL'}} selected="selected"{{/if}}>{{$mobile}}</option>
									<option value="HOME"{{if $tel.type.0 == 'HOME'}} selected="selected"{{/if}}>{{$home}}</option>
									<option value="WORK"{{if $tel.type.0 == 'WORK'}} selected="selected"{{/if}}>{{$work}}</option>
									<option value="OTHER"{{if $tel.type.0 == 'OTHER'}} selected="selected"{{/if}}>{{$other}}</option>
								</select>
								<input type="text" name="tel[]" value="{{$tel.nr}}" size="{{$tel.nr|count_characters:true}}" placeholder="{{$tel_label}}">
								<i data-remove="vcard-tel" data-id="{{$vcard.id}}" class="bi bi-trash remove-field drop-icons fakelink"></i>
							</div>
							{{/foreach}}
							{{/if}}
						</div>
					</div>


					<div class="vcard-email mb-3">
						<div class="form-vcard-email-wrapper">
							{{if $vcard.emails}}
							{{foreach $vcard.emails as $email}}
							<div class="mb-3 form-vcard-email">
								<select name="email_type[]">
									<option value=""{{if $email.type.0 != 'HOME' && $email.type.0 != 'WORK' && $email.type.0 != 'OTHER'}} selected="selected"{{/if}}>{{$email.type.1}}</option>
									<option value="HOME"{{if $email.type.0 == 'HOME'}} selected="selected"{{/if}}>{{$home}}</option>
									<option value="WORK"{{if $email.type.0 == 'WORK'}} selected="selected"{{/if}}>{{$work}}</option>
									<option value="OTHER"{{if $email.type.0 == 'OTHER'}} selected="selected"{{/if}}>{{$other}}</option>
								</select>
								<input type="text" name="email[]" value="{{$email.address}}" size="{{$email.address|count_characters:true}}" placeholder="{{$email_label}}">
								<i data-remove="vcard-email" data-id="{{$vcard.id}}" class="bi bi-trash remove-field drop-icons fakelink"></i>
							</div>
							{{/foreach}}
							{{/if}}
						</div>
					</div>

					<div class="vcard-impp mb-3">
						<div class="form-vcard-impp-wrapper">
							{{if $vcard.impps}}
							{{foreach $vcard.impps as $impp}}
							<div class="mb-3 form-vcard-impp">
								<select name="impp_type[]">
									<option value=""{{if $impp.type.0 != 'HOME' && $impp.type.0 != 'WORK' && $impp.type.0 != 'OTHER'}} selected="selected"{{/if}}>{{$impp.type.1}}</option>
									<option value="HOME"{{if $impp.type.0 == 'HOME'}} selected="selected"{{/if}}>{{$home}}</option>
									<option value="WORK"{{if $impp.type.0 == 'WORK'}} selected="selected"{{/if}}>{{$work}}</option>
									<option value="OTHER"{{if $impp.type.0 == 'OTHER'}} selected="selected"{{/if}}>{{$other}}</option>
								</select>
								<input type="text" name="impp[]" value="{{$impp.address}}" size="{{$impp.address|count_characters:true}}" placeholder="{{$impp_label}}">
								<i data-remove="vcard-impp" data-id="{{$vcard.id}}" class="bi bi-trash remove-field drop-icons fakelink"></i>
							</div>
							{{/foreach}}
							{{/if}}
						</div>
					</div>

					<div class="settings-submit-wrapper" >
						<button type="submit" name="done" value="{{$submit}}" class="btn btn-primary">{{$submit}}</button>
					</div>
				</div>
				{{/if}}


				{{if $fields.address || $fields.locality || $fields.postal_code || $fields.region || $fields.country_name || $fields.hometown}}
				<div class="accordion-item">
					<div class="section-subtitle-wrapper" role="tab" id="location">
						<h2 class="accordion-header">
							<button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#location-collapse" aria-expanded="true" aria-controls="location-collapse">
								{{$location}}
							</a>
						</h2>
					</div>
					<div id="location-collapse" class="accordion-collapse collapse" data-bs-parent="#profile-edit-wrapper">
						<div class="section-content-tools-wrapper accordion-body">
							{{if $fields.address}}
							{{include file="field_input.tpl" field=$address}}
							{{/if}}

							{{if $fields.locality}}
							{{include file="field_input.tpl" field=$locality}}
							{{/if}}

							{{if $fields.postal_code}}
							{{include file="field_input.tpl" field=$postal_code}}
							{{/if}}

							{{if $fields.region}}
							{{include file="field_input.tpl" field=$region}}
							{{/if}}

							{{if $fields.country_name}}
							{{include file="field_input.tpl" field=$country_name}}
							{{/if}}

							{{if $fields.hometown}}
							{{include file="field_input.tpl" field=$hometown}}
							{{/if}}

							<div class="mb-3" >
							<button type="submit" name="submit" class="btn btn-primary" value="{{$submit}}">{{$submit}}</button>
							</div>
							<div class="clear"></div>
						</div>
					</div>
				</div>

				{{/if}}

				{{if $fields.marital || $fields.sexual}}
				<div class="accordion-item">
					<div class="section-subtitle-wrapper" role="tab" id="relation">
						<h2 class="accordion-header">
							<button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#relation-collapse" aria-expanded="true" aria-controls="relation-collapse">
								{{$relation}}
							</a>
						</h2>
					</div>
					<div id="relation-collapse" class="accordion-collapse collapse" data-bs-parent="#profile-edit-wrapper">
						<div class="section-content-tools-wrapper accordion-body">
							{{if $fields.marital }}
							<div id="profile-edit-marital-wrapper" class="mb-3 field" >
							<label id="profile-edit-marital-label" for="profile-edit-marital" ><span class="heart"><i class="bi fa-heart"></i>&nbsp;</span>{{$lbl_marital}}</label>
							{{if $advanced}}
							{{$marital}}
							{{else}}
							{{$marital_min}}
							{{/if}}
							</div>
							<div class="clear"></div>

							{{if $fields.partner}}
							{{include file="field_input.tpl" field=$with}}
							{{/if}}

							{{if $fields.howlong}}
							{{include file="field_input.tpl" field=$howlong}}
							{{/if}}
							{{/if}}

							{{if $fields.sexual}}
							<div id="profile-edit-sexual-wrapper" class="mb-3 field" >
							<label id="profile-edit-sexual-label" for="sexual-select" >{{$lbl_sexual}}</label>
							{{if $advanced}}
							{{$sexual}}
							{{else}}
							{{$sexual_min}}
							{{/if}}
							</div>
							<div class="clear"></div>
							{{/if}}

							<div class="mb-3" >
							<button type="submit" name="submit" class="btn btn-primary" value="{{$submit}}">{{$submit}}</button>
							</div>
							<div class="clear"></div>
						</div>
					</div>
				</div>
				{{/if}}
				{{if $fields.keywords || $fields.politic || $fields.religion || $fields.about || $fields.contact || $fields.homepage || $fields.interest || $fields.likes || $fields.dislikes || $fields.channels || $fields.music || $fields.book || $fields.tv || $fields.film || $fields.romance || $fields.employment || $fields.education || $extra_fields}}
				<div class="accordion-item">
					<div class="section-subtitle-wrapper" role="tab" id="miscellaneous">
						<h2 class="accordion-header">
							<button type="button" class="accordion-button" data-bs-toggle="collapse" data-bs-target="#miscellaneous-collapse" aria-expanded="true" aria-controls="miscellaneous-collapse">
								{{$miscellaneous}}
							</button>
						</h2>
					</div>
					<div id="miscellaneous-collapse" class="accordion-collapse collapse" data-bs-parent="#profile-edit-wrapper">
						<div class="section-content-tools-wrapper accordion-body">
							{{if $fields.homepage}}
							{{include file="field_input.tpl" field=$homepage}}
							{{/if}}

							{{if $fields.keywords}}
							{{include file="field_input.tpl" field=$keywords}}
							{{/if}}

							{{if $fields.politic}}
							{{include file="field_input.tpl" field=$politic}}
							{{/if}}

							{{if $fields.religion}}
							{{include file="field_input.tpl" field=$religion}}
							{{/if}}

							{{if $fields.about}}
							{{include file="field_textarea.tpl" field=$about}}
							{{/if}}

							{{if $fields.contact}}
							{{include file="field_textarea.tpl" field=$contact}}
							{{/if}}

							{{if $fields.interest}}
							{{include file="field_textarea.tpl" field=$interest}}
							{{/if}}

							{{if $fields.likes}}
							{{include file="field_textarea.tpl" field=$likes}}
							{{/if}}

							{{if $fields.dislikes}}
							{{include file="field_textarea.tpl" field=$dislikes}}
							{{/if}}

							{{if $fields.channels}}
							{{include file="field_textarea.tpl" field=$channels}}
							{{/if}}

							{{if $fields.music}}
							{{include file="field_textarea.tpl" field=$music}}
							{{/if}}

							{{if $fields.book}}
							{{include file="field_textarea.tpl" field=$book}}
							{{/if}}

							{{if $fields.tv}}
							{{include file="field_textarea.tpl" field=$tv}}
							{{/if}}

							{{if $fields.film}}
							{{include file="field_textarea.tpl" field=$film}}
							{{/if}}

							{{if $fields.romance}}
							{{include file="field_textarea.tpl" field=$romance}}
							{{/if}}

							{{if $fields.employment}}
							{{include file="field_textarea.tpl" field=$employ}}
							{{/if}}

							{{if $fields.education}}
							{{include file="field_textarea.tpl" field=$education}}
							{{/if}}

							{{if $extra_fields}}
							{{foreach $extra_fields as $field }}
							{{include file="field_input.tpl" field=$field}}
							{{/foreach}}
							{{/if}}
							<div class="mb-3" >
							<button type="submit" name="submit" class="btn btn-primary" value="{{$submit}}">{{$submit}}</button>
							</div>
							<div class="clear"></div>
						</div>
					</div>
				</div>
				{{/if}}
			</div>
		</form>
</div>

