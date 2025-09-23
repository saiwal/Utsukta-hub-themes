	<h3>Page does not exist</h3>
        <br /><br /><br />
		{{if $canadd}}
			<form id="new-page-form" action="/wiki/{{$channel_address}}/create/page" method="post" >
				<input type="hidden" name="resource_id" value="{{$resource_id}}">
				{{include file="field_input.tpl" field=$pageName}}
				{{if $typelock}}
				<input id="id_mimetype" type="hidden" name="mimetype" value="{{$lockedtype}}">
				{{else}}
				<div id="wiki_missing_page_options" style="display: none">
					{{$mimetype}}
				</div>
				<div class="float-end fakelink" onClick="openClose('wiki_missing_page_options')">
					{{$options}}
				</div>
				{{/if}}
				<button id="create-missing-page-submit" class="btn btn-primary" type="submit" name="submit" >{{$submit}}</button>
			</form>

<script>
	$('#create-missing-page-submit').click(function (ev) {
		$.post("wiki/{{$channel_address}}/create/page", {pageName: $('#id_missingPageName').val(), resource_id: window.wiki_resource_id, mimetype: $('#id_mimetype').val() },
		function(data) {
			if(data.success) {
				window.location = data.url;
			} else {
				window.console.log('Error creating page. ('+data.message+')');
			}
		}, 'json');
		ev.preventDefault();
	});

</script>
{{/if}}
