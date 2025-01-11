<div id="categories-sidebar" class="widget card mb-3">
	<h3>{{$title}}</h3>
	<div id="categories-sidebar-desc">{{$desc}}</div>

	<ul class="nav nav-pills flex-column">
		<li class="nav-item"><a href="{{$base}}" class="nav-link{{if $sel_all}} active{{/if}}">{{$all}}</a></li>
		{{foreach $terms as $term}}
		<li class="nav-item"><a  href="{{$base}}/?cat={{$term.name|escape:'url'}}" class="nav-link{{if $term.selected}} active{{/if}}">{{$term.name}}</a></li>
		{{/foreach}}
	</ul>

</div>
