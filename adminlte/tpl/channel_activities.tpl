<div class="mb-1 text-uppercase">
	<a href="{{$url}}"><i class="bi bi-{{$icon}} generic-icons-nav"></i> {{$label}}</a>
</div>
<div class="row row-cols-1 row-cols-sm-3 g-4 mb-4">
	{{foreach $items as $i}}
	<div class="col">
		<div class="card">
			<a href="{{$i.url}}" class="text-reset">
				<div class="card-body clearfix">
					{{if $i.title}}
						<strong>{{$i.title}}</strong>
						<hr>
					{{/if}}
					{{$i.summary}}
				</div>
				<div class="card-footer text-muted autotime" title="{{$i.footer}}">{{$i.footer}}</div>
			</a>
		</div>
	</div>
	{{/foreach}}
</div>
