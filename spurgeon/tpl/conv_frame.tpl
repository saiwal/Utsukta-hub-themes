<div id="threads-begin"></div>
<div id="threads-end"></div>
<div id="conversation-end"></div>
<!-- pagination -->
<div class="row pagination invisible">
	<div class="column lg-12">
		<nav class="pgn">
			<ul>
				<li>
					<a class="pgn__prev" href="#0">
						<svg width="24" height="24" fill="none" viewBox="0 0 24 24">
							<path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
								d="M10.25 6.75L4.75 12L10.25 17.25"></path>
							<path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
								d="M19.25 12H5"></path>
						</svg>
					</a>
				</li>
				<li><a class="pgn__num" href="#0">1</a></li>
				<li><span class="pgn__num current">2</span></li>
				<li><a class="pgn__num" href="#0">3</a></li>
				<li><a class="pgn__num" href="#0">4</a></li>
				<li><a class="pgn__num" href="#0">5</a></li>
				<li><span class="pgn__num dots">…</span></li>
				<li><a class="pgn__num" href="#0">8</a></li>
				<li>
					<a class="pgn__next" href="#0">
						<svg width="24" height="24" fill="none" viewBox="0 0 24 24">
							<path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
								d="M13.75 6.75L19.25 12L13.75 17.25"></path>
							<path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
								d="M19 12H4.75"></path>
						</svg>
					</a>
				</li>
			</ul>
		</nav> <!-- end pgn -->
	</div> <!-- end column -->
</div> <!-- end pagination -->


<div id="page-spinner" class="spinner-wrapper">
	<div class="spinner m"></div>
	<div id="image_counter" class="text-muted text-center small"></div>
</div>
<div class="modal" id="conversation_settings" tabindex="-1" role="dialog" aria-labelledby="conversation_settings_label"
	aria-hidden="true">
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
			<div class="modal-body row row-cols-2 row-cols-sm-3 row-cols-md-6 g-3" id="reactions_body">
				{{$wait}}
			</div>
			<div class="ps-3 pe-3" id="reactions_extra_top"></div>
			<div class="ps-3 pe-3" id="reactions_extra_middle"></div>
			<div class="ps-3 pe-3" id="reactions_extra_bottom"></div>
		</div><!-- /.modal-content -->
	</div><!-- /.modal-dialog -->
</div><!-- /.modal -->
{{include file="contact_edit_modal.tpl"}}
