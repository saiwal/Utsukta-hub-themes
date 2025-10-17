<div class="mb-3">
	<div class="h4 mt-0">{{$roles_label}}</div>
	<ul class="nav nav-pills flex-column">
		{{foreach $roles as $role}}
		<li class="nav-item">
			<a class="nav-link{{if $role.active}} active{{/if}}" href="{{$role.url}}">
				{{$role.name}}
			</a>
		</li>
		{{/foreach}}
	</ul>
</div>

{{if $members}}
<div class="mb-3">
	<div class="h4 mt-0">{{$members_label}}</div>
	<div class="p-0 overflow-auto" style="height: 19rem;">
		{{foreach $members as $member}}
		<a href="{{$member.url}}" class="lh-sm border-bottom p-2 d-block text-truncate">
			<img src="{{$member.photo}}" class="float-start rounded me-2" style="height: 2.2rem; width: 2.2rem;" loading="lazy">
			{{$member.name}}<br>
			<span class="text-muted small">{{$member.addr}}</span>
		</a>
		{{/foreach}}
	</div>
</div>
{{/if}}
