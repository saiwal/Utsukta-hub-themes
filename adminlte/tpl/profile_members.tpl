<div id="prof-update-wrapper">

	<div id="prof-members-title">
		<h3>{{$visible_to}}</h3>
	</div>

	<div id="prof-members">
		{{foreach $members as $m}}
			{{$m.micro}}
		{{/foreach}}
	</div>

	<div id="prof-members-end"></div>

	<hr id="prof-separator" />

	<div id="prof-all-contcts-title">
		<h3>{{$all_connections}}</h3>
	</div>

	<div id="prof-all-contacts">
		{{foreach $all_members as $am}}
			{{$am.micro}}
		{{/foreach}}
	</div>

	<div id="prof-all-contacts-end"></div>

</div>
