<div class="h6">{{$title}}</div>
<ul class="link-list">
{{foreach $options as $x}}
	<li><a href="{{$x.0}}" class="{{if $x.2}} active{{/if}}">{{$x.1}}</a></li>
{{/foreach}}
</ul>
