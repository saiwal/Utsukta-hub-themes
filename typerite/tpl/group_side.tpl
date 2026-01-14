<div class="mb-3" id="group-sidebar">
	<div class="h4 mt-0">{{$title}}</div>
		<ul class="nav nav-pills flex-column">
			{{foreach $groups as $group}}
			<li class="nav-item nav-item-hack">
				{{if $group.cid}}
				<i id="group-{{$group.id}}" class="widget-nav-pills-checkbox bi {{if $group.ismember}}bi-check-square{{else}}bi-square{{/if}}" onclick="contactgroupChangeMember('{{$group.id}}','{{$group.enc_cid}}'); return true;"></i>
				{{/if}}
				{{if $group.edit}}
				<a href="{{$group.edit.href}}" class="nav-link{{if $group.selected}} active{{/if}} widget-nav-pills-icons" title="{{$edittext}}"><i class="bi bi-pencil"></i></a>
				{{/if}}
				<a class="nav-link{{if $group.selected}} active{{/if}}" href="{{$group.href}}">{{$group.text}}</a>
			</li>
			{{/foreach}}
			{{if $createtext}}
			<li class="nav-item">
				<a class="nav-link" href="group/new" title="{{$createtext}}" ><i class="bi bi-box-arrow-up-right"></i> {{$createtext}}</a>
			</li>
			{{/if}}
		</ul>
</div>




