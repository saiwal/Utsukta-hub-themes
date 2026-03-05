<div class="generic-content-wrapper">
	<div class="section-title-wrapper app-content-header clearfix">
		<header class="entry__header">
			<h2 class="entry__title h1">{{$header}}
			</h2>
		</header>
		<a class="btn btn-success btn-sm float-end" href="{{$create.0}}" title="{{$create.1}}"><i class="bi bi-plus-lg"></i>&nbsp;{{$create.2}}</a>
	</div>
	<div class="section-content-wrapper-np">
		{{if $channel_usage_message}}
		<div id="channel-usage-message" class="alert-box alert-box--info">
			{{$channel_usage_message}}
		</div>
		{{/if}}
		{{if $desc}}
		<div id="channels-desc" class="alert-box alert-box--info mb-2">
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
