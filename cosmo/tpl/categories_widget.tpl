<div id="categories-sidebar" class="card mb-3">
  <div class="card-header">
    <h3 class="card-title">{{$title}}</h3>
  </div>
  <div class="card-body">
    <div id="categories-sidebar-desc">{{$desc}}</div>

    <ul class="nav nav-pills flex-column">
      <li class="nav-item"><a href="{{$base}}" class="nav-link{{if $sel_all}} active{{/if}}">{{$all}}</a></li>
      {{foreach $terms as $term}}
      <li class="nav-item"><a href="{{$base}}/?cat={{$term.name|escape:'url'}}"
          class="nav-link{{if $term.selected}} active{{/if}}">{{$term.name}}</a></li>
      {{/foreach}}
    </ul>
  </div>
</div>
