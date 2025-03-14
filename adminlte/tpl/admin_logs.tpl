<div class="generic-content-wrapper-styled" id='adminpage'>
	<div class="section-title-wrapper app-content-header">
	<h3>{{$title}} - {{$page}}</h3>
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
	
	<h3>{{$logname}}</h3>
	<div style="width:100%; height:400px; overflow: auto; "><pre>{{$data}}</pre></div>
<!--	<iframe src='{{$baseurl}}/{{$logname}}' style="width:100%; height:400px"></iframe> -->
	<!-- <div class="submit"><input type="submit" name="page_logs_clear_log" value="{{$clear}}" /></div> -->
</div>
