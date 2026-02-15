<div class="generic-content-wrapper">
	<div class="section-title-wrapper app-content-header">
		<header class="entry__header">
			<h2 class="entry__title h1">{{$header}}
			</h2>
		</header>
		<a class="btn btn-success btn-sm float-end" href="{{$cr_new_link}}" id="profile-listing-new-link"
			title="{{$cr_new}}"><i class="bi bi-plus-lg"></i>&nbsp;{{$cr_new}}</a>
	</div>
	<div class="row row-cols-1 row-cols-md-2">
		{{$profiles}}
	</div>
</div>
