{{if $schema=="adminlte"}}
	{{include file="field_colorinput.tpl" field=$primary_color}}
	{{include file="field_colorinput.tpl" field=$success_color}}
	{{include file="field_colorinput.tpl" field=$info_color}}
	{{include file="field_colorinput.tpl" field=$warning_color}}
	{{include file="field_colorinput.tpl" field=$danger_color}}
{{/if}}

<h6> Background Image (Light Mode)</h6>  
{{include file="field_colorinput.tpl" field=$background_image}}

<h6> Background Image (Dark Mode)</h6>  
{{include file="field_colorinput.tpl" field=$background_image_dark}}

<h6> Background Image Mode (stretch, tile) </h6>  
{{include file="field_select.tpl" field=$bg_mode}}

<div class="settings-submit-wrapper" >
	<button type="submit" name="adminlte-settings-submit" class="btn btn-primary">{{$submit}}</button>
</div>
