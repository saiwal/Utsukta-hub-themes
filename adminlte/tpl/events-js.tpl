<div class="generic-content-wrapper">
	<div class="section-title-wrapper app-content-header">
		<div class="float-end">
			<div class="dropdown">
				<button type="button" class="btn btn-secondary btn-sm dropdown-toggle" data-bs-toggle="dropdown"><i class="bi bi-gear"></i>&nbsp;{{$view_label}}</button>
				<div class="dropdown-menu">
					<a class="dropdown-item" href="#" onclick="changeView('changeView', 'month'); return false;">{{$month}}</a>
					<a class="dropdown-item" href="#" onclick="changeView('changeView', 'agendaWeek'); return false;">{{$week}}</a>
					<a class="dropdown-item" href="#" onclick="changeView('changeView', 'agendaDay'); return false;">{{$day}}</a>
				</div>
				<button class="btn btn-success btn-sm" onclick="openClose('form');">{{$new_event.1}}</button>
				<div class="btn-group">
					<button class="btn btn-secondary btn-sm" onclick="changeView('prev', false);" title="{{$prev}}"><i class="bi bi-rewind-fill"></i></button>
					<button id="today-btn" class="btn btn-secondary btn-sm" onclick="changeView('today', false);" title="{{$today}}"><div id="events-spinner" class="spinner s"></div><i class="bi bi-bullseye" style="display: none; width: 1rem;"></i></button>
					<button class="btn btn-secondary btn-sm" onclick="changeView('next', false);" title="{{$next}}"><i class="bi bi-fast-forward-fill"></i></button>
				</div>
				<button id="fullscreen-btn" type="button" class="btn btn-secondary btn-sm" onclick="makeFullScreen();"><i class="bi bi-arrows-angle-expand"></i></button>
				<button id="inline-btn" type="button" class="btn btn-secondary btn-sm" onclick="makeFullScreen(false);"><i class="bi bi-arrows-angle-contract"></i></button>
			</div>
		</div>
		<h3 id="title"></h3>
		<div class="clear"></div>
	</div>
	<div id="form" class="section-content-tools-wrapper"{{if !$expandform}} style="display:none;"{{/if}}>
		{{$form}}
	</div>
	<div class="clear"></div>
	<div class="section-content-wrapper">
		<div id="events-calendar"></div>
	</div>
</div>
