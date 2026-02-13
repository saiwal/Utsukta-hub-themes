<div class="mb-3">
  <div class="h6">{{$title}}</div>
    {{if $entries}}
    <ul class="list-group list-group-flush">
      {{foreach $entries as $child}}
        {{include file="suggest_friends.tpl" entry=$child}}
      {{/foreach}}
    </ul>
    {{/if}}
    <a href="directory?f=&suggest=1">{{$more}}</a>
</div>
