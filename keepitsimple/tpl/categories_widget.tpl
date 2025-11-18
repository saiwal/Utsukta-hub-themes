<div id="categories-sidebar" class="widget widget--categories">
  <h3 class="h6">
    {{$title}}
  </h3>
    <ul>
      <li><a href="{{$base}}" class="{{if $sel_all}} active{{/if}}">{{$all}}</a></li>
      {{foreach $terms as $term}}{{if $term.name}}
      <li><a href="{{$base}}/?cat={{$term.name|escape:'url'}}"
          class="{{if $term.selected}} active{{/if}}">{{$term.name}}</a></li>
      {{/if}}
      {{/foreach}}
    </ul>
</div>
