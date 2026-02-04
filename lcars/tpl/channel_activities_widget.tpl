<div id="channel-activities" class="d-none overflow-hidden">
	<div class="lcars-text-bar"><span>
		{{$welcome}} {{$channel_name}}!
		</span>	
	</div>
	{{if !$activities}}
		<h3>{{$no_activities}}</h3>
	{{else}}
		{{$activity_html}}
	{{/if}}
	</div>
