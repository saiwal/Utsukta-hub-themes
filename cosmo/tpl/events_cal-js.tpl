{{$tabs}}
<div class="generic-content-wrapper">
	<div class="section-title-wrapper app-content-header">
		<div class="float-end">
			<div class="btn-group">
				<button class="btn btn-outline-secondary btn-sm" onclick="changeView('prev', false);" title="{{$prev}}"><i class="bi fa-backward"></i></button>
					<button id="today-btn" class="btn btn-outline-secondary btn-sm" onclick="changeView('today', false);" title="{{$today}}"><div id="events-spinner" class="spinner s"></div><i class="bi fa-bullseye" style="display: none; width: 1rem;"></i></button>
				<button class="btn btn-outline-secondary btn-sm" onclick="changeView('next', false);" title="{{$next}}"><i class="bi fa-forward"></i></button>
			</div>
			<button id="fullscreen-btn" type="button" class="btn btn-outline-secondary btn-sm" onclick="makeFullScreen();"><i class="bi fa-expand"></i></button>
			<button id="inline-btn" type="button" class="btn btn-outline-secondary btn-sm" onclick="makeFullScreen(false);"><i class="bi fa-compress"></i></button>
		</div>
		<h3 id="title"></h3>
		<div class="clear"></div>
	</div>
	<div class="clear"></div>
	<div class="section-content-wrapper-np">
		<div id="events-calendar"></div>
	</div>
</div>
