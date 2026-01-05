{{foreach $tabs as $tab}}
<button onclick="playSoundAndRedirect('audio2', '{{$tab.url}}')">{{$tab.label}}</button>
{{/foreach}}
