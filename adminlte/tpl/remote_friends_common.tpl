<div class="card mb-3">
	<div class="card-header">{{$desc}}</div>
  <div class="card-body">
	{{if $items}}
	<div class="contact-block-content">
		{{foreach $items as $item}}
		<div class="contact-block-div">
        <a class="btn fw-bold fs-7 text-body-secondary text-truncate w-100 p-0" href="{{$base}}/chanview?f=&url={{$item.xchan_url}}"><img class="img-fluid rounded-circle position-relative p-1" src="{{$item.xchan_photo_s}}" alt="{{$item.xchan_name}}" title="{{$item.xchan_name}}">
  </a>
		</div>
		{{/foreach}}
	</div>
	{{/if}}
  </div>
	{{if $linkmore}}
  <div class="card-footer text-center">
    <a href="{{$base}}/common/{{$uid}}">{{$viewconnections}}</a>
  </div>
  <!-- /.card-footer -->
	{{/if}}

</div>
