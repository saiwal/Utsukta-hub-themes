<ul class="flex-column" style="list-style:none;">
	{{foreach $pills as $p}}
	<li class="p-2"{{if $p.id}} id="{{$p.id}}"{{/if}}>
		<a class="nav-link{{if $p.sel}} {{$p.sel}}{{/if}}" href="{{$p.url}}"{{if $p.title}} title="{{$p.title}}"{{/if}}{{if $p.sub}} onclick="{{if $p.sel}}closeOpen('{{$p.id}}_sub');{{else}}openClose('{{$p.id}}_sub');{{/if}} return false;"{{/if}}>
			{{if $p.icon}}<i class="bi bi-{{$p.icon}}"></i>{{/if}}
			{{if $p.img}}<img class="menu-img-1" src="{{$p.img}}">{{/if}}
			{{$p.label}}
			{{if $p.sub}}<i class="bi bi-caret-down hover-fx-hide"></i>{{/if}}
		</a>
		{{if $p.sub}}
		<ul class="flex-column ml-4" id="{{$p.id}}_sub"{{if !$p.sel}} style="display: none;"{{/if}}>
			{{foreach $p.sub as $ps}}
			<li class="nav-item"{{if $ps.id}} id="{{$ps.id}}"{{/if}}>
				<a class="nav-link{{if $ps.sel}} {{$ps.sel}}{{/if}}" href="{{$ps.url}}"{{if $ps.title}} title="{{$ps.title}}"{{/if}}>
				{{if $ps.icon}}<i class="bi bi-{{$ps.icon}}"></i>{{/if}}
				{{if $ps.img}}<img class="img-size-32 shadow" src="{{$ps.img}}">{{/if}}
				{{$ps.label}}
				{{if $ps.lock}}<i class="bi bi-{{$ps.lock}} text-muted"></i>{{/if}}
				</a>
			</li>
			{{/foreach}}
		</ul>
		{{/if}}
	</li>
	{{/foreach}}
</ul>
