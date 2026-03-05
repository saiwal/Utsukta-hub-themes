<div class="tagblock">
  <div class="h5">{{$title}} </div>
  <span class="entry__tag-list">
    {{foreach $tags as $tag}}
    <a href="{{$baseurl}}{{$tag.0}}">#{{$tag.0}}</a>
    {{/foreach}}
  </span>
</div>
