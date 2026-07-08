{{if $wrap}}
{{$body}}
{{else}}
<div class="card">
    {{if $show_title}}
		<div class="card-header">
			<div class="card-title">{{$title}}</div>
		</div>
    {{/if}}
		<div class="card-body">
    {{$body}}
		</div>
</div>
{{/if}}
