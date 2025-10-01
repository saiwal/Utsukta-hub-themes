<div class="col contact-block-div{{if $class}} {{$class}}{{/if}}">
  <div class="fs-8 position-relative">
  <a class="btn fw-bold fs-7 text-body-secondary text-truncate w-100 p-0" href="{{if $click}}#{{else}}{{$url}}{{/if}}" {{if $click}}onclick="{{$click}}"{{/if}}>
  <img class="img-fluid rounded-circle position-relative p-1" src="{{$photo}}" alt="" title="{{$title}}">
  </a>
    {{if $perminfo}}<span class="position-absolute top-0 end-0 p-1">
    {{include "connstatus.tpl"}}</span>
    {{/if}}
  </div>
</div>
