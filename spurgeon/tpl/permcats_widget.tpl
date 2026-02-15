	<div class="h5">{{$roles_label}}</div>
	<ul class="list-unstyled">
		{{foreach $roles as $role}}
		<li>
			<a class="{{if $role.active}} active{{/if}}" href="{{$role.url}}">
				{{$role.name}}
			</a>
		</li>
		{{/foreach}}
	</ul>

{{if $members}}
	<div class="h5">{{$members_label}}</div>
	<div class="p-0 overflow-auto" style="height: 19rem;">
		{{foreach $members as $member}}
		<a href="{{$member.url}}" class="lh-sm border-bottom p-2 d-block text-truncate">
			<img src="{{$member.photo}}" class="float-start rounded me-2" style="height: 2.2rem; width: 2.2rem;" loading="lazy">
			{{$member.name}}<br>
			<span class="text-muted small">{{$member.addr}}</span>
		</a>
		{{/foreach}}
	</div>
{{/if}}
