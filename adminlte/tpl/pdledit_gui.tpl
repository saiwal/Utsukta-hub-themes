<div id="pdledit_gui_offcanvas" class="offcanvas offcanvas-bottom shadow border rounded-top start-50 translate-middle-x" tabindex="-1" data-bs-backdrop="false" data-bs-scroll="true" style="min-width: 300px">
	<div id="pdledit_gui_offcanvas_body" class="offcanvas-body"></div>
	<div class="offcanvas-header">
		<div class="offcanvas-title h3"></div>
		<button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas" aria-label="Close"></button>
	</div>
</div>

<div id="pdledit_gui_offcanvas_edit" class="offcanvas offcanvas-bottom " tabindex="-1" data-bs-backdrop="false" data-bs-scroll="true" style="min-width: 300px">
	<div id="pdledit_gui_offcanvas_edit_body" class="offcanvas-body">
		<textarea id="pdledit_gui_offcanvas_edit_textarea" class="form-control font-monospace h-100"></textarea>
	</div>
	<div class="offcanvas-header">
		<button id="pdledit_gui_offcanvas_edit_submit" type="button" class="btn btn-primary">Submit</button>
		<button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas" aria-label="Close"></button>
	</div>
</div>

<div id="pdledit_gui_offcanvas_submit" class="offcanvas offcanvas-bottom shadow border rounded-top start-50 translate-middle-x" tabindex="-1" data-bs-backdrop="false" data-bs-scroll="true" style="min-width: 300px">
	<div id="pdledit_gui_offcanvas_submit_body" class="offcanvas-body"></div>
	<div class="offcanvas-header">
		<button id="pdledit_gui_offcanvas_submit_submit" type="button" class="btn btn-primary">Submit</button>
		<button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas" aria-label="Close"></button>
	</div>
</div>

<ul class="nav position-fixed bottom-0 start-50 bg-light translate-middle-x text-uppercase" style="min-width: 300px">
	<li class="nav-item">
		<a id="pdledit_gui_modules" class="nav-link" href="#">Modules</a>
	</li>
	<li class="nav-item">
		<a id="pdledit_gui_templates" class="nav-link" href="#">Templates</a>
	</li>
	<li class="nav-item">
		<a id="pdledit_gui_items" class="nav-link" href="#">Items</a>
	</li>
	<li class="nav-item">
		<a id="pdledit_gui_src" class="nav-link" href="#">Source</a>
	</li>
	{{if $module_modified}}
	<li class="nav-item">
		<a id="pdledit_gui_reset" class="nav-link" href="#">Reset</a>
	</li>
	{{/if}}
	<li class="nav-item">
		<a id="pdledit_gui_save" class="nav-link" href="#">Apply</a>
	</li>
</ul>

