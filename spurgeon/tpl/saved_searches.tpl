<h5 id="search">{{$title}}</h5>
<p>
	{{$searchbox}}
<ul id="saved-search-list" class="list-unstyled">
	{{foreach $saved as $search}}
	<li class="d-flex justify-content-between" id="search-term-{{$search.id}}">
		<a id="saved-search-term-{{$search.id}}" class="{{if $search.selected}} active{{/if}}"
			href="{{$search.srchlink}}">{{$search.displayterm}}</a>
		<a class="widget-nav-pills-icons{{if $search.selected}} active{{/if}}" title="{{$search.delete}}"
			onclick="return confirmDelete();" id="drop-saved-search-term-{{$search.id}}" href="{{$search.dellink}}"><i
				class="bi bi-trash"></i></a>
	</li>
	{{/foreach}}
</ul>
</p>
