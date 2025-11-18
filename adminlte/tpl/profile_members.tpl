<div id="prof-update-wrapper" class="card">

	<div id="prof-members-title">
		<div class="card-header">{{$visible_to}}</div>
	</div>
  <div class="card-body">
	<div id="prof-members" class="row row-cols-1 row-cols-md-5 g-4">
		{{foreach $members as $m}}
			{{$m.micro}}
		{{/foreach}}
	</div>
	</div>

	<div id="prof-members-end"></div>

	<hr id="prof-separator" />

	<div id="prof-all-contcts-title">
		<div class="card-header">{{$all_connections}}</div>
	</div>

  <div class="card-body">
	<div id="prof-all-contacts" class="row row-cols-1 row-cols-md-5 g-4">
		{{foreach $all_members as $am}}
			{{$am.micro}}
		{{/foreach}}
	</div>
	</div>

	<div id="prof-all-contacts-end"></div>

</div>
