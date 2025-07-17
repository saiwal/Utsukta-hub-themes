<div id="threads-begin"></div>
<div id="threads-end"></div>
<div id="conversation-end"></div>
<div id="page-spinner" class="spinner-wrapper">
	<div  class="spinner m"></div>
	<div id="image_counter" class="text-muted text-center small"></div>
</div>
<div class="modal" id="conversation_settings" tabindex="-1" role="dialog" aria-labelledby="conversation_settings_label" aria-hidden="true">
	<div class="modal-dialog">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title" id="conversation_settings_label">{{$conversation_tools}}</h5>
				<button type="button" class="btn-close" data-bs-dismiss="modal" aria-hidden="true"></button>
			</div>
			<div class="modal-body" id="conversation_settings_body">
				{{$wait}}
			</div>
		</div><!-- /.modal-content -->
	</div><!-- /.modal-dialog -->
</div><!-- /.modal -->
<div class="modal modal-lg" id="reactions" tabindex="-1" role="dialog" aria-hidden="true">
	<div class="modal-dialog modal-dialog-scrollable modal-dialog-centered">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title" id="reactions_title"></h5>
				<button type="button" class="btn-close" data-bs-dismiss="modal" aria-hidden="true"></button>
			</div>
			<div class="modal-header" id="reactions_action">
			</div>
			<div class="modal-body d-flex" id="reactions_body">
				{{$wait}}
			</div>
			<div class="ps-3 pe-3" id="reactions_extra_top"></div>
			<div class="ps-3 pe-3" id="reactions_extra_middle"></div>
			<div class="ps-3 pe-3" id="reactions_extra_bottom"></div>
		</div><!-- /.modal-content -->
	</div><!-- /.modal-dialog -->
</div><!-- /.modal -->
{{include file="contact_edit_modal.tpl"}}
