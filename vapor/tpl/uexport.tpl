<div class="generic-content-wrapper">
	<div class="section-title-wrapper app-content-header">
		<h3>{{$title}}</h3>
	</div>
	<div class="section-content-wrapper">
		<p class="mb-3">
			<h3>{{$channel_title}}</h3>
			<p>
				{{$channel_info}}
			</p>
			<a href="uexport/channel" class="btn btn-outline-primary"><i class="bi bi-download"></i> {{$channel_title}}</a>
		</p>
		<p class="mb-3">
			<h3>{{$content_title}}</h3>
			<p>
				{{$content_info}}
				{{$items_extra_info}}
			</p>
			{{foreach $years as $year}}
			<a href="uexport/{{$year}}" class="btn btn-outline-primary"><i class="bi bi-download"></i> {{$year}}</a>
			{{/foreach}}
		</p>
		<p class="mb-3">
			<h3>{{$wikis_title}}</h3>
			<p>
				{{$wikis_info}}
				{{$items_extra_info}}
			</p>
			<a href="uexport/wikis" class="btn btn-outline-primary"><i class="bi bi-download"></i> {{$wikis_title}}</a>
		</p>
		<p class="mb-3">
			<h3>{{$webpages_title}}</h3>
			<p>
				{{$webpages_info}}
				{{$items_extra_info}}
			</p>
			<a href="uexport/webpages" class="btn btn-outline-primary"><i class="bi bi-download"></i> {{$webpages_title}}</a>
		</p>
		<p class="mb-3">
			<h3>{{$events_title}}</h3>
			<p>
				{{$events_info}}
				{{$items_extra_info}}
			</p>
			<a href="uexport/events" class="btn btn-outline-primary"><i class="bi bi-download"></i> {{$events_title}}</a>
		</p>
		<p class="mb-3">
			<h3>{{$chatrooms_title}}</h3>
			<p>
				{{$chatrooms_info}}
				{{$items_extra_info}}
			</p>
			<a href="uexport/chatrooms" class="btn btn-outline-primary"><i class="bi bi-download"></i> {{$chatrooms_title}}</a>
		</p>
	</div>
</div>
