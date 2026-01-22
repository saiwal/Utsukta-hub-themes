<div class="{{if $class}} {{$class}}{{/if}} mb-3">
  {{if $title}}
  <div class="h6">
    {{$title}}
  </div>
  {{/if}}
    {{if $desc}}<div class="desc">{{$desc}}</div>{{/if}}

    <ul>
      {{foreach $items as $item}}
      <li><a href="{{$item.url}}"
          class="{{if $item.selected}}text-secondary active{{/if}}">{{$item.label}}</a></li>
      {{/foreach}}
    </ul>
</div>
