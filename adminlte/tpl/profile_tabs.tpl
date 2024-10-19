{{foreach $tabs as $tab}}
<li class="nav-item">
<a class="nav-link{{if $tab.sel}} {{$tab.sel}}{{/if}}" href="{{$tab.url}}"{{if $tab.title}} title="{{$tab.title}}"{{/if}}><i class="bi bi-{{$tab.icon}} generic-icons-nav"></i> <p>{{$tab.label}}</p> </a>
</li>
{{/foreach}}
