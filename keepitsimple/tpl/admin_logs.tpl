<div class="generic-content-wrapper-styled" id='adminpage'>
	<div class="section-title-wrapper app-content-header">		<header class="entry__header">
			<h2 class="entry__title h1">{{$title}} - {{$page}}
			</h2>
		</header>
	</div>
	<form action="{{$baseurl}}/admin/logs" method="post">
		<input type='hidden' name='form_security_token' value='{{$form_security_token}}' />

		{{include file="field_checkbox.tpl" field=$debugging}}
		{{include file="field_input.tpl" field=$logfile}}
		{{include file="field_select.tpl" field=$loglevel}}

		<div class="submit">
			<input type="submit" name="page_logs" class="btn btn-primary" value="{{$submit}}" />
		</div>

	</form>

	<h5>{{$logname}}</h5>
	<div>
		<pre style="width:100%; height:400px; overflow: auto; ">{{$data}}</pre>
	</div>
</div>
