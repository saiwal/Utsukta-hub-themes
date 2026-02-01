{{foreach $tabs as $tab}}
<button class="{{if $tab.sel}}blink-fast{{/if}}" onclick="playSoundAndRedirect('audio2', '{{$tab.url}}')">{{$tab.label}}</button>
{{/foreach}}
