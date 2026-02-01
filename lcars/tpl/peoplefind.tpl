<div id="peoplefind-sidebar" class="mb-3">
  <div class="lcars-text-bar">
		<span>
	{{$findpeople}}
		</span>
  </div>
  <div class="pillbox">
		{{if $similar}}<a class="pill" href="match" >{{$similar}}</a>>{{/if}}
		{{if $loggedin}}<a class="pill" href="directory?f=&suggest=1" >{{$suggest}}</a>{{/if}}
		<a class="pill" href="randprof" >{{$random}}</a>
		{{if $loggedin}}{{if $inv}}<a class="pill" href="invite" >{{$inv}}</a>{{/if}}{{/if}}
	</div>

	<form action="directory" method="post" />
		<div class="input-group d-flex">
			<input class="w-100 form-control" type="text" name="search" title="{{$hint}}{{if $advanced_search}}{{$advanced_hint}}{{/if}}" placeholder="{{$desc}}" />
		</div>
	</form>
</div>
