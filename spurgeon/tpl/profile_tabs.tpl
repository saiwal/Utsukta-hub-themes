{{foreach $tabs as $tab}}
<li class="{{if $tab.sel}} {{$tab.sel}}{{/if}}{{if $tab.active}} current-menu-item{{/if}}">
  <a href="{{$tab.url}}">{{$tab.label}}</a>
</li>
{{/foreach}}
