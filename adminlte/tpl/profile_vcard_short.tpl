{{$contact_block}}

	{{if $connect}}
	<div class="connect-btn-wrapper"><a href="{{$connect_url}}" class="btn btn-block btn-success btn-sm"><i class="bi bi-plus-lg"></i> {{$connect}}</a></div>
	{{/if}}


{{$rating}}

	{{if $pdesc}}<div class="title">{{$profile.pdesc}}</div>{{/if}}
<dl class="row">
    {{if $location}}
      <dt class="location-label col-sm-4">{{$location}}</dt>
      <dd class="adr h-adr col-sm-8">
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
    {{/if}}
    {{if $hometown}}
      <dt class="col-sm-4 hometown-label">{{$hometown}}</dt>
      <dd class="p-hometown col-sm-8">{{$profile.hometown}}</dd>
    {{/if}}
    {{if $gender}}
      <dt class="col-sm-4 gender-label">{{$gender}}</dt>
      <dd class="p-gender col-sm-8">{{$profile.gender}}</dd>
    {{/if}}
    {{if $marital}}
      <dt class="marital-label col-sm-4"><span class="heart"><i class="bi fa-heart"></i>&nbsp;</span>{{$marital}}</dt>
      <dd class="marital-text col-sm-8">{{$profile.marital}}</dd>
    {{/if}}
    {{if $homepage}}
      <dt class="card-link col-sm-4">{{$homepage}}</dt>
      <dd class=" col-sm-8">
        {{$profile.homepage}}
      </dd>
  {{/if}}
  {{/if}}
</dl>

{{$chanmenu}}



