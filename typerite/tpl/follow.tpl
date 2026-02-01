<div id="follow-sidebar" class="mb-3">
  <div class="h3 mt-0">{{$connect}}</div>
	<form action="follow" method="post" />
		<div class="input-group">
			<input class="form-control" type="text" name="url" title="{{$hint}}" placeholder="{{$desc}}" />
			<button class="btn btn-success" type="submit" name="submit" value="{{$follow}}" title="{{$follow}}"><i class="bi bi-plus-lg"></i></button>
		</div>
	</form>
	{{if $abook_usage_message}}
	<div class="usage-message" id="abook-usage-message">{{$abook_usage_message}}</div>
	{{/if}}
</div>
