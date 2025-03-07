<script>
$(document).ready(function() {

	$(document).on('click', '.vcard-header, .vcard-cancel-btn', updateView);
	$(document).on('click', '.add-field', doAdd);
	$(document).on('click', '.remove-field', doRemove);

	function updateView() {
		var id = $(this).data('id');
		var action = $(this).data('action');
		var header = $('#vcard-header-' + id);
		var cancel = $('#vcard-cancel-' + id);
		var addField = $('#vcard-add-field-' + id);
		var info = $('#vcard-info-' + id);
		var vcardPreview = $('#vcard-preview-' + id);
		var fn = $('#vcard-fn-' + id);

		if(action === 'open') {
			$(header).addClass('active');
			$(cancel).show();
			$(addField).show();
			$(info).show();
			$(fn).show();
			$(vcardPreview).hide();
		}
		else {
			$(header).removeClass('active');
			$(cancel).hide();
			$(addField).hide();
			$(info).hide();
			$(fn).hide();
			$(vcardPreview).show();
		}
	}

	function doAdd(e) {
		e.preventDefault();
		var what = $(this).data('add');
		var id = $(this).data('id');
		var element = '#template-form-' + what;
		var where = '#card_form_' + id;

		$(element + ' .remove-field').attr('data-id', id)

		if(what === 'vcard-adr') {
			var adrCount = $(where + ' .form-' + what).length;
			var attrName = 'adr[' + adrCount + '][]';
			$(element + ' input').attr('name', attrName);
		}

		if(what === 'vcard-org' || what === 'vcard-title' || what === 'vcard-note') {
			$(where + ' .add-' + what).hide()
		}

		$(element).clone().removeAttr('id').appendTo(where + ' .form-' + what + '-wrapper');
	}

	function doRemove() {
		var what = $(this).data('remove');
		var element = $(this).parents('div.form-' + what);
		var where = '#card_form_' + $(this).data('id');

		if(what === 'vcard-org' || what === 'vcard-title' || what === 'vcard-note') {
			$(where + ' .add-' + what).show()
		}

		$(element).remove();
	}

});
</script>
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

