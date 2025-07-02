<div class="generic-content-wrapper">
<div class="section-title-wrapper app-content-header">
	<h3>{{$title}}</h3>
</div>

<div class="section-content-tools-wrapper">

	<div id="profile-edit-links">
		<ul>
			<li>
				<a id="profile-edit-view-link" href="{{$baseurl}}/oauth2/add">{{$add}}</a>
			</li>
		</ul>
	</div>

	{{foreach $apps as $app}}
<form action="oauth2" method="post" autocomplete="off">
<input type='hidden' name='form_security_token' value='{{$form_security_token}}'>
<input type='hidden' name='name' value='{{$app.client_id}}'>
	<div class='oauthapp'>
		{{if $app.client_id}}<h4>{{$app.client_id}}</h4>{{else}}<h4>{{$noname}}</h4>{{/if}}
		{{if $app.my}}
			{{if $app.oauth_token}}
			<div class="settings-submit-wrapper" ><button class="settings-submit"  type="submit" name="remove" value="{{$app.oauth_token}}">{{$remove}}</button></div>
			{{/if}}
		{{/if}}
		{{if $app.my}}
		<a href="{{$baseurl}}/oauth2/edit/{{$app.client_id}}" title="{{$edit}}"><i class="bi bi-pencil btn btn-secondary"></i></a>
		<a href="{{$baseurl}}/oauth2/delete/{{$app.client_id}}?t={{$form_security_token}}" title="{{$delete}}"><i class="bi bi-trash btn btn-secondary"></i></a>
		{{/if}}		
	</div>
</form>
	{{/foreach}}

</div>
</div>
