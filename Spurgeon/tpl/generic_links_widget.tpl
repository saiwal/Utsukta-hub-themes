<div class="{{if $class}} {{$class}}{{/if}} card mb-3">
  {{if $title}}
  <div class="card-header">
    {{$title}}
  </div>
  {{/if}}
  <div class="card-body">
    {{if $desc}}<div class="desc">{{$desc}}</div>{{/if}}

    <ul class="nav nav-pills flex-column">
      {{foreach $items as $item}}
      <li class="nav-item"><a href="{{$item.url}}"
          class="nav-link{{if $item.selected}} active{{/if}}">{{$item.label}}</a></li>
      {{/foreach}}
    </ul>
  </div>
</div>
