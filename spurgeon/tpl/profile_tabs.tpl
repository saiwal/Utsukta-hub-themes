{{foreach $tabs as $tab}}
<li class="{{if $tab.active}} current-menu-item{{/if}}">
  <a href="{{$tab.url}}">{{$tab.label}}</a>
</li>
{{/foreach}}
