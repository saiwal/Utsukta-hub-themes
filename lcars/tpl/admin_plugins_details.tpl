<div class="generic-content-wrapper-styled" id='adminpage'>
	<div class="section-title-wrapper app-content-header">
		<div class="lcars-text-bar"><span>{{$title}} -{{$page}}</span></div>
	</div>
		<p>{{if ! $info.disabled}}<i
				class='toggleplugin bi {{if $status==on}}bi-check-square{{else}}bi-square{{/if}} admin-icons'></i>{{else}}<i
				class='bi fa-stop admin-icons'></i>{{/if}} {{$info.name}} - {{$info.version}}{{if ! $info.disabled}} : <a
				href="{{$baseurl}}/admin/{{$function}}/{{$plugin}}/?a=t&amp;t={{$form_security_token}}">{{$action}}</a>{{/if}}
		</p>

		{{if $info.disabled}}
		<p>{{$disabled}}</p>
		{{/if}}

		<p>{{$info.description}}</p>

		{{foreach $info.author as $a}}
		<p class="author">{{$str_author}}
			{{$a.name}}{{if $a.link}} {{$a.link}}{{/if}}
		</p>
		{{/foreach}}

		{{if $info.minversion}}
		<p class="versionlimit">{{$str_minversion}}{{$info.minversion}}</p>
		{{/if}}
		{{if $info.maxversion}}
		<p class="versionlimit">{{$str_maxversion}}{{$info.maxversion}}</p>
		{{/if}}
		{{if $info.minphpversion}}
		<p class="versionlimit">{{$str_minphpversion}}{{$info.minphpversion}}</p>
		{{/if}}
		{{if $info.serverroles}}
		<p class="versionlimit">{{$str_serverroles}}{{$info.serverroles}}</p>
		{{/if}}
		{{if $info.requires}}
		<p class="versionlimit">{{$str_requires}}{{$info.requires}}</p>
		{{/if}}


		{{foreach $info.maintainer as $a}}
		<p class="maintainer">{{$str_maintainer}}
			{{$a.name}}{{if $a.link}} {{$a.link}}{{/if}}
		</p>
		{{/foreach}}

		{{if $screenshot}}
		<img class="img-fluid" src="{{$screenshot.0}}" alt="{{$screenshot.1}}" />
		{{/if}}

		{{if $admin_form}}
		<h4>{{$settings}}</h4>
		<form method="post" action="{{$baseurl}}/admin/{{$function}}/{{$plugin}}/">
			{{$admin_form}}
		</form>
		{{/if}}

		{{if $readme}}
		<h4>Readme</h4>
		<div id="plugin_readme">
			{{$readme}}
		</div>
		{{/if}}
</div>
