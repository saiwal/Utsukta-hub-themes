<div class="col-3 p-2">
  <img class="img-fluid rounded-circle" src="{{$photo}}" alt="" title="{{$title}}">
  <a class="btn fw-bold fs-7 text-body-secondary text-truncate w-100 p-0" href="{{$url}}">
    {{$title}}
  </a>
  <div class="fs-8">
    {{if $perminfo}}
    {{include "connstatus.tpl"}}
    {{/if}}
  </div>
</div>
