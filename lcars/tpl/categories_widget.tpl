<div id="categories-sidebar" class="mb-3">
  <div class="lcars-text-bar">
		<span>
    {{$title}}
		</span>
  </div>
  <span class="pillbox">
    <button class="pill" href="{{$base}}">{{$all}}</button>
    {{foreach $terms as $term}}{{if $term.name}}
		<button class="pill" href="{{$base}}/?cat={{$term.name|escape:'url'}}">{{$term.name}}</button>
    {{/if}}
    {{/foreach}}
  </span>
</div>
