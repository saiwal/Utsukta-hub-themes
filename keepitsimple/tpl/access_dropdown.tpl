{{if $access_list}}
	<div class="dropdown-header text-uppercase h6 m-0">{{$access_header}}</div>
	{{$access_list}}
{{/if}}

{{if $guest_access_list}}
	{{if $access_list}}
		<div class="dropdown-divider"></div>
	{{/if}}
	<div class="dropdown-header text-uppercase h6 m-0">{{$guest_access_header}}</div>
	{{$guest_access_list}}
{{/if}}

{{if $ocap_access_list}}
	{{if $access_list || $guest_access_list}}
		<div class="dropdown-divider"></div>
	{{/if}}
	<div class="dropdown-header text-uppercase h6 m-0">{{$ocap_access_header}}</div>
	{{$ocap_access_list}}
{{/if}}
