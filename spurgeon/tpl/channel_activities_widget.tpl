<div id="channel-activities" class="d-none overflow-hidden">

	<h2 class="mt-0">
		{{$welcome}} {{$channel_name}}!
	</h2>

	{{if !$activities}}
		<h3>{{$no_activities}}</h3>
	{{else}}
		{{$activity_html}}
	{{/if}}

</div>
