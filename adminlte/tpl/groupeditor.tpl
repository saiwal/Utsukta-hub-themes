<div id="contacts" class="list-group float-start w-50 pr-2">
	<h3>{{$groupeditor.label_contacts}}</h3>
	<div id="group-all-contacts" class="contact_list">
	{{foreach $groupeditor.contacts as $m}} {{$m}} {{/foreach}}
	</div>
</div>
<div id="group" class="list-group float-end w-50">
	<h3>{{$groupeditor.label_members}}</h3>
	<div id="group-members" class="contact_list">
	{{foreach $groupeditor.members as $c}} {{$c}} {{/foreach}}
	</div>
</div>
