<div class="generic-content-wrapper">
	<div class="clearfix section-title-wrapper app-content-header">
		<div class="lcars-text-bar"><span>{{$title}}</span></div>
	</div>
  <div class="mb-3">
      <div id="group_tools" class="clearfix section-content-tools-wrapper">
        <form action="group/new" id="group-edit-form" method="post" >
          <input type='hidden' name='form_security_token' value='{{$form_security_token}}'>
          {{include file="field_input.tpl" field=$gname}}
          {{include file="field_checkbox.tpl" field=$public}}
          {{include file="field_checkbox.tpl" field=$is_default_acl}}
          {{include file="field_checkbox.tpl" field=$is_default_group}}
          {{$pgrp_extras}}
					<div class="buttons the-end">
						<button type="submit" name="submit" class="">{{$submit}}</button>
					</div>
        </form>
      </div>
  </div>
</div>
