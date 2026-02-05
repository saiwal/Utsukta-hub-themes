<div id="categories-sidebar" class="mb-3">
  <div class="h5">
    {{$title}}
  </div>
  <span class="entry__tags">
  <span class="entry__tag-list">
    <a class="{{if $sel_all}} bg-dark text-white{{/if}}" href="{{$base}}">{{$all}}</a>
    {{foreach $terms as $term}}{{if $term.name}}
    <a class="{{if $term.selected}}bg-dark text-white{{/if}}" href="{{$base}}/?cat={{$term.name|escape:'url'}}">{{$term.name}}</a>
    {{/if}}
    {{/foreach}}
  </span>
  </span>
</div>
