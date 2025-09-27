<div class="card mb-3">
	<div class="card-header">{{$desc}}</div>
  <div class="card-body">
	{{if $items}}
	<div class="contact-block-content">
		{{foreach $items as $item}}
		<div class="contact-block-div">
			<a class="contact-block-link mpfriend" href="{{$base}}/chanview?f=&url={{$item.xchan_url}}"><img class="contact-block-img mpfriend" src="{{$item.xchan_photo_s}}" alt="{{$item.xchan_name}}" title="{{$item.xchan_name}} [{{$item.xchan_addr}}]" /></a>
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
