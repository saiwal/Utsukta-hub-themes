<div id="chatroom-new" class="section-content-tools-wrapper">
	<form id="chatroom-new-form" action="chat" method="post" class="acl-form" data-form_id="chatroom-new-form" data-allow_cid='{{$allow_cid}}' data-allow_gid='{{$allow_gid}}' data-deny_cid='{{$deny_cid}}' data-deny_gid='{{$deny_gid}}'>
		{{include file="field_input.tpl" field=$name}}
		{{include file="field_input.tpl" field=$chat_expire}}
		<div class="btn-group float-end">
			<button id="dbtn-acl" class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#aclModal" title="{{$permissions}}" onclick="return false;" ><i id="jot-perms-icon" class="bi bi-{{$lockstate}}"></i></button>
			<button id="dbtn-submit" class="acl-submit btn btn-primary" type="submit" name="submit" value="{{$submit}}" data-formid="chatroom-new-form">{{$submit}}</button>
		</div>
		<div class="clear"></div>
	</form>
</div>
{{$acl}}
