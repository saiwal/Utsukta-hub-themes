
	<div class="section-title-wrapper app-content-header">
		<div class="lcars-text-bar"><span>{{$title}}</span></div>
	</div>

<h3>{{$account.account_email}}</h3>


<form action="admin/account_edit/{{$account.account_id}}" method="post" >
<input type="hidden" name="aid" value="{{$account.account_id}}" />
<input type="hidden" name="security" value="{{$security}}">

{{include file="field_password.tpl" field=$pass1}}
{{include file="field_password.tpl" field=$pass2}}
{{include file="field_select.tpl" field=$account_language}}
{{include file="field_input.tpl" field=$service_class}}

<div class="buttons">
	<button type="submit" name="submit" class="" value="{{$submit}}">{{$submit}}</button>
</div>

</form>
