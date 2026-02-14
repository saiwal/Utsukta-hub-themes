<div class="{{if $class}} {{$class}}{{/if}}">
  {{if $title}}
  <div class="h6">
    {{$title}}
  </div>
  {{/if}}
    {{if $desc}}<div class="desc">{{$desc}}</div>{{/if}}

	<ul class="link-list">
      {{foreach $items as $item}}
      <li><a href="{{$item.url}}"
          class="{{if $item.selected}} active{{/if}}">{{$item.label}}</a></li>
      {{/foreach}}
    </ul>
</div>
