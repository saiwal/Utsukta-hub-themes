<div class="pillbox">
	{{foreach $pills as $p}}
		<a class="pill {{if $p.sel}}blink{{/if}}"
		   href="{{if $p.sub}}#{{$p.id}}_sub{{else}}{{$p.url}}{{/if}}"
		   {{if $p.title}}title="{{$p.title}}"{{/if}}
		   {{if $p.sub}}
			   data-bs-toggle="collapse"
			   role="button"
			   aria-expanded="{{if $p.sel}}true{{else}}false{{/if}}"
			   aria-controls="{{$p.id}}_sub"
		   {{/if}}>
			{{$p.label}}
			{{if $p.icon}}<i class="bi bi-{{$p.icon}} ps-2"></i>{{/if}}
			{{if $p.img}}<img class="menu-img-1 ps-2" src="{{$p.img}}">{{/if}}
		</a>
	{{/foreach}}
</div>

{{foreach $pills as $p}}
	{{if $p.sub}}
		<div class="collapse {{if $p.sel}}show{{/if}}" id="{{$p.id}}_sub">
			<div class="pillbox">
				{{foreach $p.sub as $ps}}
					<a class="pill {{if $ps.sel}}blink{{/if}}"
					   href="{{$ps.url}}"
					   {{if $ps.title}}title="{{$ps.title}}"{{/if}}>
						{{$ps.label}}
						{{if $ps.icon}}<i class="bi bi-{{$ps.icon}}"></i>{{/if}}
						{{if $ps.img}}<img class="img-size-32 shadow" src="{{$ps.img}}">{{/if}}
						{{if $ps.lock}}<i class="bi bi-{{$ps.lock}} text-muted"></i>{{/if}}
					</a>
				{{/foreach}}
			</div>
		</div>
	{{/if}}
{{/foreach}}
