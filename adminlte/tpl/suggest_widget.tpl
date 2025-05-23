<div class="card mb-3">
  <div class="card-header">{{$title}}</div>
  <div class="card-body">
    {{if $entries}}
    <ul class="list-group list-group-flush">
      {{foreach $entries as $child}}
        {{include file="suggest_friends.tpl" entry=$child}}
      {{/foreach}}
    </ul>
    {{/if}}
  </div>
  <div class="card-footer">
    <a href="directory?f=&suggest=1">{{$more}}</a>
  </div>
</div>
