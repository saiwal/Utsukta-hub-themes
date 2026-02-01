<label id="{{$field.0}}_container" class="h-add-bottom">
  <span for="id_{{$field.0}}">{{$field.1}}{{if $field.6}}<sup class="required zuiqmid">{{$field.6}}</sup>{{/if}}</span>
    <input type="checkbox" class="float-end" name="{{$field.0}}" id="id_{{$field.0}}" value="1" {{if $field.2}}checked="checked" {{/if}} {{if $field.5}}{{$field.5}}{{/if}} />
</label>


{{*
COMMENTS for this template:
@author hilmar runge, 2020.01
$field array index:
.0 field name: name=... for input, id=id_... for input, id=label_... for label, id=help_... for small text
.1 label text
.2 checked
.3 form text
.4 on/off value:
.4.0 off
.4.1 on
.5 additional operands for html input statement
.6 label text addition, used for qmc
css classes used:
.clearfix, .form_group, .checkbox
.floatright
.switchlabel, .onoffswitch-switch
.required, .code
.form-control, .form-text, .text-muted
*}}
