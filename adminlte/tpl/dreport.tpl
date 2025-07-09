<div class="generic-content-wrapper">
  <div class="card mb-4">
    <div class="card-header">
      <h3 class="card-title">{{$title}}</h3>
		{{if $table == 'item'}}
      <div class="card-tools">
        <button type="button" class="btn btn-secondary btn-sm dropdown-toggle" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false" title="{{$options}}">
          <i class="bi bi-gear"></i>
        </button>
        <div class="dropdown-menu">
          <a href="dreport/push?mid={{$mid}}" class="dropdown-item">{{$push}}</a>
        </div>
      </div>
		{{/if}}
    </div>
    <!-- /.card-header -->
    <div class="card-body p-0">
      <table class="table">
        {{if $entries}}
        {{foreach $entries as $e}}
        <tr>
          <td width="40%">{{$e.name}}</td>
          <td width="20%">{{$e.result}}</td>
          <td width="20%">{{$e.time}}</td>
        </tr>
        {{/foreach}}
        {{/if}}
        </table>
    </div>
    <!-- /.card-body -->
  </div>

