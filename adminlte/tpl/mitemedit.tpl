{{if $header}}
<div class="generic-content-wrapper">
	<div class="section-title-wrapper app-content-header">
		<h3>{{$header}}</h3>
	</div>
{{/if}}
	<div id="menu-element-creator" class="section-content-tools-wrapper card card-body mb-3" style="display: {{$display}};">
		<form id="mitemedit" action="mitem/{{$nick}}/{{$menu_id}}{{if $mitem_id}}/{{$mitem_id}}{{/if}}{{if $sys}}?f=&sys=1{{/if}}" method="post" class="acl-form" data-form_id="mitemedit" data-allow_cid='{{$allow_cid}}' data-allow_gid='{{$allow_gid}}' data-deny_cid='{{$deny_cid}}' data-deny_gid='{{$deny_gid}}'>
			<input type="hidden" name="menu_id" value="{{$menu_id}}" />
			{{if $mitem_id}}
			<input type="hidden" name="mitem_id" value="{{$mitem_id}}" />
			{{/if}}
			{{include file="field_input.tpl" field=$mitem_desc}}
			{{include file="field_input.tpl" field=$mitem_link}}
			{{if $menu_names}}
			<datalist id="menu-names">
				{{foreach $menu_names as $menu_name}}
				<option value="{{$menu_name}}">
				{{/foreach}}
			</datalist>
			{{/if}}
			{{include file="field_input.tpl" field=$mitem_order}}
			{{include file="field_checkbox.tpl" field=$usezid}}
			{{include file="field_checkbox.tpl" field=$newwin}}
			<div class="float-end mb-3">
				<div class="btn-group">
					<button id="dbtn-acl" class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#aclModal" onclick="return false;">
						<i id="jot-perms-icon" class="bi bi-{{$lockstate}}"></i>
					</button>
					{{if $submit_more}}
					<button class="btn btn-primary btn-sm" type="submit" name="submit-more" value="{{$submit_more}}">{{$submit_more}}&nbsp;<i class="bi bi-caret-right"></i></button>
					{{/if}}
					<button class="btn btn-primary btn-sm" type="submit" name="submit" value="{{$submit}}">{{$submit}}</button>
				</div>
			</div>
			<div class="clear"></div>
		</form>
		{{$aclselect}}
	</div>
{{if $header}}
</div>
{{/if}}
