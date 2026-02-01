<div class="{{if $class}} {{$class}}{{/if}} mb-3">
  {{if $title}}
  <div class="lcars-text-bar">
		<span>
    {{$title}}
		</span>
  </div>
  {{/if}}
    {{if $desc}}<div class="desc">{{$desc}}</div>{{/if}}

    <div class="pillbox">
      {{foreach $items as $item}}
      <button onclick="playSoundAndRedirect('audio2','{{$item.url}}')"
          class="pill{{if $item.selected}} blink{{/if}}">{{$item.label}}</button>
				{{/foreach}}
    </div>
</div>