<div class="generic-content-wrapper">
	<div class="section-title-wrapper app-content-header">
		<button type="button" class="btn btn-success btn-sm float-end" onclick="openClose('create_form')"><i class="bi bi-plus-lg"></i> {{$add_card}}</button>
		<h3>{{$displayname}}</h3>
	</div>
	<div id="create_form" class="section-content-tools-wrapper">
		<form id="card_form_new" method="post" action="">
			<input type="hidden" name="target" value="{{$id}}">
			<div class="dropdown float-end">
				<button data-bs-toggle="dropdown" type="button" class="btn btn-outline-secondary btn-sm dropdown-toggle"><i class="bi bi-plus-lg"></i> {{$add_field}}</button>
				<div class="dropdown-menu dropdown-menu-end">
					<a class="dropdown-item add-vcard-org add-field" style="display: none" href="#" data-add="vcard-org" data-id="new">{{$org_label}}</a>
					<a class="dropdown-item add-vcard-title add-field" style="display: none" href="#" data-add="vcard-title" data-id="new">{{$title_label}}</a>
					<a class="dropdown-item add-vcard-tel add-field" href="#" data-add="vcard-tel" data-id="new">{{$tel_label}}</a>
					<a class="dropdown-item add-vcard-email add-field" href="#" data-add="vcard-email" data-id="new">{{$email_label}}</a>
					<a class="dropdown-item add-vcard-impp add-field" href="#" data-add="vcard-impp" data-id="new">{{$impp_label}}</a>
					<a class="dropdown-item add-vcard-url add-field" href="#" data-add="vcard-url" data-id="new">{{$url_label}}</a>
					<a class="dropdown-item add-vcard-adr add-field" href="#" data-add="vcard-adr" data-id="new">{{$adr_label}}</a>
					<a class="dropdown-item add-vcard-note add-field" href="#" data-add="vcard-note" data-id="new">{{$note_label}}</a>
				</div>
			</div>

			<div class="vcard-fn-create mb-3">
				<div class="form-vcard-fn-wrapper">
					<div class="mb-3 form-vcard-fn">
						<div class="vcard-nophoto"><i class="bi bi-person"></i></div><input type="text" name="fn" value="" placeholder="{{$name_label}}">
					</div>
				</div>
			</div>

			<div class="vcard-org mb-3">
				<div class="form-vcard-org-wrapper">
					<div class="mb-3 form-vcard-org">
						<input type="text" name="org" value="" placeholder="{{$org_label}}">
						<i data-remove="vcard-org" data-id="new" class="bi bi-trash remove-field drop-icons fakelink"></i>
					</div>
				</div>
			</div>

			<div class="vcard-title mb-3">
				<div class="form-vcard-title-wrapper">
					<div class="mb-3 form-vcard-title">
						<input type="text" name="title" value="" placeholder="{{$title_label}}">
						<i data-remove="vcard-title" data-id="new" class="bi bi-trash remove-field drop-icons fakelink"></i>
					</div>
				</div>
			</div>

			<div class="vcard-tel mb-3">
				<div class="form-vcard-tel-wrapper">
					<div class="mb-3 form-vcard-tel">
						<select name="tel_type[]">
							<option value="CELL">{{$mobile}}</option>
							<option value="HOME">{{$home}}</option>
							<option value="WORK">{{$work}}</option>
							<option value="OTHER">{{$other}}</option>
						</select>
						<input type="text" name="tel[]" value="" placeholder="{{$tel_label}}">
						<i data-remove="vcard-tel" data-id="new" class="bi bi-trash remove-field drop-icons fakelink"></i>
					</div>
				</div>
			</div>


			<div class="vcard-email mb-3">
				<div class="form-vcard-email-wrapper">
					<div class="mb-3 form-vcard-email">
						<select name="email_type[]">
							<option value="HOME">{{$home}}</option>
							<option value="WORK">{{$work}}</option>
							<option value="OTHER">{{$other}}</option>
						</select>
						<input type="text" name="email[]" value="" placeholder="{{$email_label}}">
						<i data-remove="vcard-email" data-id="new" class="bi bi-trash remove-field drop-icons fakelink"></i>
					</div>
				</div>
			</div>

			<div class="vcard-impp mb-3">
				<div class="form-vcard-impp-wrapper">
				</div>
			</div>

			<div class="vcard-url mb-3">
				<div class="form-vcard-url-wrapper">
				</div>
			</div>

			<div class="vcard-adr mb-3">
				<div class="form-vcard-adr-wrapper">
				</div>
			</div>

			<div class="vcard-note mb-3">
				<div class="form-vcard-note-wrapper">
				</div>
			</div>

			<button type="submit" name="create" value="create_card" class="btn btn-primary btn-sm float-end">{{$create}}</button>
			<button type="button" class="btn btn-outline-secondary btn-sm" onclick="openClose('create_form')">{{$cancel}}</button>
			<div class="clear"></div>
		</form>
	</div>

	{{foreach $cards as $card}}
	<form id="card_form_{{$card.id}}" method="post" action="">
		<input type="hidden" name="target" value="{{$id}}">
		<input type="hidden" name="uri" value="{{$card.uri}}">
		<div class="section-content-wrapper-np">
			<div id="vcard-cancel-{{$card.id}}" class="vcard-cancel vcard-cancel-btn" data-id="{{$card.id}}" data-action="cancel"><i class="bi bi-x-lg"></i></div>
			<div id="vcard-add-field-{{$card.id}}" class="dropdown float-end vcard-add-field">
				<button data-bs-toggle="dropdown" type="button" class="btn btn-outline-secondary btn-sm dropdown-toggle"><i class="bi bi-plus-lg"></i> {{$add_field}}</button>
				<div class="dropdown-menu dropdown-menu-end">
					<a class="dropdown-item add-vcard-org add-field"{{if $card.org}} style="display: none"{{/if}} href="#" data-add="vcard-org" data-id="{{$card.id}}">{{$org_label}}</a>
					<a class="dropdown-item add-vcard-title add-field"{{if $card.title}} style="display: none"{{/if}} href="#" data-add="vcard-title" data-id="{{$card.id}}">{{$title_label}}</a>
					<a class="dropdown-item add-vcard-tel add-field" href="#" data-add="vcard-tel" data-id="{{$card.id}}">{{$tel_label}}</a>
					<a class="dropdown-item add-vcard-email add-field" href="#" data-add="vcard-email" data-id="{{$card.id}}">{{$email_label}}</a>
					<a class="dropdown-item add-vcard-impp add-field" href="#" data-add="vcard-impp" data-id="{{$card.id}}">{{$impp_label}}</a>
					<a class="dropdown-item add-vcard-url add-field" href="#" data-add="vcard-url" data-id="{{$card.id}}">{{$url_label}}</a>
					<a class="dropdown-item add-vcard-adr add-field" href="#" data-add="vcard-adr" data-id="{{$card.id}}">{{$adr_label}}</a>
					<a class="dropdown-item add-vcard-note add-field"{{if $card.note}} style="display: none"{{/if}} href="#" data-add="vcard-note" data-id="{{$card.id}}">{{$note_label}}</a>
				</div>
			</div>
			<div id="vcard-header-{{$card.id}}" class="vcard-header" data-id="{{$card.id}}" data-action="open">
				{{if $card.photo}}<img class="vcard-photo" src="{{$card.photo}}" width="32px" height="32px">{{else}}<div class="vcard-nophoto"><i class="bi bi-person"></i></div>{{/if}}
				<span id="vcard-preview-{{$card.id}}" class="vcard-preview">
					{{if $card.fn}}<span class="vcard-fn-preview">{{$card.fn}}</span>{{/if}}
					{{if $card.emails.0.address}}<span class="vcard-email-preview hidden-xs">{{$card.emails.0.address}}</span>{{/if}}
					{{if $card.tels.0}}<span class="vcard-tel-preview hidden-xs">{{$card.tels.0.nr}}</span>{{/if}}
				</span>
				<input id="vcard-fn-{{$card.id}}" class="vcard-fn" type="text" name="fn" value="{{$card.fn}}" size="{{$card.fn|count_characters:true}}" placeholder="{{$name_label}}">
			</div>
		</div>
		<div id="vcard-info-{{$card.id}}" class="vcard-info section-content-wrapper">

			<div class="vcard-org mb-3">
				<div class="form-vcard-org-wrapper">
					{{if $card.org}}
					<div class="mb-3 form-vcard-org">
						<input type="text" name="org" value="{{$card.org}}" size="{{$card.org|count_characters:true}}" placeholder="{{$org_label}}">
						<i data-remove="vcard-org" data-id="{{$card.id}}" class="bi bi-trash remove-field drop-icons fakelink"></i>
					</div>
					{{/if}}
				</div>
			</div>

			<div class="vcard-title mb-3">
				<div class="form-vcard-title-wrapper">
					{{if $card.title}}
					<div class="mb-3 form-vcard-title">
						<input type="text" name="title" value="{{$card.title}}" size="{{$card.title|count_characters:true}}" placeholder="{{$title_label}}">
						<i data-remove="vcard-title" data-id="{{$card.id}}" class="bi bi-trash remove-field drop-icons fakelink"></i>
					</div>
					{{/if}}
				</div>
			</div>


			<div class="vcard-tel mb-3">
				<div class="form-vcard-tel-wrapper">
					{{if $card.tels}}
					{{foreach $card.tels as $tel}}
					<div class="mb-3 form-vcard-tel">
						<select name="tel_type[]">
							<option value=""{{if $tel.type.0 != 'CELL' && $tel.type.0 != 'HOME' && $tel.type.0 != 'WORK' && $tel.type.0 != 'OTHER'}} selected="selected"{{/if}}>{{$tel.type.1}}</option>
							<option value="CELL"{{if $tel.type.0 == 'CELL'}} selected="selected"{{/if}}>{{$mobile}}</option>
							<option value="HOME"{{if $tel.type.0 == 'HOME'}} selected="selected"{{/if}}>{{$home}}</option>
							<option value="WORK"{{if $tel.type.0 == 'WORK'}} selected="selected"{{/if}}>{{$work}}</option>
							<option value="OTHER"{{if $tel.type.0 == 'OTHER'}} selected="selected"{{/if}}>{{$other}}</option>
						</select>
						<input type="text" name="tel[]" value="{{$tel.nr}}" size="{{$tel.nr|count_characters:true}}" placeholder="{{$tel_label}}">
						<i data-remove="vcard-tel" data-id="{{$card.id}}" class="bi bi-trash remove-field drop-icons fakelink"></i>
					</div>
					{{/foreach}}
					{{/if}}
				</div>
			</div>


			<div class="vcard-email mb-3">
				<div class="form-vcard-email-wrapper">
					{{if $card.emails}}
					{{foreach $card.emails as $email}}
					<div class="mb-3 form-vcard-email">
						<select name="email_type[]">
							<option value=""{{if $email.type.0 != 'HOME' && $email.type.0 != 'WORK' && $email.type.0 != 'OTHER'}} selected="selected"{{/if}}>{{$email.type.1}}</option>
							<option value="HOME"{{if $email.type.0 == 'HOME'}} selected="selected"{{/if}}>{{$home}}</option>
							<option value="WORK"{{if $email.type.0 == 'WORK'}} selected="selected"{{/if}}>{{$work}}</option>
							<option value="OTHER"{{if $email.type.0 == 'OTHER'}} selected="selected"{{/if}}>{{$other}}</option>
						</select>
						<input type="text" name="email[]" value="{{$email.address}}" size="{{$email.address|count_characters:true}}" placeholder="{{$email_label}}">
						<i data-remove="vcard-email" data-id="{{$card.id}}" class="bi bi-trash remove-field drop-icons fakelink"></i>
					</div>
					{{/foreach}}
					{{/if}}
				</div>
			</div>

			<div class="vcard-impp mb-3">
				<div class="form-vcard-impp-wrapper">
					{{if $card.impps}}
					{{foreach $card.impps as $impp}}
					<div class="mb-3 form-vcard-impp">
						<select name="impp_type[]">
							<option value=""{{if $impp.type.0 != 'HOME' && $impp.type.0 != 'WORK' && $impp.type.0 != 'OTHER'}} selected="selected"{{/if}}>{{$impp.type.1}}</option>
							<option value="HOME"{{if $impp.type.0 == 'HOME'}} selected="selected"{{/if}}>{{$home}}</option>
							<option value="WORK"{{if $impp.type.0 == 'WORK'}} selected="selected"{{/if}}>{{$work}}</option>
							<option value="OTHER"{{if $impp.type.0 == 'OTHER'}} selected="selected"{{/if}}>{{$other}}</option>
						</select>
						<input type="text" name="impp[]" value="{{$impp.address}}" size="{{$impp.address|count_characters:true}}" placeholder="{{$impp_label}}">
						<i data-remove="vcard-impp" data-id="{{$card.id}}" class="bi bi-trash remove-field drop-icons fakelink"></i>
					</div>
					{{/foreach}}
					{{/if}}
				</div>
			</div>

			<div class="vcard-url mb-3">
				<div class="form-vcard-url-wrapper">
					{{if $card.urls}}
					{{foreach $card.urls as $url}}
					<div class="mb-3 form-vcard-url">
						<select name="url_type[]">
							<option value=""{{if $url.type.0 != 'HOME' && $url.type.0 != 'WORK' && $url.type.0 != 'OTHER'}} selected="selected"{{/if}}>{{$url.type.1}}</option>
							<option value="HOME"{{if $url.type.0 == 'HOME'}} selected="selected"{{/if}}>{{$home}}</option>
							<option value="WORK"{{if $url.type.0 == 'WORK'}} selected="selected"{{/if}}>{{$work}}</option>
							<option value="OTHER"{{if $url.type.0 == 'OTHER'}} selected="selected"{{/if}}>{{$other}}</option>
						</select>
						<input type="text" name="url[]" value="{{$url.address}}" size="{{$url.address|count_characters:true}}" placeholder="{{$url_label}}">
						<i data-remove="vcard-url" data-id="{{$card.id}}" class="bi bi-trash remove-field drop-icons fakelink"></i>
					</div>
					{{/foreach}}
					{{/if}}
				</div>
			</div>

			<div class="vcard-adr mb-3">
				<div class="form-vcard-adr-wrapper">
					{{if $card.adrs}}
					{{foreach $card.adrs as $adr}}
					<div class="mb-3 form-vcard-adr">
						<div class="mb-3">
							<label>{{$adr_label}}</label>
							<select name="adr_type[]">
								<option value=""{{if $adr.type.0 != 'HOME' && $adr.type.0 != 'WORK' && $adr.type.0 != 'OTHER'}} selected="selected"{{/if}}>{{$adr.type.1}}</option>
								<option value="HOME"{{if $adr.type.0 == 'HOME'}} selected="selected"{{/if}}>{{$home}}</option>
								<option value="WORK"{{if $adr.type.0 == 'WORK'}} selected="selected"{{/if}}>{{$work}}</option>
								<option value="OTHER"{{if $adr.type.0 == 'OTHER'}} selected="selected"{{/if}}>{{$other}}</option>
							</select>
							<i data-remove="vcard-adr" data-id="{{$card.id}}" class="bi bi-trash remove-field drop-icons fakelink"></i>
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
					{{if $card.note}}
					<label>{{$note_label}}</label>
					<i data-remove="vcard-note" data-id="{{$card.id}}" class="bi bi-trash remove-field drop-icons fakelink"></i>
					<textarea name="note" class="form-control">{{$card.note}}</textarea>
					{{/if}}
				</div>
			</div>


			<button type="submit" name="update" value="update_card" class="btn btn-primary btn-sm float-end">{{$update}}</button>
			<button type="submit" name="delete" value="delete_card" class="btn btn-danger btn-sm">{{$delete}}</button>
			<button type="button" class="btn btn-outline-secondary btn-sm vcard-cancel-btn" data-id="{{$card.id}}" data-action="cancel">{{$cancel}}</button>
			<div class="clear"></div>
		</div>
	</form>
	{{/foreach}}
</div>
