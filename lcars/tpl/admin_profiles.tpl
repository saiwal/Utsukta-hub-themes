<div class="generic-content-wrapper">
  <div class="section-title-wrapper app-content-header">
		<div class="lcars-text-bar"><span>{{$title}}</span></div>
		<div class="buttons the-end">
			<a title="{{$new}}" class="flush" href="admin/profs/new"><i class="bi bi-plus-lg"></i>&nbsp;{{$new}}</a>
			</a>
		</div>
  </div>

  <div class="section-content-tools-wrapper">
    <div class="mb-3">
      <div class="callout callout-info mb-2">{{$all_desc}}<br><br>
        {{$all}}
      </div>
      <form action="admin/profs" method="post">
        {{include file="field_textarea.tpl" field=$basic}}
        {{include file="field_textarea.tpl" field=$advanced}}
				<div class="buttons the-end"><button type="submit" name="submit" class="btn btn-primary float-end" value="{{$submit}}"/>{{$submit}}</button></div>
      </form>
    </div>
    {{if $cust_fields}}
    <div class="mb-3">
      <div class="h5 mt-0 border-0">
        {{$cust_field_desc}}
      </div>
      <div class="table-responsive p-0">
        <table class="table table-striped align-middle" role="table">
          <tbody>     {{foreach $cust_fields as $field}}
            <tr>
              <td>
                {{$field.field_name}}
              </td>
              <td>{{$field.field_desc}}</td>
              <td><a class="btn btn-danger btn-sm" href="admin/profs/drop/{{$field.id}}" title="{{$drop}}"><i class="bi bi-trash"></i>&nbsp;{{$drop}}</a>
              </td>
              <td><a class="btn btn-sm" title="{{$edit}}"
              href="admin/profs/{{$field.id}}"><i class="bi bi-pencil"></i></a></td>
              </td>
            </tr>
          {{/foreach}}
          </tbody>
        </table>
      </div>
    </div>
    {{/if}}
  </div>

</div>
