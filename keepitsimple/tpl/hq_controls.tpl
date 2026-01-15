<div class="hq_controls{{if $wrapper_class}} {{$wrapper_class}}{{/if}} d-flex">
	{{foreach $entries as $e}}
	<button class="{{$e.class}} mb-0" type="{{$e.type}}" title="{{$e.label}}"{{if $e.extra}} {{$e.extra}}{{/if}}>
		{{if $e.icon}}<i class="bi text-white bi-{{$e.icon}}"></i>{{/if}}
	</button>
	{{/foreach}}
</div>
