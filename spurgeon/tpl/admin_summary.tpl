<div class="generic-content-wrapper-styled" id='adminpage'>
  <div class="section-title-wrapper app-content-header">
    <h3 class="border-0">{{$title}}</h3>
  </div>
  {{if $adminalertmsg}}
  <p class="callout callout-warning" role="alert">{{$adminalertmsg}}</p>
  {{/if}}
  {{if $upgrade}}
  <p class="callout callout-warning" role="alert">{{$upgrade}}</p>
  {{/if}}
  <div class="card mb-3">
    <div class="card-header">
      {{$page}}
    </div>
    <div class="card-body table-responsive">
      <table role="table" class="table align-middle">
        <tbody>
          <tr>
            <td>{{$queues.label}}</td>
            <td>{{$queues.queue}}</td>
          </tr>
          <tr>
            <td>{{$accounts.0}}</td>
            <td>{{foreach from=$accounts.1 item=acc name=account}}<span title="{{$acc.label}}">{{$acc.val}}
                {{$acc.label}}</span>{{if !$smarty.foreach.account.last}} / {{/if}}{{/foreach}}</td>
          </tr>
          <tr>
            <td>{{$pending.0}}</td>
            <td>{{$pending.1}}</td>
          </tr>
          <tr>
            <td>{{$channels.0}}</td>
            <td>{{foreach from=$channels.1 item=ch name=chan}}<span title="{{$ch.label}}">{{$ch.val}}
                {{$ch.label}}</span>{{if !$smarty.foreach.chan.last}} / {{/if}}{{/foreach}}</td>
          </tr>
          <tr>
            <td>{{$plugins.0}}</td>
            <td>
              {{foreach $plugins.1 as $p}} {{$p}} {{/foreach}}
            </td>
          </tr>
          <tr>
            <td>{{$version.0}}</td>
            <td>{{$version.1}} - {{$build}}</td>
          </tr>
          <tr>
            <td>{{$vmaster.0}}</td>
            <td>{{$vmaster.1}}</td>
          </tr>
          <tr>
            <td>{{$vdev.0}}</td>
            <td>{{$vdev.1}}</td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</div>
