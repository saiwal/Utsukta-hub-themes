<div class="mb-3">
  <div class="h6">
	{{$title}}
  </div>
	<ul class="link-list">
		{{foreach $menu_items as $menu_item}}
		<li class="mt-0">
			<a href="{{$menu_item.href}}" title="{{$menu_item.title}}">{{$menu_item.label}}</a>
		<li>
		{{/foreach}}
	</ul>
</div>
