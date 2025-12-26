<div id="peoplefind-sidebar" class="mb-3">
  <div class="h5">
	{{$findpeople}}
  </div>
  <ul class="flex-column" style="list-style: none;">
		{{if $similar}}<li class="nav-item"><a class="nav-link" href="match" >{{$similar}}</a></li>{{/if}}
		{{if $loggedin}}<li class="nav-item"><a class="nav-link" href="directory?f=&suggest=1" >{{$suggest}}</a></li>{{/if}}
		<li class="nav-item"><a class="nav-link" href="randprof" >{{$random}}</a></li>
		{{if $loggedin}}{{if $inv}}<li class="nav-item"><a class="nav-link" href="invite" >{{$inv}}</a></li>{{/if}}{{/if}}
	</ul>

	<form action="directory" method="post" />
		<div class="input-group d-flex">
			<input class="u-fullwidth" type="text" name="search" title="{{$hint}}{{if $advanced_search}}{{$advanced_hint}}{{/if}}" placeholder="{{$desc}}" />
		</div>
	</form>
</div>
