<div class="mb-3 hq_controls{{if $wrapper_class}} {{$wrapper_class}}{{/if}}">
	{{foreach $entries as $e}}
	<button class="{{$e.class}}" type="{{$e.type}}" title="{{$e.label}}"{{if $e.extra}} {{$e.extra}}{{/if}}>
		{{if $e.icon}}<i class="bi bi-{{$e.icon}}"></i>{{/if}}
	</button>
	{{/foreach}}
</div>
