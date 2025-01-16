<div class="col">
  <div class="card card-outline mb-4">
    <div class="card-header">
      <h3 class="card-title"><i class="bi bi-{{$icon}} generic-icons-nav"></i> <a class="text-decoration-none" href="{{$url}}">{{$label}}</a></h3>
      <div class="card-tools"> 
        <button type="button" class="btn btn-tool" data-lte-toggle="card-collapse"> <i data-lte-icon="expand" class="bi bi-plus-lg"></i> <i data-lte-icon="collapse" class="bi bi-dash-lg"></i> </button> 
      </div> <!-- /.card-tools -->
    </div> <!-- /.card-header -->
    <div class="card-body" style="display: block; box-sizing: border-box;">
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
    </div> <!-- /.card-body -->
  </div> <!-- /.card -->
</div>

