<div class="entry">
<div class="large-12 column">
	<h3 class="half-bottom d-flex justify-content-between">
		<a	href='{{$entry.profile_link}}'>{{$entry.name}}{{if $entry.public_forum}}<i class="bi p-3 bi-chat-quote" title="{{$entry.forum_label}} @{{$entry.nickname}}+"></i>{{/if}}{{if $entry.online}}&nbsp;<i class="bi bi-asterisk online-now" title="{{$entry.online}}"></i>{{/if}}</a>
		<span class="text-muted small">{{$entry.address}}</span>
	</h3>
</div>

<div class="row directory-collapse">
	<div class="large-3 tab-5 mob-12 column">
		<a href="{{$entry.profile_link}}" class="directory-profile-link" id="directory-profile-link-{{$entry.hash}}"><img class="directory-photo-img h-pull-left" src="{{$entry.photo}}" alt="{{$entry.alttext}}" title="{{$entry.alttext}}" loading="lazy" /></a>
		{{if $entry.pdesc}}
			<p><strong>{{$entry.pdesc_label}}</strong> {{$entry.pdesc}}</p>
		{{/if}}
	</div>

	<div class="large-9 tab-7 mob-12 column">
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
	</div>
  <div class="d-flex gap-2 justify-content-end">
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
