<div class="mb-3">
	<div class="lcars-text-bar"><span>{{$title}}</span></div>
	<div class="pillbox">
		{{foreach $menu_items as $menu_item}}
		
			<a class="pill {{if $menu_item.active}} blink{{/if}}" href="{{$menu_item.href}}" title="{{$menu_item.title}}">
				{{$menu_item.label}}<span class="badge {{if $menu_item.active}} bg-light text-dark{{else}} bg-secondary{{/if}} float-end ms-2">{{$menu_item.count}}</span>
			</a>
		
		{{/foreach}}
	</div>
</div>
