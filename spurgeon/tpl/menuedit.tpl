{{if $header}}
<div class="generic-content-wrapper">
<div class="section-title-wrapper app-content-header">
	{{if $menu_edit_link}}
	<div class="float-end">
		<a href="{{$menu_edit_link}}" title="{{$hintedit}}" class="btn btn-sm btn-success"><i class="bi bi-pencil-square-o"></i>&nbsp;{{$editcontents}}</a>
	</div>
	{{/if}}
	<h5>{{$header}}</h5>
	<div class="clear"></div>
</div>
{{/if}}
<div id="menu-creator" class="section-content-tools-wrapper" style="display: {{$display}};">
	<form id="menuedit" action="menu/{{$nick}}{{if $menu_id}}/{{$menu_id}}{{/if}}{{if $sys}}?f=&sys=1{{/if}}" method="post" >
		{{if $menu_id}}
		<input type="hidden" name="menu_id" value="{{$menu_id}}" />
		{{/if}}
		{{if $menu_system}}
		<input type="hidden" name="menu_system" value="{{$menu_system}}" />
		{{/if}}
		{{include file="field_input.tpl" field=$menu_name}}
		{{include file="field_input.tpl" field=$menu_desc}}

		{{include file="field_checkbox.tpl" field=$menu_bookmark}}
		<div class="menuedit-submit-wrapper mb-3 float-end" >
			<button type="submit" name="submit" class="btn btn-primary">{{$submit}}&nbsp;<i class="bi bi-caret-right"></i></button>
		</div>
		<div class="clear"></div>
	</form>
</div>
{{if $header}}
</div>
{{/if}}
