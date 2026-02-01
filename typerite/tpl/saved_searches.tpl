<div class="clearfix mb-3">
	<h3 id="search">{{$title}}</h3>
	<p>
		{{$searchbox}}
	<ul id="saved-search-list" class="list-group list-group-flush m-0">
		{{foreach $saved as $search}}
		<li class="list-group-item d-flex justify-content-between align-items-center" id="search-term-{{$search.id}}">
			<a id="saved-search-term-{{$search.id}}" class="{{if $search.selected}} fw-semibold active{{/if}}"
				href="{{$search.srchlink}}">{{$search.displayterm}}</a>
			<a class="widget-nav-pills-icons{{if $search.selected}} fw-semibold {{/if}}" title="{{$search.delete}}"
				onclick="return confirmDelete();" id="drop-saved-search-term-{{$search.id}}" href="{{$search.dellink}}"><i
					class="bi bi-trash"></i></a>
		</li>
		{{/foreach}}
	</ul>
	</p>
</div>
