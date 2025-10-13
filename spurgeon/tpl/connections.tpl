<div class="generic-content-wrapper">
	<div class="section-title-wrapper clearfix app-content-header">
		<div class="dropdown float-end">
			<button type="button" class="btn btn-success btn-sm" onclick="openClose('contacts-follow-form'); closeMenu('contacts-search-form'); $('#contacts-follow').focus();">
				<i class="bi bi-plus-lg"></i>&nbsp;Add
			</button>
			<button type="button" class="btn btn-primary btn-sm" onclick="openClose('contacts-search-form'); closeMenu('contacts-follow-form'); $('#contacts-search').focus();">
				<i class="bi bi-search"></i>&nbsp;{{$label}}
			</button>
			<button type="button" class="btn btn-secondary btn-sm dropdown-toggle" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false" title="{{$sort}}">
				<i class="bi bi-funnel"></i>
			</button>
			<div class="dropdown-menu dropdown-menu-end">
				{{foreach $tabs as $menu}}
				<a class="dropdown-item {{$menu.sel}}" href="{{$menu.url}}">{{$menu.label}}</a>
				{{/foreach}}
			</div>
		</div>
		{{if $finding}}<div class="h3 mt-0">{{$finding}}</div>{{else}}<div class="h3 mt-0">{{$header}}{{if $total}} ({{$total}}){{/if}}</div>{{/if}}
	</div>
	<div id="contacts-search-form" class="section-content-tools-wrapper">
		<form action="{{$cmd}}" method="get" name="contacts-search-form">
			<div class="input-group mb-3">
				<input type="text" name="search" id="contacts-search" class="form-control" onfocus="this.select();" value="{{$search}}" placeholder="{{$desc}}" />
				<input type="hidden" name="search_xchan" id="contacts-search-xchan" value=""/>
				<button id="contacts-search-submit" class="btn btn-primary" type="submit"><i class="bi bi-search"></i></button>
			</div>
		</form>
	</div>
	<div id="contacts-follow-form" class="section-content-tools-wrapper">
		{{if $abook_usage_message}}
		<div class="section-conten-info-wrapper">
			{{$abook_usage_message}}
		</div>
		{{/if}}
		<form action="follow" method="post">
			<div class="input-group mb-3">
				<input class="form-control" id="contacts-follow" type="text" name="url" title="Examples: bob@example.com, https://example.com/barbara" placeholder="Enter channel address">
				<button class="btn btn-success" type="submit" name="submit" value="Connect" title="Connect"><i class="bi bi-plus-lg"></i></button>
			</div>
		</form>
	</div>
	<div class="connections-wrapper clearfix">
		{{foreach $contacts as $contact}}
			{{include file="connection_template.tpl"}}
		{{/foreach}}
		<div id="page-end"></div>
	</div>
</div>
<script>$(document).ready(function() { loadingPage = false;});</script>
<div id="page-spinner" class="spinner-wrapper">
	<div class="spinner m"></div>
</div>
{{include file="contact_edit_modal.tpl"}}

