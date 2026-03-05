<div id="group-sidebar">
	<div class="h5">{{$title}}</div>
		<ul class="list-unstyled">
			{{foreach $groups as $group}}
			<li>
				{{if $group.cid}}
				<i id="group-{{$group.id}}" class="widget-nav-pills-checkbox bi {{if $group.ismember}}bi-check-square{{else}}bi-square{{/if}}" onclick="contactgroupChangeMember('{{$group.id}}','{{$group.enc_cid}}'); return true;"></i>
				{{/if}}
				{{if $group.edit}}
				<a href="{{$group.edit.href}}" class="{{if $group.selected}} active{{/if}} widget-nav-pills-icons" title="{{$edittext}}"><i class="bi bi-pencil"></i></a>
				{{/if}}
				<a class="{{if $group.selected}} active{{/if}}" href="{{$group.href}}">{{$group.text}}</a>
			</li>
			{{/foreach}}
			{{if $createtext}}
			<li >
				<a href="group/new" title="{{$createtext}}" ><i class="bi bi-box-arrow-up-right"></i> {{$createtext}}</a>
			</li>
			{{/if}}
		</ul>
</div>




