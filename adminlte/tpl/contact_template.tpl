<div class="col text-center" id="contact-entry-wrapper-{{$contact.id}}" >
	<div class="contact-entry-photo-wrapper position-relative" >
		<a class="btn fw-bold fs-7 text-body-secondary text-truncate w-100 p-0 text-start" href="{{$contact.link}}" title="{{$contact.img_hover}}" ><img class="img-fluid  p-1" src="{{$contact.thumb}}" alt="{{$contact.name}}" loading="lazy" />
      {{$contact.name}}
    </a>
    <div class="position-absolute top-0 end-0 m-2">
		{{if $contact.perminfo}}{{include "connstatus.tpl" perminfo=$contact.perminfo}}{{/if}}
    </div>  
	</div>
</div>
