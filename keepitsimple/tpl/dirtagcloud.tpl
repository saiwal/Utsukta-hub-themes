<div class="dirtagblock">
  <div class="h6">{{$title}}</div>
  <span class="tagcloud group">
    {{foreach $tags as $tag}}
    <a href="{{$baseurl}}{{$tag['term']}}">#{{$tag['term']}}</a>
    {{/foreach}}
  </span>

</div>
