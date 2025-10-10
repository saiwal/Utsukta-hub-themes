<div class="mb-3">
  <div class="h4">
	{{$title}}
  </div>
	<ul style="list-style: none;">
		{{foreach $menu_items as $menu_item}}
		<li class="h6 mt-0">
			<a href="{{$menu_item.href}}" title="{{$menu_item.title}}">{{$menu_item.label}}</a>
		<li>
		{{/foreach}}
	</ul>
</div>
