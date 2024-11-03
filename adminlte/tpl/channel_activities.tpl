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
        <thead>
            <tr>
                <th>Title</th>
                <th>Description</th>
                <th>Date</th>
            </tr>
        </thead>
        <tbody>
	          {{foreach $items as $i}}
            <tr class="align-middle">
                <td><a href="{{$i.url}}">{{$i.title}}</a></td>
                <td>{{$i.summary}}</td>
                <td>{{$i.footer}}</td>
            </tr>
            {{/foreach}}
        </tbody>
    </table>     

    </div> <!-- /.card-body -->
  </div> <!-- /.card -->
</div>

<div class="mb-1 text-uppercase">
	<a href="{{$url}}"><i class="bi bi-{{$icon}} generic-icons-nav"></i>{{$label}}</a>
</div>
<div class="row row-cols-1 row-cols-sm-3 g-4 mb-4">
	{{foreach $items as $i}}  
	<div class="col">
		<div class="card">
			<a href="{{$i.url}}" class="text-reset">
				<div class="card-body clearfix">
					{{if $i.title}}
						<strong>{{$i.title}}</strong>
						<hr>
					{{/if}}
					{{$i.summary}}
				</div>
				<div class="card-footer text-muted autotime" title="{{$i.footer}}">{{$i.footer}}</div>
			</a>
		</div>
	</div>
	{{/foreach}}
</div>


