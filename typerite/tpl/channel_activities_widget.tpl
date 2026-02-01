<div id="channel-activities" class="d-none overflow-hidden">

	<div class="h3 mt-0">
		{{$welcome}} {{$channel_name}}!
	</div>

	{{if !$activities}}
		<h3>{{$no_activities}}</h3>
	{{else}}
		{{$activity_html}}
	{{/if}}

</div>
