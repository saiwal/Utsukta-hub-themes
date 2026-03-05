<div id="peoplefind-sidebar" class="mb-3">
  <div class="h5">
	{{$findpeople}}
  </div>
  <ul class="list-unstyled">
		{{if $similar}}<li><a href="match" >{{$similar}}</a></li>{{/if}}
		{{if $loggedin}}<li><a href="directory?f=&suggest=1" >{{$suggest}}</a></li>{{/if}}
		<li><a href="randprof" >{{$random}}</a></li>
		{{if $loggedin}}{{if $inv}}<li><a href="invite" >{{$inv}}</a></li>{{/if}}{{/if}}
	</ul>

	<form action="directory" method="post" />
		<div class="input-group d-flex">
			<input class="u-fullwidth mb-0" type="text" name="search" title="{{$hint}}{{if $advanced_search}}{{$advanced_hint}}{{/if}}" placeholder="{{$desc}}" />
		</div>
	</form>
</div>
