<div id="contacts" class="list-group float-start w-50 pr-2">
	<h6>{{$groupeditor.label_contacts}}</h6>
	<div id="group-all-contacts" class="contact_list row row-cols-2 row-cols-sm-3 row-cols-md-4 g-3">
	{{foreach $groupeditor.contacts as $m}} {{$m}} {{/foreach}}
	</div>
</div>
<div id="group" class="list-group float-end w-50">
	<h6>{{$groupeditor.label_members}}</h6>
	<div id="group-members" class="contact_list row row-cols-2 row-cols-sm-3 row-cols-md-4 g-3">
	{{foreach $groupeditor.members as $c}} {{$c}} {{/foreach}}
	</div>
</div>
