<div class="generic-content-wrapper">
	<div class="section-title-wrapper app-content-header">
		<div class="lcars-text-bar"><span>{{$header}}</span></div>
		<div class="buttons the-end flush"><a class="" href="{{$create.0}}" title="{{$create.1}}"><i class="bi bi-plus-lg"></i>&nbsp;{{$create.2}}</a></div>
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
