<div class="mb-3">
  <div class="row">
    <!-- Section 1: Profile Image + Name -->
    <div class="col-12 col-md-5 mb-2 mb-md-0 border-end">
      <div class="d-flex align-items-center mb-2">
        <a href="{{$entry.profile_link}}" class="flex-shrink-0 me-3"><img src="{{$entry.photo}}" alt="Profile Picture" class="img-thumbnail " style="width: 100px; height: 100px; object-fit: cover;"></a>
        <div class="flex-column">
        <h5 class="mb-0 text-wrap">{{if $entry.public_forum}}<i class="bi bi-chat" title="{{$entry.forum_label}} @{{$entry.nickname}}+"></i>&nbsp;{{/if}}<a href='{{$entry.profile_link}}' class="link-body-emphasis" >{{$entry.name}}</a>{{if $entry.online}}&nbsp;<i class="bi bi-asterisk online-now" title="{{$entry.online}}"></i>{{/if}}</h5>
          <p class="text-muted small text-break">{{$entry.address}}</p></div>
      </div>
    </div>

    <!-- Section 2: Other Details -->
    <div class="col-12 col-md-7 directory-collapse">
			{{if $entry.common_friends}}
      <p><strong>{{$entry.common_label}}</strong> {{$entry.common_count}}</p>
			{{/if}}
			{{if $entry.pdesc}}
      <p><strong>{{$entry.pdesc_label}}</strong> {{$entry.pdesc}}</p>
			{{/if}}
			{{if $entry.age}}
      <p><strong>{{$entry.age_label}}</strong> {{$entry.age}}</p>
			{{/if}}
			{{if $entry.location}}
      <p><strong>{{$entry.location_label}}</strong> {{$entry.location}}</p>
			{{/if}}
			{{if $entry.hometown}}
      <p><strong>{{$entry.hometown_label}}</strong> {{$entry.hometown}}</p>
			{{/if}}
			{{if $entry.homepage}}
      <p><strong>{{$entry.homepage}}</strong> {{$entry.homepageurl}}</p>
			{{/if}}
			{{if $entry.kw}}
      <p><strong>{{$entry.kw}}</strong> {{$entry.keywords}}</p>
			{{/if}}
			{{if $entry.about}}
      <p><strong>{{$entry.about_label}}</strong> {{$entry.about}}</p>
			{{/if}}
      <div class="d-flex gap-2 justify-content-end position-absolute bottom-0 end-0 m-2">
      {{if $entry.censor_2}}
			<a class="btn btn-danger btn-sm {{$entry.censor_2_class}}" href="{{$entry.censor_2}}"> {{$entry.censor_2_label}}</a>
			{{/if}}
			{{if $entry.censor}}
			<a class="btn btn-warning btn-sm {{$entry.censor_class}}" href="{{$entry.censor}}"> {{$entry.censor_label}}</a>
			{{/if}}
			{{if $entry.ignlink}}
			<a class="btn btn-info btn-sm" href="{{$entry.ignlink}}"> {{$entry.ignore_label}}</a>
			{{/if}}
			{{if $entry.connect}}
			<a class="btn btn-success btn-sm" href="{{$entry.connect}}"><i class="bi bi-plus connect-icon"></i> {{$entry.conn_label}}</a>
			{{/if}}
    </div>

    </div>
  </div>
</div>

