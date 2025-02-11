<div class="page">
	<div class="generic-content-wrapper" id="page-content-wrapper" >
		{{if $title}}
		<div class="section-title-wrapper app-content-header">
			<h3 class="page-title">{{$title}}</h3>
		</div>
		{{/if}}
		<div class="section-content-wrapper">
			<div class="page-author"><a class="page-author-link" href="{{$auth_url}}">{{$author}}</a></div>
			<div class="page-date">{{$date}}</div>
			<div class="page-body">{{$body}}</div>
			{{if $edit_link}}
			<div class="position-fixed bottom-0 end-0 m-3 z-1">
				<a href="{{$edit_link}}" class="btn btn-lg btn-primary rounded-circle"><i class="bi bi-pencil"></i></a>
			</div>
			{{/if}}
		</div>
	</div>
</div>
