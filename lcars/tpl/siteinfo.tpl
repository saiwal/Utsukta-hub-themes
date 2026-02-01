<div class="app-content">
	<div class="lcars-text-bar the-end">
		<span>{{$title}}</span>
	</div>
	<h3>{{$sitename}}</h3>
	<p class="card-text">{{if $site_about}}{{$site_about}}{{else}}--{{/if}}</p>
	<div class="buttons">
		<button onclick="playSoundAndRedirect('audio3','help/TermsOfService')" class="button-bluey">{{$terms}}</button>
	</div>
	<ul class="list-group lcars-list">
		{{if $addons.1}}
		<li>{{$addons.0}}:
					{{foreach $addons.1 as $addon}}
					<span class="badge text-bg-primary">{{$addon}}</span>
					{{/foreach}}
		</li>
		{{/if}}

		{{if $themes.1}}
		<li>
					{{$themes.0}}:
					{{foreach $themes.1 as $theme}}
					<span class="badge text-bg-primary">{{$theme}}</span>
					{{/foreach}}
		</li>
		{{/if}}

		{{if $blocked_sites.1}}
		<li>
					{{$blocked_sites.0}}:
					{{foreach $blocked_sites.1 as $site}}
					<span class="badge text-bg-danger">{{$site}}</span>
					{{/foreach}}
		</li>
		{{/if}}
	</ul>
	{{if $admin_about}}<span class="text-warning">{{$admin_about}}</span>{{else}}--{{/if}}

	<div class="lcars-text-bar the-end">
		<span>{{$prj_header}}</span>
	</div>
		<p>{{$prj_name}} ({{$z_server_role}})
			{{if $prj_version}}
			{{$prj_version}}</p>
		{{/if}}
	<ul class="lcars-list">
		<li>
				{{$prj_linktxt}}:
				<a href="{{$prj_link}}" class="card-link">{{$prj_link}}</a>
		</li>
		<li>
				{{$prj_srctxt}}:
				<a href="{{$prj_src}}" class="card-link">{{$prj_src}}</a>
		</li>
		<li>
				{{$prj_transport}} {{$transport_link}}
		</li>
		<li>
			{{$additional_text}} {{$additional_fed}}
		</li>
	</ul>

</div>
