<div class="mb-3">
  <!--<h3 class="card-header">Card header</h3>-->
    <div class="d-flex">
      <div id="profile-photo-wrapper" class="overflow-hidden me-2"
        style="min-width: 5rem; min-height: 5rem;">
        <img class="img-thumbnail" src="{{$profile.thumb}}?rev={{$profile.picdate}}"
          alt="{{$profile.fullname}}" style="width: 5rem; height: 5rem;">
      </div>
      <div class="vstack d-flex flex-column justify-content-start mt-auto mb-auto">
        <div class="card-title">{{$profile.fullname}}{{if $profile.online}}<i class="bi bi-wifi text-success ps-2"
            title="{{$profile.online}}"></i>{{else}}<i class="bi bi-wifi-off text-danger ps-2"
            title="{{$profile.online}}"></i>{{/if}}
        </div>
        <div class="card-subtitle text-muted">{{$profile.reddress}}</div>
      </div>
      {{if $connect}}
      <a href="{{$connect_url}}" class="btn btn-success btn-sm m-2 position-absolute top-0 end-0" rel="nofollow">
        <i class="bi bi-plus-lg"></i> {{$connect}}
      </a>
      {{/if}}
    </div>

    {{if $profile.pdesc}}
    <p class="card-text">{{$profile.pdesc}}</p>
    {{else}}
    <p class="card-text text-muted">
      {{$no_pdesc}}
    </p>
    {{/if}}

  {{if $details && ($location || $hometown || $gender || $marital || $homepage)}}
  <ul class="list-group list-group-flush">
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
