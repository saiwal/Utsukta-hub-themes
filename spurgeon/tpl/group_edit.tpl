<div class="generic-content-wrapper">
	<div class="section-title-wrapper app-content-header">
		<div class="h3 mt-0">{{$title}}</div>
	</div>
  <div class="mb-3">
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
      <div class="h6">
        {{$desc}}
      </div>
      <div class="">
        <div id="group-update-wrapper" class="clearfix">
          {{include file="groupeditor.tpl"}}
        </div>
      </div>
	</div>
</div>
