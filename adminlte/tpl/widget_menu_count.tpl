<div class="card mb-3">
	<div class="card-header">{{$title}}</div>
  <div class="card-body p-0">
	<ul class="nav nav-pills flex-column">
		{{foreach $menu_items as $menu_item}}
		<li class="nav-item">
			<a class="nav-link {{if $menu_item.active}} active{{/if}}" href="{{$menu_item.href}}" title="{{$menu_item.title}}">
				{{$menu_item.label}}
				<span class="badge {{if $menu_item.active}} bg-light text-dark{{else}} bg-secondary{{/if}} float-end">{{$menu_item.count}}</span>
			</a>
		<li>
		{{/foreach}}
	</ul>
  </div>
</div>
