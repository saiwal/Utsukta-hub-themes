<div class="mb-3">
	<div class="h6">{{$title}}</div>
	<ul class="link-list">
		{{foreach $menu_items as $menu_item}}
		<li>
			<a class="{{if $menu_item.active}} active{{/if}}" href="{{$menu_item.href}}" title="{{$menu_item.title}}">
				{{$menu_item.label}}
				<span class="badge {{if $menu_item.active}} bg-light text-dark{{else}} bg-secondary{{/if}} float-end">{{$menu_item.count}}</span>
			</a>
		<li>
		{{/foreach}}
	</ul>
</div>
