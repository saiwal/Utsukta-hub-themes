{{if $app}}
<div id="personal-notes" class="generic-content-wrapper{{if $hidden}} d-none{{/if}}">
	<div class="section-title-wrapper clearfix app-content-header">
		<div class="float-end rounded border border-secondary m-1 ps-1 pe-1  text-muted small note-mode" title="Double click into note for edit mode">{{$strings.read}}</div>
		<h3>{{$strings.title}}</h3>
	</div>
	<div class="section-content-wrapper-np">
{{else}}
<div id="personal-notes" class="widget{{if $hidden}} d-none{{/if}}">
	<div class="float-end rounded border border-secondary mb-1 ps-1 pe-1 text-muted small note-mode" title="Double click note for edit mode">{{$strings.read}}</div>
	<h3 class="float-start">{{$strings.title}}</h3>
{{/if}}
	<textarea name="note_text" id="note-text" class="form-control{{if $app}} border-0{{else}} p-1{{/if}}" style="display: none;">{{$text}}</textarea>
	<div id="note-text-html" class="{{if !$app}}border rounded p-1{{/if}}">{{$html}}</div>
	<script>
		var noteSaveTimer = null;
		var noteText = $('#note-text');
		var noteTextHTML = $('#note-text-html');
		var noteMode = $('.note-mode');
		var noteEditing = false;

		noteText.bbco_autocomplete('bbcode');

		$(document).on('focusout',"#note-text",function(e){
			if(noteSaveTimer)
				clearTimeout(noteSaveTimer);

			noteEditing = false;
			notePostFinal();
			noteSaveTimer = null;
			setNoteMode(noteMode, 'saving');
		});

		$(document).on('dblclick',"#note-text-html",function(e){
			noteEditing = 1;
			noteText.show().focusin();
			noteText.height(noteTextHTML.outerHeight());
			noteText.scrollTop(noteTextHTML.scrollTop());
			noteTextHTML.hide();
			setNoteMode(noteMode, 'edit');

			$(document).one('click', function(e) {
				if (e.target.id !== 'note-text') {
					noteTextHTML.show();
					noteTextHTML.height(noteText.outerHeight());
					noteTextHTML.scrollTop(noteText.scrollTop());
					noteText.hide();
					setNoteMode(noteMode, 'read');
				}
			});

			$(document).one('click', '#note-text', function(e){
				noteEditing = 2;
				setNoteMode(noteMode, 'editing');
				noteSaveTimer = setTimeout(noteSaveChanges,10000);
			});

		});



		function notePostFinal() {
			$.post(
				'notes/sync',
				{
					'note_text' : noteText.val()
				},
				function (data) {
					noteTextHTML.html(data.html);
					noteTextHTML.show();
					noteTextHTML.height(noteText.outerHeight());
					noteTextHTML.scrollTop(noteText.scrollTop());
					noteText.hide();
					setNoteMode(noteMode, 'saved');
				}
			);
		}

		function noteSaveChanges() {
			$.post('notes', { 'note_text' : noteText.val() });
			noteSaveTimer = setTimeout(noteSaveChanges, 10000);
		}

		function setNoteMode (obj, mode) {
			switch(mode) {
				case 'edit':
					obj.removeClass('border-secondary border-success text-muted text-success')
					obj.addClass('border-danger text-danger')
					obj.html('{{$strings.edit}}');
				break;
				case 'editing':
					obj.removeClass('border-secondary border-success text-muted text-success')
					obj.addClass('border-danger text-danger')
					obj.html('{{$strings.editing}}{{$strings.dots}}');
				break;
				case 'saving':
					obj.removeClass('border-secondary border-danger text-muted text-danger')
					obj.addClass('border-success text-success')
					obj.html('{{$strings.saving}}{{$strings.dots}}');
				break;
				case 'saved':
					obj.removeClass('border-secondary border-danger text-muted text-danger')
					obj.addClass('border-success text-success')
					obj.html('{{$strings.saved}}');
					setTimeout(function () {
						if(noteEditing) {
							setNoteMode(noteMode, noteEditing === 1 ? 'edit' : 'editing');
						}
						else {
							setNoteMode(noteMode, 'read');
						}
					}, 3000);
				break;
				case 'read':
				default:
					obj.removeClass('border-success border-danger text-success text-danger').addClass('border-secondary text-muted').html('{{$strings.read}}');
			}
		}

	</script>
{{if $app}}
</div>
{{/if}}
</div>
