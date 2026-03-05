	<div id="id_{{$field.0}}_wrapper" class="mb-3">
		<label for="id_{{$field.0}}">{{$field.1}}{{if isset($field.5)}}<sup class="required zuiqmid"> {{$field.5}}</sup>{{/if}}</label>
		<select class="form-control" name="{{$field.0}}" id="id_{{$field.0}}">
			{{foreach $field.4 as $opt=>$val}}<option value="{{$opt}}" {{if $opt==$field.2}}selected="selected"{{/if}}>{{$val}}</option>{{/foreach}}
		</select>
		<small class="form-text text-muted">{{$field.3}}</small	>
	</div>
{{*
	COMMENTS for this template:
	@author hilmar runge, 2020.01
	$field array index:
		.0	field name: name=... for input, id=id_... for input, id=label_... for label, id=help_... for small text
		.1	label text
		.2	selected field
		.3	form text
		.4	option value(s)
		.5	label text addition, used for qmc
	css classes used:
		.required, .code
		.mb-3, .form-control, .form-text, .text-muted
*}}

