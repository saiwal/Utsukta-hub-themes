<div class="contact-entry-wrapper" id="contact-entry-wrapper-{{$contact.id}}" >
	<div class="contact-entry-photo-wrapper" >
		<a href="{{$contact.link}}" title="{{$contact.img_hover}}" ><img class="contact-block-img img-thumbnail" src="{{$contact.thumb}}" alt="{{$contact.name}}" loading="lazy" /></a>
		{{if $contact.perminfo}}{{include "connstatus.tpl" perminfo=$contact.perminfo}}{{/if}}
	</div>
	<div class="contact-entry-photo-end" ></div>
	<div class="contact-entry-name" id="contact-entry-name-{{$contact.id}}" >{{$contact.name}}</div>
	<div class="contact-entry-end" ></div>
</div>
