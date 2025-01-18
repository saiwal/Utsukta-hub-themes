<div class="card card-outline card-success collapsed-card mb-3">
  <div class="card-header">
    {{$title}}
    <div class="card-tools">
      <button type="button" class="btn btn-tool" data-lte-toggle="card-collapse">
        <i data-lte-icon="expand" class="bi bi-plus-lg"></i>
        <i data-lte-icon="collapse" class="bi bi-dash-lg"></i>
      </button>
    </div>
    <!-- /.card-tools -->
  </div>
  <!-- /.card-header -->
  {{if $options}}
  <div class="card-body" style="display: none; box-sizing: border-box;">
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
