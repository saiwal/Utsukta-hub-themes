<div class="generic-content-wrapper">
	<div class="section-title-wrapper app-content-header">
		{{if $notself}}
		<div class="float-end">
			<div class="btn-group">
				<button id="connection-dropdown" class="btn btn-secondary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
					<i class="bi bi-gear"></i>&nbsp;{{$tools_label}}
				</button>
				<div class="dropdown-menu dropdown-menu-end" aria-labelledby="dLabel">
					<a class="dropdown-item" href="{{$tools.view.url}}" title="{{$tools.view.title}}">{{$tools.view.label}}</a>
					<a class="dropdown-item" href="{{$tools.recent.url}}" title="{{$tools.recent.title}}">{{$tools.recent.label}}</a>
					{{if $tools.fetchvc}}
					<a class="dropdown-item" href="{{$tools.fetchvc.url}}" title="{{$tools.fetchvc.title}}">{{$tools.fetchvc.label}}</a>
					{{/if}}
					<a class="dropdown-item" href="#" title="{{$tools.refresh.title}}" onclick="window.location.href='{{$tools.refresh.url}}'; return false;">{{$tools.refresh.label}}</a>
					<a class="dropdown-item" href="#" title="{{$tools.rephoto.title}}" onclick="window.location.href='{{$tools.rephoto.url}}'; return false;">{{$tools.rephoto.label}}</a>
					<div class="dropdown-divider"></div>
					<a class="dropdown-item" href="#" title="{{$tools.block.title}}" onclick="window.location.href='{{$tools.block.url}}'; return false;">{{$tools.block.label}}</a>
					<a class="dropdown-item" href="#" title="{{$tools.ignore.title}}" onclick="window.location.href='{{$tools.ignore.url}}'; return false;">{{$tools.ignore.label}}</a>
					<a class="dropdown-item" href="#" title="{{$tools.archive.title}}" onclick="window.location.href='{{$tools.archive.url}}'; return false;">{{$tools.archive.label}}</a>					<a class="dropdown-item" href="#" title="{{$tools.hide.title}}" onclick="window.location.href='{{$tools.hide.url}}'; return false;">{{$tools.hide.label}}</a>
					<a class="dropdown-item" href="#" title="{{$tools.delete.title}}" onclick="window.location.href='{{$tools.delete.url}}'; return false;">{{$tools.delete.label}}</a>
				</div>
			</div>
			{{if $abook_prev || $abook_next}}
			<div class="btn-group">
				<a href="connedit/{{$abook_prev}}{{if $section}}?f=&section={{$section}}{{/if}}" class="btn btn-secondary btn-sm{{if ! $abook_prev}} disabled{{/if}}" ><i class="bi fa-backward"></i></a>
				{{if $sections}}
				<div class="btn-group" >
					<button class="btn btn-secondary btn-sm{{if $is_pending}} disabled{{/if}}" type="button" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false"><i class="bi fa-bars"></i></button>
					<div class="dropdown-menu dropdown-menu-end" aria-labelledby="dLabel">
						{{foreach $sections as $s}}
						<a class="dropdown-item" href="{{$s.url}}" title="{{$s.title}}">{{$s.label}}</a>
						{{/foreach}}
					</div>
				</div>
				{{/if}}
				<a href="connedit/{{$abook_next}}{{if $section}}?f=&section={{$section}}{{/if}}" class="btn btn-secondary btn-sm{{if ! $abook_next}} disabled{{/if}}" ><i class="bi fa-forward"></i></a>
			</div>
			{{/if}}
		</div>
		{{/if}}
		<h3>{{$header}}</h3>
	</div>
	<div class="section-content-wrapper-np">
		<form id="abook-edit-form" action="connedit/{{$contact_id}}" method="post" >

		<input type="hidden" name="contact_id" value="{{$contact_id}}">
		<input type="hidden" name="section" value="{{$section}}">

		<div class="section-content-wrapper">
			<a href="permcats/{{$permcat_value}}" class="float-end"><i class="bi bi-box-arrow-up-right"></i>&nbsp;{{$permcat_new}}</a>
			{{include file="field_select.tpl" field=$permcat}}
			<button type="button" class="btn btn-secondary float-end" data-bs-toggle="modal" data-bs-target="#perms_modal">Permissions</button>
			<button type="submit" name="done" value="{{$submit}}" class="btn btn-primary">{{$submit}}</button>
			<div class="modal" id="perms_modal" tabindex="-1" aria-labelledby="perms_modal_label" aria-hidden="true">
				<div class="modal-dialog">
					<div class="modal-content">
						<div class="modal-header">
							<div class="modal-title h3" id="perms_modal_label">Permissions Overview</div>
							<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
						</div>
						<div class="modal-body">

							<table class="table table-hover table-sm">
								<thead>
									<tr>
										<th scope="col">Permission</th>
										<th scope="col">{{$them}}</th>
										<th scope="col">{{$me}}</th>
									</tr>
								</thead>
								<tbody>
									{{foreach $perms as $perm}}
									<tr>
										<td>{{$perm.1}}</td>
										<td>
											{{if $perm.2}}
											<i class="bi bi-check-lg text-success"></i>
											{{else}}
											<i class="bi bi-x-lg text-danger"></i>
											{{/if}}
										</td>
										<td>
											{{if $perm.3}}
											<i class="bi bi-check-lg text-success"></i>
											{{else}}
											<i class="bi bi-x-lg text-danger"></i>
											{{/if}}
										</td>
									</tr>
									{{/foreach}}

								</tbody>
							</table>
						</div>
						<div class="modal-footer">
							<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
						</div>
					</div>
				</div>
			</div>
		</div>

		{{if $notself}}
		{{foreach $tools as $tool}}
		{{if $tool.info}}
		<div class="section-content-danger-wrapper">
			<div>
				{{$tool.info}}
			</div>
		</div>
		{{/if}}
		{{/foreach}}
		<div class="section-content-info-wrapper">
			<div>
				{{$addr_text}} <strong>'{{if $addr}}{{$addr}}{{else}}{{$primeurl}}{{/if}}'</strong>
			</div>
			{{if $locstr}}
			<div>
				{{$loc_text}} {{$locstr}}
			</div>
			{{/if}}
			{{if $unclonable}}
			<div>
				<br>{{$unclonable}}
			</div>
			<br>
			{{/if}}
			{{if $last_update}}
			<div>
				{{$lastupdtext}} {{$last_update}}
			</div>
			{{/if}}
		</div>
		{{/if}}

		<div class="panel-group" id="contact-edit-tools" role="tablist" aria-multiselectable="true">
			{{if $notself}}

			{{if ! $is_pending}}
			<div id="template-form-vcard-org" class="mb-3 form-vcard-org">
				<div class="mb-3 form-vcard-org">
					<input type="text" name="org" value="" placeholder="{{$org_label}}">
					<i data-remove="vcard-org" data-id="" class="bi bi-trash remove-field drop-icons fakelink"></i>
				</div>
			</div>

			<div id="template-form-vcard-title" class="mb-3 form-vcard-title">
				<div class="mb-3 form-vcard-title">
					<input type="text" name="title" value="" placeholder="{{$title_label}}">
					<i data-remove="vcard-title" data-id="" class="bi bi-trash remove-field drop-icons fakelink"></i>
				</div>
			</div>

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

			<div id="template-form-vcard-url" class="mb-3 form-vcard-url">
				<select name="url_type[]">
					<option value="HOME">{{$home}}</option>
					<option value="WORK">{{$work}}</option>
					<option value="OTHER">{{$other}}</option>
				</select>
				<input type="text" name="url[]" value="" placeholder="{{$url_label}}">
				<i data-remove="vcard-url" data-id="" class="bi bi-trash remove-field drop-icons fakelink"></i>
			</div>

			<div id="template-form-vcard-adr" class="mb-3 form-vcard-adr">
				<div class="mb-3">
					<select name="adr_type[]">
						<option value="HOME">{{$home}}</option>
						<option value="WORK">{{$work}}</option>
						<option value="OTHER">{{$other}}</option>
					</select>
					<label>{{$adr_label}}</label>
					<i data-remove="vcard-adr" data-id="" class="bi bi-trash remove-field drop-icons fakelink"></i>
				</div>
				<div class="mb-3">
					<input type="text" name="" value="" placeholder="{{$po_box}}">
				</div>
				<div class="mb-3">
					<input type="text" name="" value="" placeholder="{{$extra}}">
				</div>
				<div class="mb-3">
					<input type="text" name="" value="" placeholder="{{$street}}">
				</div>
				<div class="mb-3">
					<input type="text" name="" value="" placeholder="{{$locality}}">
				</div>
				<div class="mb-3">
					<input type="text" name="" value="" placeholder="{{$region}}">
				</div>
				<div class="mb-3">
					<input type="text" name="" value="" placeholder="{{$zip_code}}">
				</div>
				<div class="mb-3">
					<input type="text" name="" value="" placeholder="{{$country}}">
				</div>
			</div>

			<div id="template-form-vcard-note" class="mb-3 form-vcard-note">
				<label>{{$note_label}}</label>
				<i data-remove="vcard-note" data-id="" class="bi bi-trash remove-field drop-icons fakelink"></i>
				<textarea name="note" class="form-control"></textarea>
			</div>

			<div class="section-content-wrapper-np">
				<div id="vcard-cancel-{{$vcard.id}}" class="vcard-cancel vcard-cancel-btn" data-id="{{$vcard.id}}" data-action="cancel"><i class="bi bi-x-lg"></i></div>
				<div id="vcard-add-field-{{$vcard.id}}" class="dropdown float-end vcard-add-field">
					<button data-bs-toggle="dropdown" type="button" class="btn btn-secondary btn-sm dropdown-toggle"><i class="bi bi-plus-lg"></i> {{$add_field}}</button>
					<ul class="dropdown-menu">
						<li class="add-vcard-org"{{if $vcard.org}} style="display: none"{{/if}}><a href="#" data-add="vcard-org" data-id="{{$vcard.id}}" class="add-field" onclick="return false;">{{$org_label}}</a></li>
						<li class="add-vcard-title"{{if $vcard.title}} style="display: none"{{/if}}><a href="#" data-add="vcard-title" data-id="{{$vcard.id}}" class="add-field" onclick="return false;">{{$title_label}}</a></li>
						<li class="add-vcard-tel"><a href="#" data-add="vcard-tel" data-id="{{$vcard.id}}" class="add-field" onclick="return false;">{{$tel_label}}</a></li>
						<li class="add-vcard-email"><a href="#" data-add="vcard-email" data-id="{{$vcard.id}}" class="add-field" onclick="return false;">{{$email_label}}</a></li>
						<li class="add-vcard-impp"><a href="#" data-add="vcard-impp" data-id="{{$vcard.id}}" class="add-field" onclick="return false;">{{$impp_label}}</a></li>
						<li class="add-vcard-url"><a href="#" data-add="vcard-url" data-id="{{$vcard.id}}" class="add-field" onclick="return false;">{{$url_label}}</a></li>
						<li class="add-vcard-adr"><a href="#" data-add="vcard-adr" data-id="{{$vcard.id}}" class="add-field" onclick="return false;">{{$adr_label}}</a></li>
						<li class="add-vcard-note"{{if $vcard.note}} style="display: none"{{/if}}><a href="#" data-add="vcard-note" data-id="{{$vcard.id}}" class="add-field" onclick="return false;">{{$note_label}}</a></li>
					</ul>
				</div>
				<div id="vcard-header-{{$vcard.id}}" class="vcard-header" data-id="{{$vcard.id}}" data-action="open">
					<i class="vcard-fn-preview bi fa-address-card-o"></i>
					<span id="vcard-preview-{{$vcard.id}}" class="vcard-preview">
						{{if $vcard.fn}}<span class="vcard-fn-preview">{{$vcard.fn}}</span>{{/if}}
						{{if $vcard.emails.0.address}}<span class="vcard-email-preview d-none d-md-table-cell"><a href="mailto:{{$vcard.emails.0.address}}">{{$vcard.emails.0.address}}</a></span>{{/if}}
						{{if $vcard.tels.0}}<span class="vcard-tel-preview d-none d-md-table-cell">{{$vcard.tels.0.nr}} <a class="btn btn-secondary btn-sm" href="tel:{{$vcard.tels.0.nr}}"><i class="bi fa-phone connphone"></i></a></span>{{/if}}
					</span>
					<input id="vcard-fn-{{$vcard.id}}" class="vcard-fn" type="text" name="fn" value="{{$vcard.fn}}" size="{{$vcard.fn|count_characters:true}}" placeholder="{{$name_label}}">
				</div>
			</div>
			<div id="vcard-info-{{$vcard.id}}" class="vcard-info section-content-wrapper">

				<div class="vcard-org mb-3">
					<div class="form-vcard-org-wrapper">
						{{if $vcard.org}}
						<div class="mb-3 form-vcard-org">
							<input type="text" name="org" value="{{$vcard.org}}" size="{{$vcard.org|count_characters:true}}" placeholder="{{$org_label}}">
							<i data-remove="vcard-org" data-id="{{$vcard.id}}" class="bi bi-trash remove-field drop-icons fakelink"></i>
						</div>
						{{/if}}
					</div>
				</div>

				<div class="vcard-title mb-3">
					<div class="form-vcard-title-wrapper">
						{{if $vcard.title}}
						<div class="mb-3 form-vcard-title">
							<input type="text" name="title" value="{{$vcard.title}}" size="{{$vcard.title|count_characters:true}}" placeholder="{{$title_label}}">
							<i data-remove="vcard-title" data-id="{{$vcard.id}}" class="bi bi-trash remove-field drop-icons fakelink"></i>
						</div>
						{{/if}}
					</div>
				</div>


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

				<div class="vcard-url mb-3">
					<div class="form-vcard-url-wrapper">
						{{if $vcard.urls}}
						{{foreach $vcard.urls as $url}}
						<div class="mb-3 form-vcard-url">
							<select name="url_type[]">
								<option value=""{{if $url.type.0 != 'HOME' && $url.type.0 != 'WORK' && $url.type.0 != 'OTHER'}} selected="selected"{{/if}}>{{$url.type.1}}</option>
								<option value="HOME"{{if $url.type.0 == 'HOME'}} selected="selected"{{/if}}>{{$home}}</option>
								<option value="WORK"{{if $url.type.0 == 'WORK'}} selected="selected"{{/if}}>{{$work}}</option>
								<option value="OTHER"{{if $url.type.0 == 'OTHER'}} selected="selected"{{/if}}>{{$other}}</option>
							</select>
							<input type="text" name="url[]" value="{{$url.address}}" size="{{$url.address|count_characters:true}}" placeholder="{{$url_label}}">
							<i data-remove="vcard-url" data-id="{{$vcard.id}}" class="bi bi-trash remove-field drop-icons fakelink"></i>
						</div>
						{{/foreach}}
						{{/if}}
					</div>
				</div>

				<div class="vcard-adr mb-3">
					<div class="form-vcard-adr-wrapper">
						{{if $vcard.adrs}}
						{{foreach $vcard.adrs as $adr}}
						<div class="mb-3 form-vcard-adr">
							<div class="mb-3">
								<label>{{$adr_label}}</label>
								<select name="adr_type[]">
									<option value=""{{if $adr.type.0 != 'HOME' && $adr.type.0 != 'WORK' && $adr.type.0 != 'OTHER'}} selected="selected"{{/if}}>{{$adr.type.1}}</option>
									<option value="HOME"{{if $adr.type.0 == 'HOME'}} selected="selected"{{/if}}>{{$home}}</option>
									<option value="WORK"{{if $adr.type.0 == 'WORK'}} selected="selected"{{/if}}>{{$work}}</option>
									<option value="OTHER"{{if $adr.type.0 == 'OTHER'}} selected="selected"{{/if}}>{{$other}}</option>
								</select>
								<i data-remove="vcard-adr" data-id="{{$vcard.id}}" class="bi bi-trash remove-field drop-icons fakelink"></i>
							</div>
							<div class="mb-3">
								<input type="text" name="adr[{{$adr@index}}][]" value="{{$adr.address.0}}" size="{{$adr.address.0|count_characters:true}}" placeholder="{{$po_box}}">
							</div>
							<div class="mb-3">
								<input type="text" name="adr[{{$adr@index}}][]" value="{{$adr.address.1}}" size="{{$adr.address.1|count_characters:true}}" placeholder="{{$extra}}">
							</div>
							<div class="mb-3">
								<input type="text" name="adr[{{$adr@index}}][]" value="{{$adr.address.2}}" size="{{$adr.address.2|count_characters:true}}" placeholder="{{$street}}">
							</div>
							<div class="mb-3">
								<input type="text" name="adr[{{$adr@index}}][]" value="{{$adr.address.3}}" size="{{$adr.address.3|count_characters:true}}" placeholder="{{$locality}}">
							</div>
							<div class="mb-3">
								<input type="text" name="adr[{{$adr@index}}][]" value="{{$adr.address.4}}" size="{{$adr.address.4|count_characters:true}}" placeholder="{{$region}}">
							</div>
							<div class="mb-3">
								<input type="text" name="adr[{{$adr@index}}][]" value="{{$adr.address.5}}" size="{{$adr.address.5|count_characters:true}}" placeholder="{{$zip_code}}">
							</div>
							<div class="mb-3">
								<input type="text" name="adr[{{$adr@index}}][]" value="{{$adr.address.6}}" size="{{$adr.address.6|count_characters:true}}" placeholder="{{$country}}">
							</div>
						</div>
						{{/foreach}}
						{{/if}}
					</div>
				</div>

				<div class="vcard-note mb-3 form-vcard-note">
					<div class="form-vcard-note-wrapper">
						{{if $vcard.note}}
						<label>{{$note_label}}</label>
						<i data-remove="vcard-note" data-id="{{$vcard.id}}" class="bi bi-trash remove-field drop-icons fakelink"></i>
						<textarea name="note" class="form-control">{{$vcard.note}}</textarea>
						{{/if}}
					</div>
				</div>


				<div class="settings-submit-wrapper" >
					<button type="submit" name="done" value="{{$submit}}" class="btn btn-primary">{{$submit}}</button>
				</div>

			</div>
			{{/if}}

			{{if $affinity}}
			<div class="panel">
				<div class="section-subtitle-wrapper" role="tab" id="affinity-tool">
					<h3>
						<a data-bs-toggle="collapse" data-bs-parent="#contact-edit-tools" href="#affinity-tool-collapse" aria-expanded="true" aria-controls="affinity-tool-collapse">
							{{$affinity}}
						</a>
					</h3>
				</div>
				<div id="affinity-tool-collapse" class="panel-collapse collapse{{if $section == 'affinity'}} show{{/if}}" role="tabpanel" aria-labelledby="affinity-tool">
					<div class="section-content-tools-wrapper">
						{{if $slide}}
						<div class="mb-3"><strong>{{$lbl_slider}}</strong></div>
						{{$slide}}
						<input id="contact-closeness-mirror" type="hidden" name="closeness" value="{{$close}}" />
						{{/if}}

						{{if $multiprofs}}
						<div class="mb-3">
							<strong>{{$lbl_vis2}}</strong>
							{{$profile_select}}
						</div>
						{{/if}}
						<div class="settings-submit-wrapper" >
							<button type="submit" name="done" value="{{$submit}}" class="btn btn-primary">{{$submit}}</button>
						</div>
					</div>
				</div>
			</div>
			{{/if}}

			{{if $connfilter}}
			<div class="panel">
				<div class="section-subtitle-wrapper" role="tab" id="fitert-tool">
					<h3>
						<a data-bs-toggle="collapse" data-bs-parent="#contact-edit-tools" href="#fitert-tool-collapse" aria-expanded="true" aria-controls="fitert-tool-collapse">
							{{$connfilter_label}}
						</a>
					</h3>
				</div>
				<div id="fitert-tool-collapse" class="panel-collapse collapse{{if $section == 'filter' }} show{{/if}}" role="tabpanel" aria-labelledby="fitert-tool">
					<div class="section-content-tools-wrapper">
						{{include file="field_textarea.tpl" field=$incl}}
						{{include file="field_textarea.tpl" field=$excl}}
						<div class="settings-submit-wrapper" >
							<button type="submit" name="done" value="{{$submit}}" class="btn btn-primary">{{$submit}}</button>
						</div>
					</div>
				</div>
			</div>
			{{else}}
			<input type="hidden" name="{{$incl.0}}" value="{{$incl.2}}" />
			<input type="hidden" name="{{$excl.0}}" value="{{$excl.2}}" />
			{{/if}}

			{{/if}}
		</div>
		</form>
	</div>
</div>
