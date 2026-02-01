<div class="tagblock mb-3">
	<div class="lcars-text-bar"><span>{{$title}}</span></div>
  <span class="pillbox">
    {{foreach $tags as $tag}}
    <button class="pill" href="{{$baseurl}}{{$tag.0}}">#{{$tag.0}}</button>
    {{/foreach}}
  </span>
</div>
