<div class="clearfix mb-3">
	<div class="lcars-text-bar" id="search"><span>{{$title}}</span></div>
  <p>
	{{$searchbox}}
	<div id="saved-search-list" class="d-flex wrap gap-2">
		{{foreach $saved as $search}}
		<div class="d-flex gap-4" id="search-term-{{$search.id}}">
			<a id="saved-search-term-{{$search.id}}" class="nav-link{{if $search.selected}} active{{/if}}" href="{{$search.srchlink}}">{{$search.displayterm}}</a>
			<a class="nav-link widget-nav-pills-icons{{if $search.selected}} active{{/if}}" title="{{$search.delete}}" onclick="return confirmDelete();" id="drop-saved-search-term-{{$search.id}}" href="{{$search.dellink}}"><i class="bi bi-trash"></i></a>
		</div>
		{{/foreach}}
	</div>
  </p>
</div>
