<div class="col-md-3">
  <div class="card card-primary">
    <div class="card-header">
      <h3 class="card-title">Expandable</h3>
      <div class="card-tools"> 
        <button type="button" class="btn btn-tool" data-lte-toggle="card-collapse"> <i data-lte-icon="expand" class="bi bi-plus-lg"></i> <i data-lte-icon="collapse" class="bi bi-dash-lg"></i> </button> 
      </div> <!-- /.card-tools -->
    </div> <!-- /.card-header -->
    <div class="card-body" style="display: block; box-sizing: border-box;">
      The body of the card
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
