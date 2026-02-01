<div class="lcars-text-bar the-end">
	<span>{{if $entry.public_forum}}<i class="bi bi-chat"
			title="{{$entry.forum_label}} @{{$entry.nickname}}+"></i>&nbsp;{{/if}}<a
			href="{{$entry.profile_link}}">{{$entry.name}}</a>{{if $entry.online}}&nbsp;<i class="bi bi-asterisk online-now"
			title="{{$entry.online}}"></i>{{/if}}</span>
</div>
<div class="directory-collapse flush">
	<div class="pics-left mb-0">
		<a href="{{$entry.profile_link}}">
			<img src="{{$entry.photo}}" width="100px" height="100px">
		</a>
	</div>
	<h3 class="go-big go-right">{{$entry.address}}</h3>
	<p class="flush">
		{{$entry.about}}
	</p>
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
<div class="buttons flush the-end">
			{{if $entry.censor_2}}
			<button class="btn btn-danger btn-sm {{$entry.censor_2_class}}" href="{{$entry.censor_2}}">
				{{$entry.censor_2_label}}</button>
			{{/if}}
			{{if $entry.censor}}
			<button class="btn btn-warning btn-sm {{$entry.censor_class}}" href="{{$entry.censor}}">
				{{$entry.censor_label}}</button>
			{{/if}}
			{{if $entry.ignlink}}
			<button class="btn btn-info btn-sm" href="{{$entry.ignlink}}"> {{$entry.ignore_label}}</button>
			{{/if}}
			{{if $entry.connect}}
			<button class="btn btn-success btn-sm" href="{{$entry.connect}}"><i class="bi bi-plus connect-icon"></i>
				{{$entry.conn_label}}</button>
			{{/if}}
</div>
</div>
