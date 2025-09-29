<div class="card mb-3">
  <div class="card-header">
    {{$contacts}}
    <div class="card-tools">
      <button type="button" class="btn btn-sm btn-tool" data-lte-toggle="card-collapse">
        <i data-lte-icon="expand" class="bi bi-plus-lg"></i>
        <i data-lte-icon="collapse" class="bi bi-dash-lg"></i>
      </button>
      <button type="button" class="btn btn-tool btn-sm" data-lte-toggle="card-remove">
        <i class="bi bi-x-lg"></i>
      </button>
    </div>
  </div>
  {{if $micropro}}
  <!-- /.card-header -->
  <div class="card-body p-1 overflow-y-auto" style="max-height: 19rem;">
    <div class="row row-cols-5 g-2">
      {{foreach $micropro as $m}}
        {{$m}}
      {{/foreach}}
    </div>
    <!-- /.users-list -->
  </div>
  {{/if}}
  <!-- /.card-body -->
  {{if $viewconnections}}
  <div class="card-footer text-center">
    <a href="viewconnections/{{$nickname}}"
       class="link-primary link-offset-2 link-underline-opacity-25 link-underline-opacity-100-hover">{{$viewconnections}}</a>
  </div>
  <!-- /.card-footer -->
  {{/if}}
</div>
