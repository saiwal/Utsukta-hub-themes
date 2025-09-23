<div class="card mb-3">
  <!--<h3 class="card-header">Card header</h3>-->
  <img class="d-block user-select-none" width="100%" style="font-size:1.125rem;text-anchor:middle"
    src="{{$cover.url}}"></img>
  <div class="card-body">
    <div class="d-flex">
      <div id="profile-photo-wrapper" class="overflow-hidden me-2" style="min-width: 5rem; min-height: 5rem;">
        <img class="img-thumbnail shadow" src="{{$profile.thumb}}?rev={{$profile.picdate}}" alt="{{$profile.fullname}}">
      </div>
      <div class="vstack d-flex flex-column justify-content-start mt-auto mb-auto">
        <div class="card-title">{{$profile.fullname}}{{if $profile.online}}<i class="bi bi-wifi text-success ps-2"
            title="{{$profile.online}}"></i>{{else}}<i class="bi bi-wifi-off text-danger ps-2"
            title="{{$profile.online}}"></i>{{/if}}
        </div>
        <div class="card-subtitle text-muted text-break">{{$profile.reddress}}</div>
      </div>
      {{if $connect}}
      <a href="{{$connect_url}}" class="btn btn-success btn-sm m-2 position-absolute top-0 end-0" rel="nofollow">
        <i class="bi bi-plus-lg"></i> {{$connect}}
      </a>
      {{/if}}
    </div>
  </div>

  <div class="card-body pt-0">
    {{if $profile.pdesc}}
    <p class="card-text">{{$profile.pdesc}}</p>
    {{else}}
    <p class="card-text text-muted">
      {{$no_pdesc}}
    </p>
    {{/if}}

    {{if $details && ($location || $hometown || $gender || $marital || $homepage)}}
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
  </div>
  <div class="card-footer">
    <a href="/profile/{{$profile.channel_address}}" class="float-end"><i class="bi bi-info-square"></i></a>
  </div>
</div>
{{if $details}}
{{$chanmenu}}
{{$contact_block}}
{{/if}}
