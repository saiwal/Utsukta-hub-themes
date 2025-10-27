<div id="categories-sidebar" class="mb-3">
  <div class="h5">
    {{$title}}
  </div>
  <span class="entry__tag-list">
    <a class="btn {{if $sel_all}} btn--primary{{/if}}" href="{{$base}}">{{$all}}</a>
    {{foreach $terms as $term}}{{if $term.name}}
    <a class="btn {{if $term.selected}}btn--primary{{/if}}" href="{{$base}}/?cat={{$term.name|escape:'url'}}">{{$term.name}}</a>
    {{/if}}
    {{/foreach}}
  </span>
</div>
