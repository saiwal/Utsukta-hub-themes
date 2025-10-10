<div class="clearfix mb-3">
    <h4 id="search">{{$title}}</h4>
  <p>
	{{$searchbox}}
	<ul id="saved-search-list" class="nav nav-pills flex-column">
		{{foreach $saved as $search}}
		<li class="nav-item nav-item-hack" id="search-term-{{$search.id}}">
			<a class="nav-link widget-nav-pills-icons{{if $search.selected}} active{{/if}}" title="{{$search.delete}}" onclick="return confirmDelete();" id="drop-saved-search-term-{{$search.id}}" href="{{$search.dellink}}"><i class="bi bi-trash"></i></a>
			<a id="saved-search-term-{{$search.id}}" class="nav-link{{if $search.selected}} active{{/if}}" href="{{$search.srchlink}}">{{$search.displayterm}}</a>
		</li>
		{{/foreach}}
	</ul>
  </p>
</div>
