<div class="col-6 p-2">
  <div class="fs-8 position-relative">
  <a class="btn fw-bold fs-7 text-body-secondary text-truncate w-100 p-0" href="{{$url}}">
  <img class="img-fluid rounded-circle position-relative p-1" src="{{$photo}}" alt="" title="{{$title}}">
    {{$title}}
  </a>
    {{if $perminfo}}<span class="position-absolute top-0 end-0">
    {{include "connstatus.tpl"}}</span>
    {{/if}}
  </div>
</div>
