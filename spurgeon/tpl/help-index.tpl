<div class="h5">
	Index
</div>
<div class="accordion" id="accordion-helpindex">
  {{if $sections}}
    {{foreach $sections as $section => $links}}
  <div class="accordion-item">
    <span class="accordion-header">
      <button class="accordion-button collapsed h6 m-0" type="button" data-bs-toggle="collapse" data-bs-target="#{{$section}}"
        aria-expanded="false" aria-controls="{{$section}}">
        {{$section}}
      </button>
    </span>
    <div id="{{$section}}" class="accordion-collapse collapse" data-bs-parent="#accordion-helpindex">
      <div class="accordion-body list-group list-group-flush">
        {{foreach $links as $label => $url}}
        <li class="list-group-item"><a href="{{$url}}">{{$label}}</a></li>
        {{/foreach}}
      </div>
    </div>
  </div>
    {{/foreach}}
  {{else}}
  {{$contents}}
  {{/if}}

</div>