<script>
	$(document).ready(function() {
		let poi;
		let regions = [];
		let content_regions = [];
		let page_src = atob('{{$page_src}}');

		let offcanvas = new bootstrap.Offcanvas(document.getElementById('pdledit_gui_offcanvas'));
		let edit_offcanvas = new bootstrap.Offcanvas(document.getElementById('pdledit_gui_offcanvas_edit'));
		let submit_offcanvas = new bootstrap.Offcanvas(document.getElementById('pdledit_gui_offcanvas_submit'));

		{{foreach $content_regions as $content_region}}
		regions.push('{{$content_region.0}}');
		content_regions.push('{{$content_region.1}}');

		let sortable_{{$content_region.1}} = document.getElementById('{{$content_region.1}}');
		new Sortable(sortable_{{$content_region.1}}, {
			group: 'shared',
			handle: '.pdledit_gui_item_handle',
			animation: 150
		});
		{{/foreach}}

		let sortable_items = document.getElementById('pdledit_gui_offcanvas_body');
		new Sortable(sortable_items, {
			group: {
				name: 'shared',
				pull: 'clone',
				put: false
			},
			sort: false,
			handle: '.pdledit_gui_item_handle',
			animation: 150,
			onEnd: function (e) {
				$(e.item).find('button').removeClass('disabled');
			}
		});

		$(document).on('click', '.pdledit_gui_item_src', function(e) {
			poi = this.closest('.pdledit_gui_item');
			let src = atob(poi.dataset.src);
			$('#pdledit_gui_offcanvas_edit_textarea').val(src);
			$('#pdledit_gui_offcanvas_edit_textarea').bbco_autocomplete('comanche');
			edit_offcanvas.show();
		});

		$(document).on('click', '.pdledit_gui_item_remove', function(e) {
			poi = this.closest('.pdledit_gui_item');
			$(poi).remove();
		});

		$(document).on('click', '#pdledit_gui_offcanvas_edit_submit', function(e) {
			let src = $('#pdledit_gui_offcanvas_edit_textarea').val();

			if (poi) {
				poi.dataset.src = btoa(src);
			}
			else {
				$.post(
					'pdledit_gui',
					{
						'save_src': 1,
						'module': '{{$module}}',
						'src': $('#pdledit_gui_offcanvas_edit_textarea').val()
					}
				)
				.done(function(data) {
					if (data.success) {
						window.location.href = 'pdledit_gui/' + data.module;
					}
				});
			}

			edit_offcanvas.hide();
		});

		$(document).on('click', '#pdledit_gui_offcanvas_submit_submit', function(e) {
			if ($('#pdledit_gui_templates_form').length) {
				$.post(
					'pdledit_gui',
					{
						'save_template': 1,
						'module': '{{$module}}',
						'data': $('#pdledit_gui_templates_form').serializeArray()
					}
				)
				.done(function(data) {
					if (data.success) {
						window.location.href = 'pdledit_gui/' + data.module;
					}
				});
			}

			submit_offcanvas.hide();
		});

		$(document).on('click', '#pdledit_gui_src', function(e) {
			e.preventDefault();
			poi = null; // this is important!

			let obj = {};

			content_regions.forEach(function (content_region, i) {
				let data_src = [];
				$('#' + content_region + ' > .card').each(function () {
					data_src.push(atob(this.dataset.src));
				});
				obj[regions[i]] = data_src;
			});

			for (let [region, entries] of Object.entries(obj)) {
				let region_pdl = '';

				entries.forEach(function (entry) {
					region_pdl = region_pdl.concat(entry + "\r\n");
				});

				let regex_str = '\\[region=' + region + '\\](.*?)\\[\\/region\\]';
				let replace_str = '[region=' + region + ']' + "\r\n" + region_pdl + "\r\n" + '[/region]'
				let regex = new RegExp(regex_str, 'ism');

				page_src = page_src.replace(regex, replace_str);
			}

			$('#pdledit_gui_offcanvas_edit_textarea').val(page_src);
			$('#pdledit_gui_offcanvas_edit_textarea').bbco_autocomplete('comanche');
			edit_offcanvas.show();
		});

		$(document).on('click', '#pdledit_gui_items', function(e) {
			e.preventDefault();
			$('#pdledit_gui_offcanvas_body').html(atob('{{$items}}'));
			offcanvas.show();
		});

		$(document).on('click', '#pdledit_gui_templates', function(e) {
			e.preventDefault();
			$('#pdledit_gui_offcanvas_submit_body').html(atob('{{$templates}}'));

			submit_offcanvas.show();
		});

		$(document).on('click', '#pdledit_gui_modules', function(e) {
			e.preventDefault();
			$('#pdledit_gui_offcanvas_body').html(atob('{{$modules}}'));
			offcanvas.show();
		});

		$(document).on('click', '#pdledit_gui_save', function(e) {
			e.preventDefault();

			let obj = {};

			content_regions.forEach(function (content_region, i) {
				let data_src = [];
				$('#' + content_region + ' > .card').each(function () {
					data_src.push(this.dataset.src);
				});
				obj[regions[i]] = data_src;
			});

			$.post(
				'pdledit_gui',
				{
					'save': 1,
					'module': '{{$module}}',
					'data': JSON.stringify(obj)
				}
			)
			.done(function(data) {
				if (data.success) {
					window.location.href = 'pdledit_gui/' + data.module;
				}
			});
		});

		$(document).on('click', '#pdledit_gui_reset', function(e) {
			e.preventDefault();
			$.post(
				'pdledit_gui',
				{
					'reset': 1,
					'module': '{{$module}}'
				}
			)
			.done(function(data) {
				if (data.success) {
					window.location.href = 'pdledit_gui/' + data.module;
				}
			});
		});

	});
</script>
