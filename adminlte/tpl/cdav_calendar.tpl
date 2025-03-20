<script>
var calendar;
var new_event = {};
var new_event_id = Math.random().toString(36).substring(7);
var views = {'dayGridMonth' : '{{$month}}', 'timeGridWeek' : '{{$week}}', 'timeGridDay' : '{{$day}}', 'listMonth' : '{{$list_month}}', 'listWeek' : '{{$list_week}}', 'listDay' : '{{$list_day}}'};

var event_id;
var event_uri;
var event_xchan;

var contact_allow = [];
var group_allow = [];
var contact_deny = [];
var group_deny = [];

var resource = {{$resource}};
var allday;

$(document).ready(function() {
	var calendarEl = document.getElementById('calendar');
	calendar = new FullCalendar.Calendar(calendarEl, {

		eventSources: [ {{$sources}} ],

		timeZone: '{{$timezone}}',

		locale: '{{$lang}}',

		eventTextColor: 'white',
		headerToolbar: false,

		height: 'auto',

		firstDay: {{$first_day}},

		weekNumbers: true,
		navLinks: true,

		navLinkDayClick: function(date, jsEvent) {
			calendar.gotoDate( date );
			changeView('timeGridDay');
		},

		navLinkWeekClick: function(date, jsEvent) {
			calendar.gotoDate( date );
			changeView('timeGridWeek');
		},

		allDayText: aStr['allday'],

		snapDuration: '00:05:00',

		dateClick: function(info) {
			if(new_event.id) {
				var event_poi = calendar.getEventById(new_event.id);
				event_poi.remove();
				new_event = {};
			}

			allday = info.allDay;

			if(allday) {
				$('#id_dtstart_wrapper, #id_dtend_wrapper, #id_timezone_select_wrapper').hide();
			}
			else {
				$('#id_dtstart_wrapper, #id_dtend_wrapper, #id_timezone_select_wrapper').show();
			}


			var dtend = new Date(info.date.toUTCString());
			if(allday) {
				dtend.setDate(dtend.getDate() + 1);
			}
			else{
				dtend.setHours(dtend.getHours() + 1);
			}

			event_uri = '';
			$('#id_title').val('New event');
			$('#id_title').attr('disabled', false);
			$('#id_dtstart').attr('disabled', false);
			$('#id_dtend').attr('disabled', false);
			$('#id_description').attr('disabled', false);
			$('#id_location').attr('disabled', false);
			$('#calendar_select').val($("#calendar_select option:first").val()).attr('disabled', false);
			$('#id_dtstart').val(info.date.toUTCString().slice(0, -4));
			$('#id_dtend').val(dtend ? dtend.toUTCString().slice(0, -4) : '');
			$('#id_description').val('');
			$('#id_location').val('');
			$('#event_submit').val('create_event').html('{{$create}}');
			$('#event_delete').hide();

			new_event = { id: new_event_id, title: 'New event', start: info.date, end: dtend ? dtend : '', allDay: info.allDay, editable: true, color: '#bbb' };
			calendar.addEvent(new_event);
		},

		eventClick: function(info) {
			//reset categories
			$('#id_categories').tagsinput('removeAll');

			var event = info.event._def;
			var dtstart = new Date(info.event._instance.range.start);
			var dtend = new Date(info.event._instance.range.end);

			allday = event.allDay;

			if(event.extendedProps.plink) {
				if(! $('#l2s').length)
					$('#id_title_wrapper').prepend('<span id="l2s" class="float-end"></span>');

				$('#l2s').html('<a href="' + event.extendedProps.plink[0] + '" target="_blank"><i class="bi bi-box-arrow-up-right"></i> ' + event.extendedProps.plink[1] + '</a>');
			}
			else {
				$('#l2s').remove();
			}

			if (allday) {
				$('#id_dtstart_wrapper, #id_dtend_wrapper, #id_timezone_select_wrapper').hide();
			}
			else {
				$('#id_dtstart_wrapper, #id_dtend_wrapper, #id_timezone_select_wrapper').show();
			}

			if(event.publicId == new_event_id) {
				$('#calendar_select').trigger('change');
				$('#event_submit').show();
				event_id = 0;
				$(window).scrollTop(0);
				$('.section-content-tools-wrapper, #event_form_wrapper').show();
				$('#recurrence_warning').hide();
				$('#id_title').focus().val('');
				$('#id_title').attr('disabled', false);
				$('#id_dtstart').attr('disabled', false);
				$('#id_dtend').attr('disabled', false);
				$('#id_description').attr('disabled', false);
				$('#id_location').attr('disabled', false);

				return false;
			}

			if(new_event.id && event.extendedProps.rw) {
				var event_poi = calendar.getEventById(new_event.id);
				event_poi.remove();
				new_event = {};
			}

			var calendar_id = ((event.extendedProps.calendar_id.constructor === Array) ? event.extendedProps.calendar_id[0] + ':' + event.extendedProps.calendar_id[1] : event.extendedProps.calendar_id);

			if(!event.extendedProps.recurrent) {
				$(window).scrollTop(0);
				$('.section-content-tools-wrapper, #event_form_wrapper').show();
				$('#recurrence_warning').hide();
				event_uri = event.extendedProps.uri;
				$('#id_title').val(event.title);
				$('#calendar_select').val(calendar_id).attr('disabled', true).trigger('change');
				$('#id_timezone_select').val(event.extendedProps.timezone);
				$('#id_location').val(event.extendedProps.location);
				$('#id_categories').tagsinput('add', event.extendedProps.categories);
				$('#id_dtstart').val(dtstart.toUTCString().slice(0, -4));
				$('#id_dtend').val(dtend.toUTCString().slice(0, -4));
				$('#id_description').val(event.extendedProps.description);
				$('#id_location').val(event.extendedProps.location);
				$('#event_submit').val('update_event').html('{{$update}}');
				$('#dbtn-acl').addClass('d-none');
				event_id = event.extendedProps.item ? event.extendedProps.item.id : 0;
				event_xchan = event.extendedProps.item ? event.extendedProps.item.event_xchan : '';

				contact_allow = event.extendedProps.contact_allow || [];
				group_allow = event.extendedProps.group_allow || [];
				contact_deny = event.extendedProps.contact_deny || [];
				group_deny = event.extendedProps.group_deny || [];

				if(event.extendedProps.rw) {
					$('#event_delete').show();
					$('#event_submit').show();
					$('#id_title').focus();
					$('#id_title').attr('disabled', false);
					$('#id_dtstart').attr('disabled', false);
					$('#id_dtend').attr('disabled', false);
					$('#id_description').attr('disabled', false);
					$('#id_location').attr('disabled', false);

					if(calendar_id === 'channel_calendar' && !event.ui.startEditable) {
						$('#event_submit').hide();
					}
				}
				else {
					$('#event_submit').hide();
					$('#event_delete').hide();
					$('#id_title').attr('disabled', true);
					$('#id_dtstart').attr('disabled', true);
					$('#id_dtend').attr('disabled', true);
					$('#id_description').attr('disabled', true);
					$('#id_location').attr('disabled', true);
				}
			}
			else if(event.extendedProps.recurrent && event.extendedProps.rw) {
				$('.section-content-tools-wrapper, #recurrence_warning').show();
				$('#event_form_wrapper').hide();
				event_uri = event.extendedProps.uri;
				$('#calendar_select').val(calendar_id).attr('disabled', true).trigger('change');
			}
		},

		eventResize: function(info) {

			var event = info.event._def;
			var dtstart = new Date(info.event._instance.range.start);
			var dtend = new Date(info.event._instance.range.end);

			$('#id_title').val(event.title);
			$('#id_dtstart').val(dtstart.toUTCString().slice(0, -4));
			$('#id_dtend').val(dtend.toUTCString().slice(0, -4));

			event_id = event.extendedProps.item ? event.extendedProps.item.id : 0;
			event_xchan = event.extendedProps.item ? event.extendedProps.item.event_xchan : '';

			if(event.extendedProps.calendar_id === 'channel_calendar') {
				$.post( 'channel_calendar', {
					'event_id': event_id,
					'event_hash': event_uri,
					'xchan': event_xchan,
					'type': 'event',
					'preview': 0,
					'summary': event.title,
					'timezone_select': event.extendedProps.timezone,
					'dtstart': dtstart.toUTCString().slice(0, -4),
					'dtend': dtend.toUTCString().slice(0, -4),
					'adjust': event.allDay ? 0 : 1,
					'categories': event.extendedProps.categories,
					'desc': event.extendedProps.description,
					'location': event.extendedProps.location,
				})
				.fail(function() {
					info.revert();
				});
			}
			else {
				$.post( 'cdav/calendar', {
					'update': 'resize',
					'id[]': event.extendedProps.calendar_id,
					'uri': event.extendedProps.uri,
					'timezone_select': event.extendedProps.timezone,
					'dtstart': dtstart ? dtstart.toUTCString().slice(0, -4) : '',
					'dtend': dtend ? dtend.toUTCString().slice(0, -4) : '',
					'allday': event.allDay ? 1 : 0
				})
				.fail(function() {
					info.revert();
				});
			}
		},

		eventDrop: function(info) {

			var event = info.event._def;
			var dtstart = new Date(info.event._instance.range.start);
			var dtend = new Date(info.event._instance.range.end);

			$('#id_title').val(event.title);
			$('#id_dtstart').val(dtstart.toUTCString().slice(0, -4));
			$('#id_dtend').val(dtend.toUTCString().slice(0, -4));

			event_id = event.extendedProps.item ? event.extendedProps.item.id : 0;
			event_xchan = event.extendedProps.item ? event.extendedProps.item.event_xchan : '';

			if(event.extendedProps.calendar_id === 'channel_calendar') {
				$.post( 'channel_calendar', {
					'event_id': event_id,
					'event_hash': event_uri,
					'xchan': event_xchan,
					'type': 'event',
					'preview': 0,
					'summary': event.title,
					'timezone_select': event.extendedProps.timezone,
					'dtstart': dtstart.toUTCString().slice(0, -4),
					'dtend': dtend.toUTCString().slice(0, -4),
					'adjust': event.allDay ? 0 : 1,
					'categories': event.extendedProps.categories,
					'desc': event.extendedProps.description,
					'location': event.extendedProps.location,
				})
				.fail(function() {
					info.revert();
				});
			}
			else {
				$.post( 'cdav/calendar', {
					'update': 'drop',
					'id[]': event.extendedProps.calendar_id,
					'uri': event.extendedProps.uri,
					'timezone_select': event.extendedProps.timezone,
					'dtstart': dtstart ? dtstart.toUTCString().slice(0, -4) : '',
					'dtend': dtend ? dtend.toUTCString().slice(0, -4) : '',
					'allday': event.allDay ? 1 : 0
				})
				.fail(function() {
					info.revert();
				});
			}
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

	$('#title').text(calendar.view.title);
	$('#view_selector').html(views[calendar.view.type]);

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

	$('#calendar_select').on('change', function() {
		if(this.value === 'channel_calendar')
			$('#dbtn-acl, #id_categories_wrapper').removeClass('d-none');
		else
			$('#dbtn-acl, #id_categories_wrapper').addClass('d-none');
	});

	$('.color-edit').colorpicker({ input: '.color-edit-input' });

	$(document).on('click','#fullscreen-btn', updateSize);
	$(document).on('click','#inline-btn', updateSize);
	$(document).on('click','#event_submit', on_submit);
	$(document).on('click','#event_more', on_more);
	$(document).on('click','#event_cancel, #event_cancel_recurrent', reset_form);
	$(document).on('click','#event_delete, #event_delete_recurrent', on_delete);

	if(resource !== null) {
		$('.section-content-tools-wrapper, #event_form_wrapper').show();

		$('#id_title_wrapper').prepend('<span id="l2s" class="float-end"></span>');
		$('#l2s').html('<a href="' + resource.plink[0] + '" target="_blank"><i class="bi bi-box-arrow-up-right"></i> ' + resource.plink[1] + '</a>');

		event_id = resource.id;
		event_uri = resource.event_hash;
		event_xchan = resource.event_xchan;

		allday = resource.adjust ? 0 : 1;

		if (allday) {
			$('#id_dtstart_wrapper, #id_dtend_wrapper, #id_timezone_select_wrapper').hide();
		}

		$('#calendar_select').val('channel_calendar').attr('disabled', true);
		$('#id_title').val(resource.summary);

		calendar.changeView('timeGridDay', resource.dtstart);
		$('#title').text(calendar.view.title);

		// A hack to match with internal workings of fullcalendar.
		// See https://fullcalendar.io/docs/timeZone#UTC-coercion
		let start_d = new Date(resource.dtstart);
		let start_o = start_d.getTimezoneOffset();
		let end_d = new Date(resource.dtend);
		let end_o = start_d.getTimezoneOffset();
		$('#id_dtstart').val(new Date(start_d - start_o * 60000).toUTCString().slice(0, -4));
		$('#id_dtend').val(new Date(end_d - end_o * 60000).toUTCString().slice(0, -4));

		$('#id_categories').tagsinput('add', '{{$categories}}'),
		$('#id_description').val(resource.description);
		$('#id_location').val(resource.location);
		$('#id_timezone_select').val(resource.timezone);

		if(event_xchan !== '{{$channel_hash}}')
			$('#event_submit').hide();
		else
			$('#event_submit').html('{{$update}}');
	}

});

function changeView(viewName) {

	calendar.changeView(viewName);
	$('#title').text(calendar.view.title);
	$('#view_selector').html(views[calendar.view.type]);

	if(viewName === 'dayGridMonth') {
		$('#id_dtstart_wrapper, #id_dtend_wrapper, #id_timezone_select_wrapper').hide();
	}
	else {
		$('#id_dtstart_wrapper, #id_dtend_wrapper, #id_timezone_select_wrapper').show();
	}

	return;
}

function add_remove_json_source(source, color, editable, status) {
	var id, parts = [];

	if(source == '/channel_calendar/json')
		id = 'channel_calendar'

	if(! id) {
		parts = source.split('/');
		id = parts[4];
	}

	var eventSource = calendar.getEventSourceById(id);
	var selector = '#calendar-btn-' + id;

	if(status === undefined)
		status = 'bi-calendar-check';

	if(status === 'drop') {
		eventSource.remove();
		reset_form();
		return;
	}

	if($(selector).hasClass('bi-calendar')) {
		calendar.addEventSource({ id: id, url: source, color: color, editable: editable });
		$(selector).removeClass('bi-calendar');
		$(selector).addClass(status);
		$.get('/cdav/calendar/switch/' + id + '/1');
	}
	else {
		eventSource.remove();
		$(selector).removeClass(status);
		$(selector).addClass('bi-calendar');
		$.get('/cdav/calendar/switch/' + id + '/0');
	}
}

function updateSize() {
	calendar.updateSize();
}

function on_submit() {
	if($('#calendar_select').val() === 'channel_calendar') {
		if(new_event_id) {
			$("input[name='contact_allow[]']").each(function() {
			    contact_allow.push($(this).val());
			});
			$("input[name='group_allow[]']").each(function() {
			    group_allow.push($(this).val());
			});
			$("input[name='contact_deny[]']").each(function() {
			    contact_deny.push($(this).val());
			});
			$("input[name='group_deny[]']").each(function() {
			    group_deny.push($(this).val());
			});
		}

		$.post( 'channel_calendar', {
			'event_id': event_id,
			'event_hash': event_uri,
			'xchan': event_xchan,
			'type': 'event',
			'preview': 0,
			'timezone_select': $('#id_timezone_select').val(),
			'summary': $('#id_title').val(),
			'dtstart': $('#id_dtstart').val(),
			'dtend': $('#id_dtend').val(),
			'adjust': allday ? 0 : 1,
			'categories': $('#id_categories').val(),
			'desc': $('#id_description').val(),
			'location': $('#id_location').val(),
			'contact_allow[]': contact_allow,
			'group_allow[]': group_allow,
			'contact_deny[]': contact_deny,
			'group_deny[]': group_deny

		})
		.done(function() {
			var eventSource = calendar.getEventSourceById('channel_calendar');
			if (eventSource) {
				eventSource.refetch();
			}
			else {
				toast('{{$disabled_warning}}', 'danger');
			}
			reset_form();
		});

	}
	else {
		$.post( 'cdav/calendar', {
			'submit': $('#event_submit').val(),
			'target': $('#calendar_select').val(),
			'timezone_select': $('#id_timezone_select').val(),
			'uri': event_uri,
			'title': $('#id_title').val(),
			'dtstart': $('#id_dtstart').val(),
			'dtend': $('#id_dtend').val(),
			'description': $('#id_description').val(),
			'location': $('#id_location').val(),
			'allday': allday ? 1 : 0
		})
		.done(function() {
			var parts = $('#calendar_select').val().split(':');
			var eventSource = calendar.getEventSourceById(parts[0]);
			if (eventSource) {
				eventSource.refetch();
			}
			else {
				toast('{{$disabled_warning}}', 'danger');
			}
			reset_form();
		});
	}
}

function on_delete() {
	if($('#calendar_select').val() === 'channel_calendar') {
		$.get('channel_calendar/drop/' + event_uri, function() {
			var eventSource = calendar.getEventSourceById('channel_calendar');
			eventSource.refetch();
			reset_form();
		});
	}
	else {
		$.post( 'cdav/calendar', {
			'delete': 'delete',
			'target': $('#calendar_select').val(),
			'uri': event_uri
		})
		.done(function() {
			var parts = $('#calendar_select').val().split(':');
			var eventSource = calendar.getEventSourceById(parts[0]);
			eventSource.refetch();
			reset_form();
		});
	}
}

function reset_form() {
	$('.section-content-tools-wrapper, #event_form_wrapper, #recurrence_warning').hide();

	$('#event_submit').val('');
	$('#calendar_select').val('');
	event_uri = '';
	$('#id_title').val('');
	$('#id_categories').tagsinput('removeAll');
	$('#id_dtstart').val('');
	$('#id_dtend').val('');

	if(new_event.id) {
		var event_poi = calendar.getEventById(new_event.id);
		event_poi.remove();
		new_event = {};
	}

	if($('#more_block').hasClass('open'))
		on_more();
}

function on_more() {
	if($('#more_block').hasClass('open')) {
		$('#event_more').html('<i class="bi bi-caret-down"></i> {{$more}}');
		$('#more_block').removeClass('open').hide();
	}
	else {
		$('#event_more').html('<i class="bi bi-caret-up"></i> {{$less}}');
		$('#more_block').addClass('open').show();
	}
}

function exportDate() {
	window.location.href= 'channel_calendar/export';
}

</script>

<div class="generic-content-wrapper">
	<div class="section-title-wrapper app-content-header">
		<div class="float-end">
			<div class="dropdown">
				<button id="view_selector" type="button" class="btn btn-outline-secondary btn-sm dropdown-toggle" data-bs-toggle="dropdown"></button>
				<div class="dropdown-menu">
					<a class="dropdown-item" href="#" onclick="changeView('dayGridMonth'); return false;">{{$month}}</a>
					<a class="dropdown-item" href="#" onclick="changeView('timeGridWeek'); return false;">{{$week}}</a>
					<a class="dropdown-item" href="#" onclick="changeView('timeGridDay'); return false;">{{$day}}</a>
					<div class="dropdown-divider"></div>
					<a class="dropdown-item" href="#" onclick="changeView('listMonth'); return false;">{{$list_month}}</a>
					<a class="dropdown-item" href="#" onclick="changeView('listWeek'); return false;">{{$list_week}}</a>
					<a class="dropdown-item" href="#" onclick="changeView('listDay'); return false;">{{$list_day}}</a>
				</div>
				<div class="btn-group">
					<button id="prev-btn" class="btn btn-outline-secondary btn-sm" title="{{$prev}}"><i class="bi bi-chevron-left"></i></button>
					<button id="today-btn" class="btn btn-outline-secondary btn-sm" title="{{$today}}"><span id="events-spinner" class="spinner s"></span><i class="bi bi-crosshair" style="display: none; padding: 0.065rem;"></i></button>
					<button id="next-btn" class="btn btn-outline-secondary btn-sm" title="{{$next}}"><i class="bi bi-chevron-right"></i></button>
				</div>
			</div>
		</div>
		<h3 id="title"></h3>
		<div class="clear"></div>
	</div>
	<div class="section-content-tools-wrapper" style="display: none">
		<div id="recurrence_warning" style="display: none">
			<div class="section-content-warning-wrapper">
				{{$recurrence_warning}}
			</div>
			<div>
				<button id="event_delete_recurrent" type="button" class="btn btn-danger btn-sm">{{$delete_all}}</button>
				<button id="event_cancel_recurrent" type="button" class="btn btn-outline-secondary btn-sm">{{$cancel}}</button>
			</div>
		</div>
		<div id="event_form_wrapper" style="display: none">
			<form id="event_form" method="post" action="" class="acl-form" data-form_id="event_form" data-allow_cid='{{$allow_cid}}' data-allow_gid='{{$allow_gid}}' data-deny_cid='{{$deny_cid}}' data-deny_gid='{{$deny_gid}}'>
				{{include file="field_input.tpl" field=$title}}
				<label for="calendar_select">{{$calendar_select_label}}</label>
				<select id="calendar_select" name="target" class="form-control mb-3">
					<optgroup label="{{$calendar_optiopns_label.0}}">
					{{foreach $channel_calendars as $channel_calendar}}
					<option value="channel_calendar">{{$channel_calendar.displayname}}</option>
					{{/foreach}}
					</optgroup>
					<optgroup label="{{$calendar_optiopns_label.1}}">
					{{foreach $writable_calendars as $writable_calendar}}
					<option value="{{$writable_calendar.id.0}}:{{$writable_calendar.id.1}}">{{$writable_calendar.displayname}}{{if $writable_calendar.sharer}} ({{$writable_calendar.sharer}}){{/if}}</option>
					{{/foreach}}
					</optgroup>
				</select>
				{{if $timezone_select}}
				{{include file="field_select_grouped.tpl" field=$timezone_select}}
				{{/if}}
				<div id="more_block" style="display: none;">
					{{if $catsenabled}}
					<div id="id_categories_wrapper" class="mb-3">
						<label id="label_categories" for="id_categories">{{$categories_label}}</label>
						<input name="categories" id="id_categories" class="form-control" type="text" value="{{$categories}}" data-role="cat-tagsinput" />
					</div>
					{{/if}}
					{{include file="field_input.tpl" field=$dtstart}}
					{{include file="field_input.tpl" field=$dtend}}
					{{include file="field_textarea.tpl" field=$description}}
					{{include file="field_textarea.tpl" field=$location}}
				</div>
				<div class="mb-3">
					<div class="float-end">
						<button id="event_more" type="button" class="btn btn-outline-secondary btn-sm"><i class="bi bi-caret-down"></i> {{$more}}</button>
						<button id="dbtn-acl" class="btn btn-outline-secondary btn-sm d-none" type="button" data-bs-toggle="modal" data-bs-target="#aclModal"><i id="jot-perms-icon" class="bi bi-{{$lockstate}}"></i></button>
						<button id="event_submit" type="button" value="" class="btn btn-primary btn-sm"></button>

					</div>
					<div>
						<button id="event_delete" type="button" class="btn btn-danger btn-sm">{{$delete}}</button>
						<button id="event_cancel" type="button" class="btn btn-outline-secondary btn-sm">{{$cancel}}</button>
					</div>
					<div class="clear"></div>
				</div>
			</form>
			{{$acl}}
		</div>
	</div>
	<div class="section-content-wrapper-np">
		<div id="calendar"></div>
	</div>
</div>
