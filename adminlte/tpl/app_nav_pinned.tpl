<!-- pinned apps in main sidebar menu -->
  <a href="{{$app.url}}" class="nav-link {{if $app.active}} active{{/if}}">
  {{if $icon}}<i class="nav-icon fa fa-fw fa-{{$icon}}"></i>{{/if}} {{$app.name}}
  </a>

