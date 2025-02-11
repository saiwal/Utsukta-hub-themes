<div class="generic-content-wrapper">
	<div class="section-title-wrappe app-content-headerr">
		<h3>{{$title}}</h3>
	</div>
	<div class="section-content-wrapper clearfix">
		{{foreach $items as $item}}
		<div class="float-start mr-4">
			<a href="{{$item.url}}">
				<img class="contact-block-img" src="{{$item.photo}}" alt="{{$item.name}}" title="{{$item.name}} [{{$item.url}}]" />
			</a>
			<div>
				{{$item.name}}
			</div>
		</div>
		{{/foreach}}
	</div>
</div>
