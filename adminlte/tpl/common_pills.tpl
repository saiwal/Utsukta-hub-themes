<ul class="nav nav-pills flex-column">
	{{foreach $pills as $p}}
	<li class="nav-item hover-fx-show"{{if $p.id}} id="{{$p.id}}"{{/if}}>
		<a class="nav-link{{if $p.sel}} {{$p.sel}}{{/if}}" href="{{$p.url}}"{{if $p.title}} title="{{$p.title}}"{{/if}}{{if $p.sub}} onclick="{{if $p.sel}}closeOpen('{{$p.id}}_sub');{{else}}openClose('{{$p.id}}_sub');{{/if}} return false;"{{/if}}>
			{{if $p.icon}}<i class="fa fa-fw fa-{{$p.icon}}"></i>{{/if}}
			{{if $p.img}}<img class="menu-img-1" src="{{$p.img}}">{{/if}}
			{{$p.label}}
			{{if $p.sub}}<i class="fa fa-fw fa-caret-down hover-fx-hide"></i>{{/if}}
		</a>
		{{if $p.sub}}
		<ul class="nav nav-pills flex-column ml-4" id="{{$p.id}}_sub"{{if !$p.sel}} style="display: none;"{{/if}}>
			{{foreach $p.sub as $ps}}
			<li class="nav-item"{{if $ps.id}} id="{{$ps.id}}"{{/if}}>
				<a class="nav-link{{if $ps.sel}} {{$ps.sel}}{{/if}}" href="{{$ps.url}}"{{if $ps.title}} title="{{$ps.title}}"{{/if}}>
				{{if $ps.icon}}<i class="fa fa-fw fa-{{$ps.icon}}"></i>{{/if}}
				{{if $ps.img}}<img class="menu-img-1" src="{{$ps.img}}">{{/if}}
				{{$ps.label}}
				{{if $ps.lock}}<i class="fa fa-{{$ps.lock}} text-muted"></i>{{/if}}
				</a>
			</li>
			{{/foreach}}
		</ul>
		{{/if}}
	</li>
	{{/foreach}}
</ul>
