{{foreach $tabs as $tab}}
<li class="{{if $tab.sel}} current{{/if}}">
  <a href="{{$tab.url}}">{{$tab.label}}</a>
</li>
{{/foreach}}
