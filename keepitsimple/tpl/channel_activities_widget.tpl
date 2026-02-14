<div id="channel-activities" class="d-none overflow-hidden">
	<header class="entry__header">
		<h2 class="entry__title h1">
			{{$welcome}} {{$channel_name}}!
		</h2>
	</header>
	{{if !$activities}}
	<h3>{{$no_activities}}</h3>
	{{else}}
	{{$activity_html}}
	{{/if}}

</div>
