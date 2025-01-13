<script>

function toggle_posted_date_button() {
	if($('#posted-date-dropdown').is(':visible')) {
		$('#posted-date-icon').removeClass('bi-chevron-up');
		$('#posted-date-icon').addClass('bi-chevron-down');
		$('#posted-date-dropdown').hide();
	}
	else {
		$('#posted-date-icon').addClass('bi-chevron-up');
		$('#posted-date-icon').removeClass('bi-chevron-down');
		$('#posted-date-dropdown').show();
	}
}
</script>


<div id="datebrowse-sidebar" class="card mb-3">
  <div class="card-header">
	<h3 class="card-title">{{$title}}</h3>
  </div>
  <div class="card-body">
	<script>function dateSubmit(dateurl) { window.location.href = dateurl; } </script>
	<ul id="posted-date-selector" class="nav nav-pills flex-column">
		{{foreach $dates as $y => $arr}}
		{{if $y == $cutoff_year}}
		</ul>
		<div id="posted-date-dropdown" style="display: none;">
		<ul id="posted-date-selector-drop" class="nav nav-pills flex-column">
		{{/if}}
		<li class="nav-item" id="posted-date-selector-year-{{$y}}">
			<a class="nav-link" href="#" onclick="openClose('posted-date-selector-{{$y}}'); return false;">{{$y}}</a>
		</li>
		<div id="posted-date-selector-{{$y}}" style="display: none;">
			<ul class="posted-date-selector-months nav nav-pills flex-column">
				{{foreach $arr as $d}}
				<li class="nav-item">
					<a class="nav-link" href="#" onclick="dateSubmit('{{$url}}?f=&dend={{$d.1}}{{if $showend}}&dbegin={{$d.2}}{{/if}}'); return false;">{{$d.0}}</a>
				</li>
				{{/foreach}}
			</ul>
		</div>
		{{/foreach}}
		{{if $cutoff}}
		</div>
		<button class="btn btn-outline-secondary btn-sm" onclick="toggle_posted_date_button(); return false;"><i id="posted-date-icon" class="bi bi-chevron-down"></i></button>
		{{/if}}
	</ul>
  </div>
</div>
