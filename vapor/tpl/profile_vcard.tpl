<div class="rounded mb-3 vcard-card h-card">
	<div class="card mb-2">
		<div class="position-relative">
			<div id="cover-photo-wrapper" class="overflow-hidden w-100">
				<img class="img-fluid card-img-top" src="{{$cover.url}}" alt="" style="">
			</div>
			{{if $connect}}
			<a href="{{$connect_url}}" class="btn btn-success btn-sm m-2 position-absolute top-0 end-0" rel="nofollow">
				<i class="bi bi-plus-lg"></i> {{$connect}}
			</a>
			{{/if}}
			<div id="profile-cover-text" class="p-2 position-absolute bottom-0 w-100">
				<div class="text-truncate h3 mb-0 lh-sm">
					<strong class="text-white fn p-name">{{$profile.fullname}}{{if $profile.online}}<i class="bi bi-wifi text-success ps-2" title="{{$profile.online}}"></i>{{else}}<i class="bi bi-wifi-off text-danger ps-2" title="{{$profile.online}}"></i>{{/if}}</strong>
				</div>
				<div class="text-truncate">
					<span class="text-white p-adr">{{$profile.reddress}}</span>
				</div>
			</div>
			{{if $editmenu.multi}}
			<div class="dropdown position-absolute bottom-0 end-0 m-2">
				<a class="profile-edit-side-link text-white" data-bs-toggle="dropdown" href="#" ><i class="bi bi-pencil" title="{{$editmenu.edit.1}}"></i></a>
				<div class="dropdown-menu dropdown-menu-end" role="menu">
					{{foreach $editmenu.menu.entries as $e}}
					<a href="profiles/{{$e.id}}" class="dropdown-item"><img class="menu-img-1" src='{{$e.photo}}'> {{$e.profile_name}}</a>
					{{/foreach}}
					{{if $editmenu.menu.cr_new}}
					<a href="profiles/new" id="profile-listing-new-link" class="dropdown-item">{{$editmenu.menu.cr_new}}</a>
					{{/if}}
				</div>
			</div>
			{{elseif $editmenu}}
			<div class="position-absolute bottom-0 end-0 m-2">
				<a class="profile-edit-side-link text-white" href="{{$editmenu.edit.0}}" ><i class="bi bi-pencil" title="{{$editmenu.edit.1}}"></i></a>
			</div>
			{{else}}
			<div class="position-absolute bottom-0 end-0 m-2">
				<a class="profile-edit-side-link text-white" href="profile/{{$profile.channel_address}}" ><i class="bi bi-box-arrow-up-right" title="{{$editmenu.edit.1}}"></i></a>
			</div>
			{{/if}}
		</div>
		<div class="d-flex">
			<div id="profile-photo-wrapper" class="bg-body-secondary overflow-hidden" style="min-width: 5rem; min-height: 5rem;">
				<img class="u-photo card-img-bottom" src="{{$profile.thumb}}?rev={{$profile.picdate}}" alt="{{$profile.fullname}}" style="width: 5rem; height: 5rem;">
			</div>
			{{if $profile.pdesc}}
			<div class="m-2 small text-break">{{$profile.pdesc}}</div>
			{{else}}
			<div class="m-2 small">
				<span class="opacity-50">{{$no_pdesc}}</span>
			</div>
			{{/if}}
		</div>
	</div>
	{{if $details && ($location || $hometown || $gender || $marital || $homepage)}}
	<div class="vcard rounded ps-2 pe-2">
		{{if $location}}
		<dl class="mb-0 pb-1 rounded">
			<dt class="location-label">{{$location}}</dt>
			<dd class="adr h-adr">
				{{if $profile.address}}
				<div class="street-address p-street-address">{{$profile.address}}</div>
				{{/if}}
				<div class="city-state-zip">
					<span class="postal-code p-postal-code">{{$profile.postal_code}}</span>
					<span class="locality p-locality">{{$profile.locality}}</span>
				</div>
				{{if $profile.region}}
				<div class="region p-region">{{$profile.region}}</div>
				{{/if}}
				{{if $profile.country_name}}
				<div class="country-name p-country-name">{{$profile.country_name}}</div>
				{{/if}}
			</dd>
		</dl>
		{{/if}}
		{{if $hometown}}
		<dl class="mb-0 pb-1 rounded">
			<dt class="hometown-label">{{$hometown}}</dt>
			<dd class="p-hometown">{{$profile.hometown}}</dd>
		</dl>
		{{/if}}
		{{if $gender}}
		<dl class="mb-0 pb-1 rounded">
			<dt class="gender-label">{{$gender}}</dt>
			<dd class="p-gender">{{if $profile.gender_icon}}<i class="bi bi-{{$profile.gender_icon}}"></i>&nbsp;{{/if}}{{$profile.gender}}</dd>
		</dl>
		{{/if}}
		{{if $marital}}
		<dl class="mb-0 pb-1 rounded">
			<dt class="marital-label"><span class="heart"><i class="bi fa-heart"></i>&nbsp;</span>{{$marital}}</dt>
			<dd class="marital-text">{{$profile.marital}}</dd>
		</dl>
		{{/if}}
		{{if $homepage}}
		<dl class="mb-0 pb-1 rounded">
			<dt class="homepage-label">{{$homepage}}</dt>
			<dd class="homepage-url u-url">{{$profile.homepage}}</dd>
		</dl>
		{{/if}}
	</div>
	{{/if}}
	{{if $details}}
	<div class="hcard-addon"></div>
	{{/if}}

</div>
{{if $details}}
{{$chanmenu}}
{{$contact_block}}
{{/if}}


