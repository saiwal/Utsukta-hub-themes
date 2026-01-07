<div class="event-item-title lcars-text-bar">
	<span><i class="bi bi-calendar3"></i>&nbsp;{{$title}}</span>
</div>
<div class="entry__meta">
	{{if $oneday && $allday}}
	<span class="dtstart small entry__meta-date">{{$dtstart_dt}}</span>
	{{else if $allday}}
	<span class="dtstart small entry__meta-date">{{$dtstart_dt}}</span> &mdash; <span class="dtend">{{$dtend_dt}}</span>
	{{else}}
	<div class="event-item-start entry__meta-date">
		<span class="event-item-label">{{$dtstart_label}}</span>&nbsp;<span class="dtstart"
			title="{{$dtstart_title}}">{{$dtstart_dt}}</span>
	</div>
	{{if $finish}}
	<div class="event-item-start entry__meta-date">
		<span class="event-item-label">{{$dtend_label}}</span>&nbsp;<span class="dtend"
			title="{{$dtend_title}}">{{$dtend_dt}}</span>
	</div>
	{{/if}}
	{{/if}}
	{{if $event_tz.value}}
	<div class="event-item-start entry__meta-date">
		<span class="event-item-label">{{$event_tz.label}}:</span>&nbsp;<span class="timezone"
			title="{{$event_tz.value}}">{{$event_tz.value}}</span>
	</div>
	{{/if}}
</div>
