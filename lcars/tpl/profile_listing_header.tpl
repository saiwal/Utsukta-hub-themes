<div class="generic-content-wrapper">
  <div class="section-title-wrapper app-content-header">
		<div class="lcars-text-bar"><span>{{$header}}</span></div>
		<div class="buttons the-end flush">
    <button class="float-end" href="{{$cr_new_link}}" id="profile-listing-new-link"
      title="{{$cr_new}}"><i class="bi bi-plus-lg"></i>&nbsp;{{$cr_new}}</button>
		</div>
  </div>
  <div class="row row-cols-1 row-cols-md-2">
    {{$profiles}}
  </div>
</div>
