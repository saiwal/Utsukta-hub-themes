<div class="generic-content-wrapper-styled" id='adminpage'>
  <div class="section-title-wrapper app-content-header">
    <h3 class="border-0">{{$title}} - {{$page}}</h3>
  </div>
  <div class="card card-body" <form action="{{$baseurl}}/admin/logs" method="post">
    <input type='hidden' name='form_security_token' value='{{$form_security_token}}' />

    {{include file="field_checkbox.tpl" field=$debugging}}
    {{include file="field_input.tpl" field=$logfile}}
    {{include file="field_select.tpl" field=$loglevel}}

    <div class="submit">
      <input type="submit" name="page_logs" class="btn btn-primary" value="{{$submit}}" />
    </div>

    </form>

    <h4>{{$logname}}</h4>
    <div style="width:100%; height:400px; overflow: auto; ">
      <pre>{{$data}}</pre>
    </div>
  </div>
</div>
