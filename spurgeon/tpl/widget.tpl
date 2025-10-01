{{* Generic template for widgets.
  *
  * To use this template, pass the `$this` object reference of the widget,
  * and implement the `title()` and `contents()` functions as public methods
  * on the widget class.
  *}}
<div class="widget card">
	{{if $widget->title()}}
  <div class="card-header">{{$widget->title()}}</div>
	{{/if}}
  <div class="card-body">
	{{$widget->contents()}}
  </div>
</div>
