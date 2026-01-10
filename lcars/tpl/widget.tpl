{{* Generic template for widgets.
  *
  * To use this template, pass the `$this` object reference of the widget,
  * and implement the `title()` and `contents()` functions as public methods
  * on the widget class.
  *}}
<div class="widget">
	{{if $widget->title()}}
  <div class="lcars-text-bar">{{$widget->title()}}</div>
	{{/if}}
	{{$widget->contents()}}
</div>
