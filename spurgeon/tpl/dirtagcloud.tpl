<div class="dirtagblock">
  <div class="h5">{{$title}}</div>
  <span class="entry__tag-list">
    {{foreach $tags as $tag}}
    <a href="{{$baseurl}}{{$tag['term']}}">#{{$tag['term']}}</a>
    {{/foreach}}
  </span>
</div>
