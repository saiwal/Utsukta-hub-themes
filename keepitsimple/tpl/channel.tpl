<div class="section-content-wrapper m-4">
	<div class="d-flex justify-content-between">
		<div class="channel-photo-wrapper">
			{{if $selected != $channel.channel_id}}
			<a href="{{$channel.link}}" class="channel-selection-photo-link" title="{{$channel.channel_name}}">
				{{/if}}
				<img class="mg-thumbnail" src="{{$channel.xchan_photo_m}}" alt="{{$channel.channel_name}}" />
				{{if $selected != $channel.channel_id}}</a>{{/if}}
		</div>
		<div class="channel-notifications-wrapper">
			{{if $selected == $channel.channel_id}}
			<i class="bi bi-circle-fill text-success" title="{{$msg_selected}}"></i>
			{{/if}}
			{{if $channel.delegate}}
			<i class="bi fa-arrow-circle-right" title="{{$delegated_desc}}"></i>
			{{/if}}
			{{if $selected != $channel.channel_id}}<a href="{{$channel.link}}" title="{{$channel.channel_name}}">{{/if}}
				{{$channel.channel_name}}
				{{if $selected != $channel.channel_id}}</a>{{/if}}
			{{if !$channel.delegate}}
			<div class="channel-notification">
				<i class="bi bi-person{{if $channel.intros != 0}} text-danger{{/if}}"></i>
				{{if $channel.intros != 0}}<a
					href='manage/{{$channel.channel_id}}/connections/ifpending'>{{/if}}{{$channel.intros|string_format:$intros_format}}{{if
					$channel.intros != 0}}</a>{{/if}}
			</div>
			{{/if}}
		</div>
		{{if $channel.default_links}}
		{{if $channel.default}}
		<div>
			<i class="bi bi-check-square"></i>&nbsp;{{$msg_default}}
		</div>
		{{else}}
		<a href="manage/{{$channel.channel_id}}/default" class="make-default-link">
			<i class="bi bi-square"></i>&nbsp;{{$msg_make_default}}
		</a>
		{{/if}}
		{{/if}}
		{{if $channel.delegate}}
		{{$delegated_desc}}
		{{/if}}
	</div>
</div>
