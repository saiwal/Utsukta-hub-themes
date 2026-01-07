<div class="generic-content-wrapper">
	<div class="section-title-wrapper app-content-header">
		<h3>{{$title}}</h3>
	</div>
	<div class="section-content-wrapper">
		{{if ! $default_role}}
		<div class="section-content-info-wrapper">
			{{$desc}}
		</div>
		{{if $channel_usage_message}}
		<div class="section-content-warning-wrapper">
			{{$channel_usage_message}}
		</div>
		{{/if}}
		{{/if}}
		{{if $canadd}}
			<form action="new_channel" method="post" id="newchannel-form">
				{{if $default_role}}
					<input type="hidden" name="permissions_role" value="{{$default_role}}" />
				{{else}}
					{{include file="field_select.tpl" field=$role}}
				{{/if}}

				{{include file="field_input.tpl" field=$name}}

				{{include file="field_input.tpl" field=$nickname}}
				<button class="btn btn-primary" type="submit" id="newchannel-submit-button">{{$submit}}</button>


				<div id="newchannel-submit-end" class="clear"></div>

				<div id="newchannel-import-link" class="descriptive-paragraph" >{{$label_import}}</div>
				<div id="newchannel-import-end" class="clear"></div>
			</form>
		{{/if}}
	</div>
</div>
