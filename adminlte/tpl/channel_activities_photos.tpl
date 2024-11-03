<div class="col">
  <div class="card card-primary mb-4">
    <div class="card-header">
      <h3 class="card-title"><i class="bi bi-{{$icon}} generic-icons-nav"></i> <a href="{{$url}}">{{$label}}</a></h3>
      <div class="card-tools"> 
        <button type="button" class="btn btn-tool" data-lte-toggle="card-collapse"> <i data-lte-icon="expand" class="bi bi-plus-lg"></i> <i data-lte-icon="collapse" class="bi bi-dash-lg"></i> </button> 
      </div> <!-- /.card-tools -->
    </div> <!-- /.card-header -->
    <div class="card-body" style="display: block; box-sizing: border-box;">
      <table class="table">
        <thead>
            <tr>
                <th>Title</th>
                <th>Date</th>
            </tr>
        </thead>
        <tbody>
	          {{foreach $items as $i}}
            <tr class="align-middle">
                <td><a href="{{$i.url}}">{{$i.alt}}</a></td>
                <td>{{$i.edited}}</td>
            </tr>
            {{/foreach}}
        </tbody>
    </table>     
    </div> <!-- /.card-body -->
  </div> <!-- /.card -->
</div>

