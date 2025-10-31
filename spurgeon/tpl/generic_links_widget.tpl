<div class="{{if $class}} {{$class}}{{/if}} mb-3">
  {{if $title}}
  <div class="h5">
    {{$title}}
  </div>
  {{/if}}
    {{if $desc}}<div class="desc">{{$desc}}</div>{{/if}}

    <ul style="list-style: none;">
      {{foreach $items as $item}}
      <li class="nav-item"><a href="{{$item.url}}"
          class="nav-link{{if $item.selected}} active{{/if}}">{{$item.label}}</a></li>
      {{/foreach}}
    </ul>
</div>
