<div class="widget card mb-3">
	<h5 class="d-flex justify-content-between align-items-center">
		{{$title}}
		{{if $reset}}
		<a href="{{$reset.url}}" class="text-muted" title="{{$reset.title}}">
			<i class="bi bi-{{$reset.icon}}"></i>
		</a>
		{{/if}}
	</h5>
	{{$content}}
	{{if $name}}
	<div id="cid-filter-wrapper" class="notifications-textinput">
		<form method="get" action="{{$name.url}}" role="search">
			<div class="text-muted notifications-textinput-filter"><i class="bi bi-filter"></i></div>
			<input id="cid" type="hidden" value="" name="cid" />
			<input id="cid-filter" class="form-control form-control-sm{{if $name.sel}} {{$name.sel}}{{/if}}" type="text" value="" placeholder="{{$name.label}}" name="name" title="" />
		</form>
	</div>
	<script>
		$("#cid-filter").contact_autocomplete(baseurl + '/acl', 'a', true, function(data) {
			$("#cid").val(data.id);
		});
	</script>
	{{/if}}
</div>
