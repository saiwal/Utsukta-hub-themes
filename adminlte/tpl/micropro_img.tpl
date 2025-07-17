  <a role="button" class="btn text-body-secondary text-truncate" href="{{if $click}}#{{else}}{{$url}}{{/if}}" {{if $click}}onclick="{{$click}}"{{/if}}>
  <img class="img-fluid rounded-circle position-relative p-1" src="{{$photo}}" alt="" title="{{$title}}">
    {{$title}}
  </a>
    {{if $perminfo}}<span class="position-absolute top-0 end-0 p-1">
    {{include "connstatus.tpl"}}</span>
    {{/if}}
