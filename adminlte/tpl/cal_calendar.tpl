<script>
$(document).ready(function() {
	let calendarEl = document.getElementById('calendar');
	let fragment = window.location.hash.substring(1);
	let view;

	calendar = new FullCalendar.Calendar(calendarEl, {

		eventSources: [ {{$sources}} ],

		timeZone: '{{$timezone}}',

		locale: '{{$lang}}',

		eventTextColor: 'white',
		headerToolbar: false,

		height: 'auto',

		firstDay: {{$first_day}},

		allDayText: aStr['allday'],

		eventClick: function(info) {
			var event_id = info.event._def.extendedProps.item.id;
			showEvent(event_id);
		},

		loading: function(isLoading, view) {
			$('#events-spinner').show();
			$('#today-btn > i').hide();
			if(!isLoading) {
				$('#events-spinner').hide();
				$('#today-btn > i').show();
			}
		}

	});

	calendar.render();

	if (fragment) {
		switch (fragment.length) {
			case 7:
				view = 'dayGridMonth';
				break;
			case 10:
				view = 'timeGridWeek';
				break;
			case 11:
				if (fragment[0] === '!') {
					fragment = fragment.substring(1);
					view = 'timeGridDay';
				}
				break;
			default:
				view = 'dayGridMonth';
		}
		calendar.changeView(view, fragment);
	}

	$('#title').text(calendar.view.title);

	$('#today-btn').on('click', function() {
		calendar.today();
		$('#title').text(calendar.view.title);
	});

	$('#prev-btn').on('click', function() {
		calendar.prev();
		$('#title').text(calendar.view.title);
	});

	$('#next-btn').on('click', function() {
		 calendar.next();
		 $('#title').text(calendar.view.title);
	});

	$(document).on('click','#fullscreen-btn', updateSize);
	$(document).on('click','#inline-btn', updateSize);

});

function showEvent(event_id) {
	$.get(
		'cal/{{$nick}}?id='+event_id,
		function(data){
			$.colorbox({ scrolling: false, html: data, onComplete: function() { $.colorbox.resize(); }});
		}
	);
}

function changeView(action, viewName) {
	calendar.changeView(viewName);
	$('#title').text(calendar.view.title);
	$('#view_selector').html(views[calendar.view.type]);
	return;
}

function updateSize() {
	calendar.updateSize();
}
</script>

<div class="generic-content-wrapper">
	<div class="section-title-wrapper app-content-header">
		<div class="float-end">
			<div class="btn-group">
				<button id="prev-btn" class="btn btn-secondary btn-sm" title="{{$prev}}"><i class="bi bi-chevron-left"></i></button>
				<button id="today-btn" class="btn btn-secondary btn-sm" title="{{$today}}"><span id="events-spinner" class="spinner s"></span><i class="bi bi-crosshair" style="display: none; padding: 0.065rem;"></i></button>
				<button id="next-btn" class="btn btn-secondary btn-sm" title="{{$next}}"><i class="bi bi-chevron-right"></i></button>
			</div>
		</div>
		<h3 id="title"></h3>
		<div class="clear"></div>
	</div>
	<div class="section-content-wrapper">
		<div id="calendar"></div>
	</div>
</div>
