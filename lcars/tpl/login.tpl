<form action="{{$dest_url}}" id="{{$form_id}}" method="post" >
	<input type="hidden" name="auth-params" value="login" />
	<div>
		{{include file="field_input.tpl" field=$lname}}
		{{include file="field_password.tpl" field=$lpassword}}
		{{include file="field_checkbox.tpl" field=$remember_me}}
		<div class="buttons">
		<button type="submit" name="submit" class="">{{$login}}</button>
		{{if $register}}<a href="{{$register.link}}" title="{{$register.title}}" class="register-link float-end">{{$register.desc}}</a>{{/if}}
		{{if $lostlink}}<button href="lostpass" title="{{$lostpass}}" class="lost-pass-link float-end">{{$lostlink}}</button>{{/if}}
		<hr>
		<button href="rmagic" class="btn btn-outline-success">{{$remote_login}}</button>
		</div>
	</div>
	{{foreach $hiddens as $k=>$v}}
	<input type="hidden" name="{{$k}}" value="{{$v}}" />
	{{/foreach}}
</form>
{{if $login_page}}
<script type="text/javascript"> $(document).ready(function() { $("#id_{{$lname.0}}").focus();} );</script>
{{/if}}
