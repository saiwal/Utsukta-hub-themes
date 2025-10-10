<div class="generic-content-wrapper">
	<div class="section-title-wrapper app-content-header">
		<a class="btn btn-success btn-sm float-end" href="{{$create.0}}" title="{{$create.1}}"><i class="bi bi-plus-lg"></i>&nbsp;{{$create.2}}</a>
		<h3>{{$header}}</h3>
	</div>
	<div class="section-content-wrapper-np">
		{{if $channel_usage_message}}
		<div id="channel-usage-message" class="section-content-warning-wrapper">
			{{$channel_usage_message}}
		</div>
		{{/if}}
		{{if $desc}}
		<div id="channels-desc" class="callout callout-info mb-2">
			{{$desc}}
		</div>
		{{/if}}
		{{foreach $all_channels as $chn}}
			{{include file="channel.tpl" channel=$chn}}
		{{/foreach}}
		{{if $delegates}}
			{{foreach $delegates as $chn}}
				{{include file="channel.tpl" channel=$chn}}
			{{/foreach}}
		{{/if}}
	</div>
</div>
