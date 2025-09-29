<div class="col">
  <div class="card mb-4">
    <div class="card-header">
      <i class="bi bi-{{$icon}} generic-icons-nav"></i> <a class="text-decoration-none" href="{{$url}}">{{$label}}</a>
        <div class="card-tools">
          <button type="button" class="btn btn-sm btn-tool" data-lte-toggle="card-collapse"> <i data-lte-icon="expand" class="bi bi-plus-lg"></i> <i data-lte-icon="collapse" class="bi bi-dash-lg"></i> </button> 
        </div> <!-- /.card-tools -->
    </div> <!-- /.card-header -->
    <div class="card-body" style="display: block; box-sizing: border-box;">
    <table class="table">
        <tbody>
	          {{foreach $items as $i}}
            <tr class="align-middle">
					      {{if $i.title}}
                <td><a href="{{$i.url}}" class="text-decoration-none">{{$i.title}}</a></td>
                {{/if}}
                <td><a href="{{$i.url}}" class="text-decoration-none">{{$i.summary}}</a></td>
				        <td class="text-muted autotime" title="{{$i.footer}}">{{$i.footer}}</td>
            </tr>
            {{/foreach}}
        </tbody>
    </table>     

    </div> <!-- /.card-body -->
  </div> <!-- /.card -->
</div>

