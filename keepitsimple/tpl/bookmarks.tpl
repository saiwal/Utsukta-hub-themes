<div class="generic-content-wrapper-styled">
	<header class="entry__header">
		<h2 class="entry__title h1">{{$title1}}
		</h2>
	</header>
	{{foreach $bookmarks as $bm}}
	{{$bm}}
	{{/foreach}}
	<header class="entry__header">
		<h2 class="entry__title h1">{{$title2}}
		</h2>
	</header>

	{{foreach $conn_bookmarks as $bm}}
	{{$bm}}
	{{/foreach}}
</div>
