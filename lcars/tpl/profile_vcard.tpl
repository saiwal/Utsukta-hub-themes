<div class="mb-3">

  <div class="h4">{{$profile.fullname}}{{if $profile.online}}<i class="bi bi-wifi blink text-success ps-2"
      title="{{$profile.online}}"></i>{{else}}<i class="bi bi-wifi-off text-danger ps-2 blink"
      title="{{$profile.online}}"></i>{{/if}}
  </div>
  <div class="h5 mt-0 text-muted">{{$profile.reddress}}</div>
		<div class="pics-left">
    <img width="80" height="80" alt="sample-image" src="{{$profile.thumb}}">
		</div>
    {{if $profile.pdesc}}
    {{$profile.pdesc}}
    {{else}}
    {{$no_pdesc}}
    {{/if}}
  {{if $connect}}
  <a href="{{$connect_url}}" class="btn" rel="nofollow">
    <i class="bi bi-plus-lg"></i> {{$connect}}
  </a>
  {{/if}}

  <div class="clearfix"></div>

  {{if $details && ($location || $hometown || $gender || $marital || $homepage)}}
  <ul class="lcars-list">
    {{if $location}}
    <li>
      {{$location}} {{if $profile.address}}{{$profile.address}},{{/if}}
          {{$profile.postal_code}}
          {{$profile.locality}}
        {{if $profile.region}}
				{{$profile.region}}<br>
        {{/if}}
        {{if $profile.country_name}}
        {{$profile.country_name}}
        {{/if}}
    </li>
    {{/if}}
    {{if $hometown}}
    <li>
      {{$hometown}}
      {{$profile.hometown}}
    </li>
    {{/if}}
    {{if $gender}}
    <li>

      {{$gender}}
      {{$profile.gender}}
    </li>
    {{/if}}
    {{if $marital}}
    <li>

      <span class="heart"><i class="bi fa-heart"></i>&nbsp;</span>{{$marital}}
      {{$profile.marital}}
    </li>
    {{/if}}
    {{if $homepage}}
    <li>
      {{$homepage}}
        {{$profile.homepage}}
		</li>
  </ul>
  {{/if}}
  {{/if}}
</div>

{{if $details}}
{{$chanmenu}}
{{$contact_block}}
{{/if}}
