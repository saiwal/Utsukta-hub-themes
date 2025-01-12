
<div class="card">
  <div class="card-header">
    <h3 class="card-title">{{$contacts}}</h3>
    <div class="card-tools">
      <button type="button" class="btn btn-tool" data-lte-toggle="card-collapse">
        <i data-lte-icon="expand" class="bi bi-plus-lg"></i>
        <i data-lte-icon="collapse" class="bi bi-dash-lg"></i>
      </button>
      <button type="button" class="btn btn-tool" data-lte-toggle="card-remove">
        <i class="bi bi-x-lg"></i>
      </button>
    </div>
  </div>
  {{if $micropro}}
  <!-- /.card-header -->
  <div class="card-body p-0">
    <div class="row text-center m-1">
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
    <a href="viewconnections/{{$$nickname}}"
       class="link-primary link-offset-2 link-underline-opacity-25 link-underline-opacity-100-hover">{{$viewconnections}}</a>
  </div>
  <!-- /.card-footer -->
  {{/if}}
</div>
