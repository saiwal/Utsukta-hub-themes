<div class="generic-content-wrapper">
	<div class="section-title-wrapper clearfix app-content-header">
		{{if $notifications_available}}
		<a href="#" class="btn btn-outline-secondary btn-sm float-end" onclick="markRead('notify'); setTimeout(function() { window.location.href=window.location.href; },1500); return false;">{{$notif_link_mark_seen}}</a>
		{{/if}}
		<h3>{{$notif_header}}</h3>
	</div>
	<div class="section-content-wrapper">
		{{$notif_content}}
	</div>
</div>
