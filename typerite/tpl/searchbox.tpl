<form action="{{$action_url}}" method="get" class="mb-0">
	<input type="hidden" name="f" value="" />
	<div id="{{$id}}" class="hstack gap-4">
		<input class="full-width mb-0" type="text" name="search" id="search-text" value="{{$s}}" onclick="this.submit();" />
		<div class="hstack">
		<button type="submit" name="submit" class="" id="search-submit" value="{{$search_label}}"><i class="bi bi-search"></i></button>
		{{if $savedsearch}}
		<button type="submit" name="searchsave" class="link" id="search-save" value="{{$save_label}}"><i class="bi bi-save"></i></button>
		</div>
		{{/if}}
	</div>
</form>
