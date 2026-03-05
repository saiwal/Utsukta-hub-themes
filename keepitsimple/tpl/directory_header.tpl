<div class="generic-content-wrapper">
	<div class="section-title-wrapper clearfix app-content-header">
		<header class="entry__header">
		<h2 class="entry__title h1">{{$dirlbl}}{{if $search}}:&nbsp;{{$safetxt}}{{/if}}
		</h2>
	</header>
		<div class="btn-group float-end">
			<button type="button" class="btn btn-secondary btn-sm dropdown-toggle" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false" title="{{$sort}}">
				<i class="bi bi-arrow-down-up"></i>
			</button>
			<div class="dropdown-menu dropdown-menu-end">
				<a class="dropdown-item" href="directory?f=&order=date{{$suggest}}">{{$date}}</a>
				<a class="dropdown-item" href="directory?f=&order=normal{{$suggest}}">{{$normal}}</a>
				<a class="dropdown-item" href="directory?f=&order=reversedate{{$suggest}}">{{$reversedate}}</a>
				<a class="dropdown-item" href="directory?f=&order=reverse{{$suggest}}">{{$reverse}}</a>
			</div>
		</div>
	</div>
	{{foreach $entries as $entry}}
		{{include file="direntry.tpl"}}
	{{/foreach}}
	{{** make sure this element is at the bottom - we rely on that in endless scroll **}}
	<div id="page-end" class="float-start w-100"></div>
</div>
<div id="page-spinner" class="spinner-wrapper">
	<div class="spinner m"></div>
</div>
<script>
	$(document).ready(function() {
		loadingPage = false;
		{{if $directory_admin}}
		$(document).on('click', '.directory-censor', function (e) {
			e.preventDefault();

			let that = this;
			let url;
			let path;
			let severity;
			let parent = this.closest('.directory-actions');
			let el;

			url = new URL(that.href)

			severity = url.searchParams.get('severity');
			path = url.pathname;

			console.log(url.searchParams.get('severity'));

			$.get(
				path,
				{
					aj: 1,
					severity : severity
				},
				function(data) {
					console.log(data)
					if (data.success) {

						if (that.classList.contains('directory-censor-unsafe')) {
							severity = data.flag ? 0 : 1;
							el = parent.getElementsByClassName('directory-censor-hide')[0];
							if (el.classList.contains('active')) {
								el.classList.toggle('active');
								url.searchParams.set('severity', 2);
								el.href = url.toString();
							}
						}

						if (that.classList.contains('directory-censor-hide')) {
							severity = data.flag ? 0 : 2;
							el = parent.getElementsByClassName('directory-censor-unsafe')[0];
							if (el.classList.contains('active')) {
								el.classList.toggle('active');
								url.searchParams.set('severity', 1);
								el.href = url.toString();
							}
						}

						url.searchParams.set('severity', severity);
						that.href = url.toString();
						that.classList.toggle('active');

					}
				}
			);
		});
		{{/if}}
	});
</script>
