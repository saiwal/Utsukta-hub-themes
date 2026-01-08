<div class="pillbox">
	{{foreach $pills as $p}}
		<a class="pill {{if $p.sel}} blink{{/if}}" href="{{$p.url}}"{{if $p.title}} title="{{$p.title}}"{{/if}}{{if $p.sub}} onclick="{{if $p.sel}}closeOpen('{{$p.id}}_sub');{{else}}openClose('{{$p.id}}_sub');{{/if}} return false;"{{/if}}>
			{{if $p.icon}}<i class="bi bi-{{$p.icon}}"></i>{{/if}}
			{{if $p.img}}<img class="menu-img-1" src="{{$p.img}}">{{/if}}
			{{$p.label}}
			{{if $p.sub}}<i class="bi bi-caret-down hover-fx-hide"></i>{{/if}}
		</a>
	{{/foreach}}
</div>
	{{foreach $pills as $p}}
		{{if $p.sub}}
		<div class="pillbox" id="{{$p.id}}_sub"{{if !$p.sel}} style="display: none;"{{/if}}>
			{{foreach $p.sub as $ps}}
				<a class="pill {{if $ps.sel}} blink {{/if}}" href="{{$ps.url}}"{{if $ps.title}} title="{{$ps.title}}"{{/if}}>
				{{if $ps.icon}}<i class="bi bi-{{$ps.icon}}"></i>{{/if}}
				{{if $ps.img}}<img class="img-size-32 shadow" src="{{$ps.img}}">{{/if}}
				{{$ps.label}}
				{{if $ps.lock}}<i class="bi bi-{{$ps.lock}} text-muted"></i>{{/if}}
				</a>
			{{/foreach}}
		</div>
		{{/if}}
	{{/foreach}}
