{{foreach $tabs as $tab}}
<li class="nav-item">
<a class="nav-link{{if $tab.sel}} {{$tab.sel}}{{/if}}" href="{{$tab.url}}"{{if $tab.title}} title="{{$tab.title}}"{{/if}}><i class="nav-icon bi bi-{{$tab.icon}}"></i> <p>{{$tab.label}}</p> </a>
</li>
{{/foreach}}
