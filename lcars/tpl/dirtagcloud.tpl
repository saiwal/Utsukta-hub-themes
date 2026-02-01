<div class="dirtagblock mb-3">
	<div class="lcars-text-bar"><span>{{$title}}</span></div>
  <span class="entry__tag-list">
    {{foreach $tags as $tag}}
    <a href="{{$baseurl}}{{$tag['term']}}">#{{$tag['term']}}</a>
    {{/foreach}}
  </span>

</div>
