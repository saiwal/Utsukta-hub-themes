<div class="col-md-3">
  <div class="card card-primary">
    <div class="card-header">
      <h3 class="card-title"><i class="bi bi-{{$icon}} generic-icons-nav"></i> <a href="{{$url}}">{{$label}}</a></h3>
      <div class="card-tools"> 
        <button type="button" class="btn btn-tool" data-lte-toggle="card-collapse"> <i data-lte-icon="expand" class="bi bi-plus-lg"></i> <i data-lte-icon="collapse" class="bi bi-dash-lg"></i> </button> 
      </div> <!-- /.card-tools -->
    </div> <!-- /.card-header -->
    <div class="card-body" style="display: block; box-sizing: border-box;">
      <div class="list-group">
	      {{foreach $items as $i}}
        <a href="{{$i.url}}" class="list-group-item list-group-item-action">{{$i.alt}}</a>
        {{/foreach}}
      </div>
      <table class="table table-striped">
        <thead>
            <tr>
                <th style="width: 10px">#</th>
                <th>Task</th>
                <th>Progress</th>
                <th style="width: 40px">Label</th>
            </tr>
        </thead>
        <tbody>
            <tr class="align-middle">
                <td>1.</td>
                <td>Update software</td>
                <td>
                    <div class="progress progress-xs">
                        <div class="progress-bar progress-bar-danger" style="width: 55%"></div>
                    </div>
                </td>
                <td><span class="badge text-bg-danger">55%</span></td>
            </tr>
            <tr class="align-middle">
                <td>2.</td>
                <td>Clean database</td>
                <td>
                    <div class="progress progress-xs">
                        <div class="progress-bar text-bg-warning" style="width: 70%"></div>
                    </div>
                </td>
                <td> <span class="badge text-bg-warning">70%</span> </td>
            </tr>
        </tbody>
    </table>     
    </div> <!-- /.card-body -->
  </div> <!-- /.card -->
</div>
<div class="mb-1 text-uppercase">
	<a href="{{$url}}"><i class="bi bi-{{$icon}} generic-icons-nav"></i> {{$label}}</a>
</div>
<div id="photo-album" class="mb-4">
	{{foreach $items as $i}}
	<a href="{{$i.url}}" title="{{$i.alt}}">
		<img src="{{$i.src}}" width="{{$i.width}}" height="{{$i.height}}" alt="{{$i.alt}}">
		<div class='jg-caption rounded text-truncate autotime' title="{{$i.edited}}"></div>
	</a>
	{{/foreach}}
</div>
<script>
	$('#photo-album').justifiedGallery({
		border: 0,
		margins: 3,
		maxRowsCount: 1,
		waitThumbnailsLoad: false
	});
</script>
