<div class="generic-content-wrapper">
	<div class="section-title-wrapper app-content-header">
		<h3>{{$title}}</h3>
	</div>
  <div class="card mb-3">
    <div class="card-body">
    	<div id="group_tools" class="clearfix section-content-tools-wrapper">
		    <form action="group/{{$gid}}" id="group-edit-form" method="post" >
          <input type='hidden' name='form_security_token' value='{{$form_security_token_edit}}'>
          {{include file="field_input.tpl" field=$gname}}
          {{include file="field_checkbox.tpl" field=$public}}
          {{include file="field_checkbox.tpl" field=$is_default_acl}}
          {{include file="field_checkbox.tpl" field=$is_default_group}}
          {{$pgrp_extras}}
          <a href="group/drop/{{$gid}}?t={{$form_security_token_drop}}" onclick="return confirmDelete();" class="btn btn-sm btn-outline-danger">
            {{$delete}}
          </a>
          <button type="submit" name="submit" class="btn btn-sm btn-primary float-end">{{$submit}}</button>
        </form>
    	</div>
      <div class="callout callout-info mt-2 mb-2">
        {{$desc}}
      </div>
      <div class="">
        <div id="group-update-wrapper" class="clearfix">
          {{include file="groupeditor.tpl"}}
        </div>
      </div>
    </div>
	</div>
</div>
