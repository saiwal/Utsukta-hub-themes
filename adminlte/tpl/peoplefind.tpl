<div id="peoplefind-sidebar" class="card mb-3">
  <div class="card-header">
	<h3 class="card-title">{{$findpeople}}</h3>
  </div>
  <div class="card-body">
	<form action="directory" method="post" />
		<div class="input-group mb-3">
			<input class="form-control" type="text" name="search" title="{{$hint}}{{if $advanced_search}}{{$advanced_hint}}{{/if}}" placeholder="{{$desc}}" />
			<button class="btn btn-outline-secondary" type="submit" name="submit"><i class="bi bi-search"></i></button>
		</div>
	</form>
	<ul class="nav nav-pills flex-column">
		{{if $similar}}<li class="nav-item"><a class="nav-link" href="match" >{{$similar}}</a></li>{{/if}}
		{{if $loggedin}}<li class="nav-item"><a class="nav-link" href="directory?f=&suggest=1" >{{$suggest}}</a></li>{{/if}}
		<li class="nav-item"><a class="nav-link" href="randprof" >{{$random}}</a></li>
		{{if $loggedin}}{{if $inv}}<li class="nav-item"><a class="nav-link" href="invite" >{{$inv}}</a></li>{{/if}}{{/if}}
	</ul>
  </div>
</div>
