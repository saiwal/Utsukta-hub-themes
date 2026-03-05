<div class="{{if $class}} {{$class}}{{/if}} mb-3">
  {{if $title}}
  <div class="h5">
    {{$title}}
  </div>
  {{/if}}
    {{if $desc}}<div class="desc">{{$desc}}</div>{{/if}}

    <ul class="list-unstyled">
      {{foreach $items as $item}}
      <li><a href="{{$item.url}}"
          class="{{if $item.selected}} active{{/if}}">{{$item.label}}</a></li>
      {{/foreach}}
    </ul>
</div>
