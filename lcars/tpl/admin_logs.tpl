<div class="generic-content-wrapper-styled" id='adminpage'>
  <div class="section-title-wrapper app-content-header">
		<div class="lcars-text-bar"><span>{{$title}} -{{$page}}</span></div>
  </div>
  <form action="{{$baseurl}}/admin/logs" method="post">
    <input type='hidden' name='form_security_token' value='{{$form_security_token}}' />

    {{include file="field_checkbox.tpl" field=$debugging}}
    {{include file="field_input.tpl" field=$logfile}}
    {{include file="field_select.tpl" field=$loglevel}}

    <div class="submit buttons">
			<button type="submit" name="page_logs" class="btn btn-primary">{{$submit}}</button>
    </div>
  </form>

  <h5>{{$logname}}</h5>
		<div class="border border-1 border-info flex">
      <pre style="width:100%; height:600px; overflow:auto;
              white-space:pre-wrap;
              word-break:break-word;
              overflow-wrap:anywhere;">{{$data}}</pre>
    </div>

</div>
