<div id="peoplefind-sidebar" class="mb-3">
  <div class="h4 mt-0">
	{{$findpeople}}
  </div>
	<form action="directory" method="post" />
		<div class="input-group mb-0">
			<input class="form-control mb-0" type="text" name="search" title="{{$hint}}{{if $advanced_search}}{{$advanced_hint}}{{/if}}" placeholder="{{$desc}}" />
			<button class="btn btn-secondary" type="submit" name="submit"><i class="bi bi-search"></i></button>
		</div>
	</form>
	<ul class="flex-column" style="list-style: none;">
		{{if $similar}}<li class="nav-item h6 mt-0"><a class="nav-link" href="match" >{{$similar}}</a></li>{{/if}}
		{{if $loggedin}}<li class="nav-item h6 mt-0"><a class="nav-link" href="directory?f=&suggest=1" >{{$suggest}}</a></li>{{/if}}
		<li class="nav-item h6 mt-0"><a class="nav-link" href="randprof" >{{$random}}</a></li>
		{{if $loggedin}}{{if $inv}}<li class="nav-item h6 mt-0"><a class="nav-link" href="invite" >{{$inv}}</a></li>{{/if}}{{/if}}
	</ul>
</div>
