<div class="mb-3">
  <div class="h4 mt-0">{{$title}}</div>
<ul class="nav nav-pills flex-column">
{{foreach $options as $x}}
	<li class="nav-item"><a href="{{$x.0}}" class="nav-link{{if $x.2}} active{{/if}}">{{$x.1}}</a></li>
{{/foreach}}
</ul>
</div>
