<div class="h3">
  <i class="bi bi-{{$icon}} generic-icons-nav"></i> <a class="text-decoration-none link-dark" href="{{$url}}">{{$label}}</a>
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
