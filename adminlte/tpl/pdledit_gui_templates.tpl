<form id="pdledit_gui_templates_form">
	{{foreach $templates as $template}}
	<div class="form-check mb-2">
		<input class="form-check-input" type="radio" name="template" id="id_template_{{$template.name}}" value="{{$template.name}}" {{if $template.name == $active}} checked{{/if}}>
		<label class="form-check-label" for="id_template_{{$template.name}}">
			{{$template.name}}
		</label>
		<small class="text-muted">{{$template.desc}}</small>
	</div>
	{{/foreach}}
</form>
