<div class="generic-content-wrapper">
	<div class="section-title-wrapper clearfix app-content-header">
		{{if $authed}}
		{{if $create}}
		<a href="appman" class="float-end btn btn-success btn-sm"><i class="bi bi-pencil-square"></i>&nbsp;{{$create}}</a>
		{{elseif $manage}}
		<a href="apps/edit{{if $cat.0}}/?f=&cat={{$cat.0}}{{/if}}" class="float-end btn btn-primary btn-sm">{{$manage}}</a>
		{{/if}}
		{{/if}}
		<h3>{{$title}}{{if $cat.0}} - {{$cat.0}}{{/if}}</h3>
	</div>
	<div class="clearfix section-content-wrapper-np">
		{{foreach $apps as $ap}}
		{{$ap}}
		{{/foreach}}
	</div>
</div>
