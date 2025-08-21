<div class="generic-content-wrapper-styled">
  <div class="section-title-wrapper app-content-header">
    <h3 class="border-0">{{$banner}}
  </div>
  {{if $hasentries}}
  <div class="card mb-3">
    <div class="card-body">

      <table cellpadding="10" id="admin-queue-table">
        <tr>
          <td>{{$numentries}}&nbsp;&nbsp;</td>
          <td>{{$desturl}}</td>
          <td>{{$priority}}</td>
          <td>&nbsp;</td>
          <td>&nbsp;</td>
        </tr>

        {{foreach $entries as $e}}

        <tr>
          <td>{{$e.total}}</td>
          <td>{{$e.outq_posturl}}</td>
          <td>{{$e.priority}}</td>{{if $expert}}<td><a href="admin/queue?f=&drophub={{$e.eurl}}" title="{{$nukehub}}"
              class="btn btn-outline-secondary"><i class="bi bi-x-lg"></i><a></td>
          <td><a href="admin/queue?f=&emptyhub={{$e.eurl}}" title="{{$empty}}" class="btn btn-outline-secondary"><i
                class="bi bi-trash"></i></a></td>
          <td><a href="admin/queue?f=&deliverhub={{$e.eurl}}" title="{{$deliverhub}}"
              class="btn btn-outline-secondary"><i class="bi fa-refresh"></i><a></td>{{/if}}
        </tr>
        {{/foreach}}

      </table>
    </div>
  </div>
  {{/if}}
</div>
