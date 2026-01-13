<div class="page">
	<div class="generic-content-wrapper" id="page-content-wrapper">
		{{if $title}}
		<div class="section-title-wrapper app-content-header d-flex justify-content-between">
			<h3 class="page-title">{{$title}}</h3>
			{{if $edit_link}}
				<div class="buttons mb-0 mt-0">
					<a role="button" class="" href="{{$edit_link}}" class="btn btn-lg btn-info shadow"><i
							class="bi bi-pencil"></i></a>
				</div>
				{{/if}}

		</div>
		{{/if}}
		<div class="section-content-wrapper-np">
			<div class="mb-3">
				<div class="page-author"><a class="page-author-link" href="{{$auth_url}}">{{$author}}</a></div>
				<div class="page-date">{{$date}}</div>
				<div class="page-body">{{$body}}</div>
			</div>
		</div>
	</div>
</div>
