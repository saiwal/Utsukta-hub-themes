	<div class="h6">{{$roles_label}}</div>
	<ul class="link-list">
		{{foreach $roles as $role}}
		<li>
			<a class="{{if $role.active}} active{{/if}}" href="{{$role.url}}">
				{{$role.name}}
			</a>
		</li>
		{{/foreach}}
	</ul>

{{if $members}}
	<div class="h6">{{$members_label}}</div>
	<ul class="link-list">
		{{foreach $members as $member}}
		<a href="{{$member.url}}" class="lh-sm border-bottom p-2 d-block text-truncate">
			<img src="{{$member.photo}}" class="float-start rounded me-4" style="height: 4.2rem; width: 4.2rem;" loading="lazy">
			{{$member.name}}<br>
			<span class="text-muted small">{{$member.addr}}</span>
		</a>
		{{/foreach}}
	</ul>
{{/if}}
