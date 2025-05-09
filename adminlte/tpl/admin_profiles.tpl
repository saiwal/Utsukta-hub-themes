<div class="generic-content-wrapper">
<div class="section-title-wrapper app-content-header"><a title="{{$new}}" class="btn btn-primary btn-sm float-end" href="admin/profs/new"><i class="bi bi-plus-lg"></i>&nbsp;{{$new}}</a><h3>{{$title}}</h3>
<div class="clear"></div>
</div>

<div class="section-content-tools-wrapper">

<div class="section-content-info-wrapper">{{$all_desc}}
<br /><br />
{{$all}}
</div>

<form action="admin/profs" method="post">

{{include file="field_textarea.tpl" field=$basic}}
{{include file="field_textarea.tpl" field=$advanced}}

<input type="submit" name="submit" class="btn btn-primary" value="{{$submit}}" />

</form>



{{if $cust_fields}}
<br /><br />
<div><strong>{{$cust_field_desc}}</strong></div>
<br />

<table width="100%">
{{foreach $cust_fields as $field}}
<tr><td>{{$field.field_name}}</td><td>{{$field.field_desc}}</td><td><a class="btn btn-danger btn-sm" href="admin/profs/drop/{{$field.id}}" title="{{$drop}}"><i class="bi bi-trash"></i>&nbsp;{{$drop}}</a> <a class="btn btn-sm" title="{{$edit}}" href="admin/profs/{{$field.id}}" ><i class="bi bi-pencil"></i></a></td></tr>
{{/foreach}}
</table>
{{/if}}

</div>

</div>
