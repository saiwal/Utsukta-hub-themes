<div class="generic-content-wrapper">
  <div class="section-title-wrapper clearfix app-content-header">
    <h3>{{$title}}</h3>
  </div>
{{if $entries}}
<div class="row row-cols-1 row-cols-md-2 g-4">  
  {{foreach $entries as $child}}
    {{include file="suggest_friends_pg.tpl" entry=$child}}
  {{/foreach}}  
</div>
{{/if}}
</div>
