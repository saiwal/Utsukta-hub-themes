<div class="generic-content-wrapper">
  <div class="section-title-wrapper app-content-header"><a title="{{$new}}" class="btn btn-primary btn-sm float-end"
      href="admin/profs/new"><i class="bi bi-plus-lg"></i>&nbsp;{{$new}}</a>
    <h3>{{$title}}</h3>
  </div>

  <div class="section-content-tools-wrapper">
    <div class="card card-body mb-3">
      <div class="callout callout-info mb-2">{{$all_desc}}<br><br>
        {{$all}}
      </div>
      <form action="admin/profs" method="post">
        {{include file="field_textarea.tpl" field=$basic}}
        {{include file="field_textarea.tpl" field=$advanced}}
        <input type="submit" name="submit" class="btn btn-primary float-end" value="{{$submit}}" />
      </form>
    </div>
    {{if $cust_fields}}
    <div class="card mb-3">
      <div class="card-header border-0">
        <h3 class="card-title">{{$cust_field_desc}}</h3>
      </div>
      <div class="card-body table-responsive p-0">
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
