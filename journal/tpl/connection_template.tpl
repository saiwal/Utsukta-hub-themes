<div class="card mb-3">
  <div class="row g-0">

<div id="contact-entry-wrapper-{{$contact.id}}">
	<div class="section-subtitle-wrapper clearfix card-header border-bottom-0">
		<div class="float-end">
			{{if $contact.status}}
			{{foreach $contact.states as $state}}
			<span class="badge rounded-pill bg-danger text-white me-1" title="">{{$state}}</span>
			{{/foreach}}
			{{/if}}
			<span id="contact-role-{{$contact.id}}" class="badge bg-warning text-dark me-1" title="{{$role_label}}">{{$contact.role}}</span>
			<button type="button" class="btn btn-tool contact-edit" title="{{$contact.edit_hover}}" data-id="{{$contact.id}}">
				<i class="bi bi-pencil contact-edit-icon-{{$contact.id}}"></i>
			</button>

		</div>
        <div>{{include "connstatus.tpl" perminfo=$contact.perminfo}}{{if $contact.public_forum}}<i class="bi bi-chat" title="{{$group_label}}"></i>&nbsp;{{/if}}<a href="{{$contact.url}}" title="{{$contact.img_hover}}" >{{$contact.name}}</a>{{if $contact.phone}}&nbsp;<a class="btn btn-outline-secondary btn-sm" href="tel:{{$contact.phone}}" title="{{$contact.call}}"><i class=bi bi-phone connphone"></i></a>{{/if}}</div>
	</div>
	<div class="section-content-tools-wrapper card-body">
		<div class="contact-photo-wrapper" >
			<a href="{{$contact.url}}" title="{{$contact.img_hover}}" >
				<img class="directory-photo-img {{if $contact.classes}}{{$contact.classes}}{{/if}}" src="{{$contact.thumb}}" alt="{{$contact.name}}" loading="lazy" />
			</a>
		</div>
		<div class="contact-info">
			{{** if $contact.status}}
			<div class="contact-info-element">
				<span class="contact-info-label">{{$contact.status_label}}:</span> {{$contact.status}}
			</div>
			{{/if **}}
			{{if $contact.connected}}
			<div class="contact-info-element">
				<span class="contact-info-label">{{$contact.connected_label}}:</span> <span class="autotime" title="{{$contact.connected}}"></span>
			</div>
			{{/if}}
			{{if $contact.webbie}}
			<div class="contact-info-element">
				<span class="contact-info-label">{{$contact.webbie_label}}:</span> {{$contact.webbie}}
			</div>
			{{/if}}
			{{if $contact.network}}
			<div class="contact-info-element">
				<span class="contact-info-label">{{$contact.network_label}}:</span> {{$contact.network}} - <a href="{{$contact.recentlink}}" rel="nofollow noopener">{{$contact.recent_label}}</a>
			</div>
			{{/if}}
			{{if $contact.connect}}
			<a href="{{$contact.follow}}" class="btn btn-success btn-sm" title="{{$contact.connect_hover}}"><i class="bi bi-plus"></i> {{$contact.connect}}</a>
			{{/if}}
		</div>

	</div>
</div>
  </div>
</div>

