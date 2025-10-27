<div class="mb-3">

  <div class="h4">{{$profile.fullname}}{{if $profile.online}}<i class="bi bi-wifi text-success ps-2"
      title="{{$profile.online}}"></i>{{else}}<i class="bi bi-wifi-off text-danger ps-2"
      title="{{$profile.online}}"></i>{{/if}}
  </div>
  <div class="h5 mt-0 text-muted">{{$profile.reddress}}</div>
  <p>
    <a href="#"><img width="80" height="80" class="u-pull-left" alt="sample-image" src="{{$profile.thumb}}"></a>
    {{if $profile.pdesc}}
    {{$profile.pdesc}}
    {{else}}
    {{$no_pdesc}}
    {{/if}}
  </p>
  {{if $connect}}
  <a href="{{$connect_url}}" class="btn" rel="nofollow">
    <i class="bi bi-plus-lg"></i> {{$connect}}
  </a>
  {{/if}}



  {{if $details && ($location || $hometown || $gender || $marital || $homepage)}}
  <ul class="list-group list-group-flush ms-0">
    {{if $location}}
    <li class="list-group-item">
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
    </li>
    {{/if}}
    {{if $hometown}}
    <li class="list-group-item">
      <dt class="hometown-label">{{$hometown}}</dt>
      <dd class="p-hometown">{{$profile.hometown}}</dd>
    </li>
    {{/if}}
    {{if $gender}}
    <li class="list-group-item">

      <dt class="gender-label">{{$gender}}</dt>
      <dd class="p-gender">{{$profile.gender}}</dd>
    </li>
    {{/if}}
    {{if $marital}}
    <li class="list-group-item">

      <dt class="marital-label"><span class="heart"><i class="bi fa-heart"></i>&nbsp;</span>{{$marital}}</dt>
      <dd class="marital-text">{{$profile.marital}}</dd>
    </li>
    {{/if}}
    {{if $homepage}}
    <li class="list-group-item">
      <dt class="card-link">{{$homepage}}</dt>
      <dd class="">
        {{$profile.homepage}}
      </dd>
    </li>
  </ul>
  {{/if}}
  {{/if}}
</div>

{{if $details}}
{{$chanmenu}}
{{$contact_block}}
{{/if}}
