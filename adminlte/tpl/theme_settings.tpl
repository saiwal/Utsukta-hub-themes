	<h3>{{$light}}</h3>
	{{include file="field_colorinput.tpl" field=$bgcolor}}
	{{include file="field_colorinput.tpl" field=$background_image}}

	<h3>{{$dark}}</h3>
	{{include file="field_colorinput.tpl" field=$bgcolor_dark}}
	{{include file="field_colorinput.tpl" field=$background_image_dark}}
  <hr>
  {{include file="field_select.tpl" field=$bg_mode}}

{{include file="field_checkbox.tpl" field=$advanced_theming}}

{{if $expert}}
	<hr>
	<h3>{{$common}}</h3>
	{{include file="field_colorinput.tpl" field=$primary_color}}
	{{include file="field_colorinput.tpl" field=$success_color}}
	{{include file="field_colorinput.tpl" field=$info_color}}
	{{include file="field_colorinput.tpl" field=$warning_color}}
	{{include file="field_colorinput.tpl" field=$danger_color}}
	{{include file="field_input.tpl" field=$radius}}
	{{include file="field_input.tpl" field=$top_photo}}
	{{include file="field_input.tpl" field=$reply_photo}}

<script>
	$(function(){
		$('#id_redbasic_link_color, #id_redbasic_link_color_dark, #id_redbasic_link_hover_color, #id_redbasic_link_hover_color_dark, #id_redbasic_background_color, #id_redbasic_background_color_dark, #id_redbasic_nav_bg, #id_redbasic_nav_bg_dark').colorpicker({format: 'rgba'});
	});
	$(function(){
		$('#id_redbasic_primary_color, #id_redbasic_success_color, #id_redbasic_info_color, #id_redbasic_warning_color, #id_redbasic_danger_color').colorpicker({format: 'hex'});
	});
</script>
{{/if}}


<div class="settings-submit-wrapper" >
	<button type="submit" name="adminlte-settings-submit" class="btn btn-primary">{{$submit}}</button>
</div>
