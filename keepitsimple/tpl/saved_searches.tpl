<div class="clearfix mb-3">
    <h6 id="search">{{$title}}</h6>
	{{$searchbox}}
	<div id="saved-search-list" class="tagcloud group">
		{{foreach $saved as $search}}
		<span id="search-term-{{$search.id}}">
			<a id="saved-search-term-{{$search.id}}" class="nav-link{{if $search.selected}} active{{/if}}" href="{{$search.srchlink}}">{{$search.displayterm}}</a>
			<a class="nav-link widget-nav-pills-icons{{if $search.selected}} active{{/if}}" title="{{$search.delete}}" onclick="return confirmDelete();" id="drop-saved-search-term-{{$search.id}}" href="{{$search.dellink}}"><i class="bi bi-trash"></i></a>
		</span>
		{{/foreach}}
	</div>
</div>
