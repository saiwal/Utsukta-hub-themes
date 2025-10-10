<div class="tagblock mb-3">
  <div class="h4">{{$title}} </div>
  <span class="entry__tag-list">
    {{foreach $tags as $tag}}
    <a href="{{$baseurl}}{{$tag.0}}">#{{$tag.0}}</a>
    {{/foreach}}
  </span>
</div>
