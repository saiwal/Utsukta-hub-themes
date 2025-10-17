<div class="mb-3">
  <div class="h4">
    {{$title}}
  </div>
  <!-- /.card-header -->
  {{if $options}}
    <ul class="list-group list-group-flush ms-0" style="list-style: none;">
    {{foreach $options as $x}}
    {{if is_array($x) }}
    {{foreach $x as $y => $z}}
      <a href="{{$y}}" class="h6 mt-0 ms-3">{{$z}}</a>
    {{/foreach}}
    {{else}}
      <li class="h5 mt-3">{{$x}}</li>
    {{/if}}
    {{/foreach}}
    </ul>
  <!-- /.card-body -->
  {{/if}}
</div>
