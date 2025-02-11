<div class="generic-content-wrapper">
	<div class="section-title-wrapper app-content-header">
		<h3 id="title">{{$title}} - {{$page}}</h3>
		<div class="clear"></div>
	</div>
      <div class="clear"></div>
    <div id="chat-rotator" class="spinner-wrapper">
        <div class="spinner s"></div>
    </div>
    <div class="clear"></div>
	<div class="section-content-wrapper-np">
      {{foreach $plugins as $p}}
      <div class="section-content-tools-wrapper" id="pluginslist">
		<div class="contact-info plugin {{$p.1}}">
            {{if ! $p.2.disabled}}
            <a class='toggleplugin' href='{{$baseurl}}/admin/{{$function}}/{{$p.0}}?a=t&amp;t={{$form_security_token}}' title="{{if $p.1==on}}Disable{{else}}Enable{{/if}}" ><i class='bi {{if $p.1==on}}bi-check-square{{else}}bi-square{{/if}} admin-icons'></i></a>
            {{else}}
            <i class='bi fa-stop admin-icons'></i>
            {{/if}}
            <a href='{{$baseurl}}/admin/{{$function}}/{{$p.0}}'><span class='name'>{{$p.2.name}}</span></a> - <span class="version">{{$p.2.version}}</span>{{if $p.2.disabled}} {{$disabled}}{{/if}}
            {{if $p.2.experimental}} {{$experimental}} {{/if}}{{if $p.2.unsupported}} {{$unsupported}} {{/if}}

            <div class='desc'>{{$p.2.description}}</div>
		</div>
	</div>
    {{/foreach}}

	</div>
</div>
