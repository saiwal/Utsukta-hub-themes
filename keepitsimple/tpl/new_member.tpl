<div class="widget widget_text group">
  <h3 class="h6">{{$title}}</h3>
  <!-- /.card-header -->
  {{if $options}}
  <div class="card-body">
    <ul class="list-group list-group-flush">
    {{foreach $options as $x}}
    {{if is_array($x) }}
    {{foreach $x as $y => $z}}
      <a href="{{$y}}" class="list-group-item list-group-item-action">{{$z}}</a>
    {{/foreach}}
    {{else}}
      <li class="list-group-item list-group-item-primary">{{$x}}</li>
    {{/if}}
    {{/foreach}}
    </ul>
  </div>
  <!-- /.card-body -->
  {{/if}}
</div>
