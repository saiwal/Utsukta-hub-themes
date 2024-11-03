<div class="col">
  <div class="card card-primary">
    <div class="card-header">
      <h3 class="card-title"><a href="{{$url}}">{{$label}}</a></h3>
        <div class="card-tools">
          <button type="button" class="btn btn-tool" data-lte-toggle="card-collapse"> <i data-lte-icon="expand" class="bi bi-plus-lg"></i> <i data-lte-icon="collapse" class="bi bi-dash-lg"></i> </button> 
        </div> <!-- /.card-tools -->
    </div> <!-- /.card-header -->
    <div class="card-body" style="display: block; box-sizing: border-box;">
    <table class="table">
        <tbody>
	          {{foreach $items as $i}}
            <tr class="align-middle">
					      {{if $i.title}}
                <td><a href="{{$i.url}}">{{$i.title}}</a></td>
                {{/if}}
                <td><a href="{{$i.url}}">{{$i.summary}}</a></td>
                <td>{{$i.footer}}</td>
            </tr>
            {{/foreach}}
        </tbody>
    </table>     

    </div> <!-- /.card-body -->
  </div> <!-- /.card -->
</div>


