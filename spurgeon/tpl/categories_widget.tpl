<div id="categories-sidebar">
  <div class="h5">
    {{$title}}
  </div>
  <span class="entry__tag-list">
    <a class="{{if $sel_all}} {{/if}}" href="{{$base}}">{{$all}}</a>
    {{foreach $terms as $term}}{{if $term.name}}
    <a class="{{if $term.selected}}text-white bg-black{{/if}}" href="{{$base}}/?cat={{$term.name|escape:'url'}}">{{$term.name}}</a>
    {{/if}}
    {{/foreach}}
  </span>
</div>
