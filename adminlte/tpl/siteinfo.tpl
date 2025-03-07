
<div class="app-content">

  <div class="card mb-3">
    <div class="card-header">{{$title}}</div>
    <div class="card-body">
      <h5 class="card-title">{{$sitename}}</h5>
      <h6 class="card-subtitle text-muted">{{if $site_about}}{{$site_about}}{{else}}--{{/if}}</h6>
    </div>
    <div class="card-body">
      <a href="help/TermsOfService" class="card-link">{{$terms}}</a>
    </div>
    <ul class="list-group list-group-flush">
      {{if $addons.1}}
      <li class="list-group-item">
        <div class="d-flex border-top py-2 px-1">
          <div class="col-2">
            {{$addons.0}}
          </div>
          <div class="col-10">
            {{foreach $addons.1 as $addon}}
              <span class="badge text-bg-primary">{{$addon}}</span>
            {{/foreach}}
          </div>
      </li>
      {{/if}}
      {{if $blocked_sites.1}}
      <li class="list-group-item">
        <div class="d-flex border-top py-2 px-1">
          <div class="col-2">
            {{$blocked_sites.0}}
          </div>
          <div class="col-10">
          {{foreach $blocked_sites.1 as $site}}
            <span class="badge text-bg-danger">{{$site}}</span>
          {{/foreach}}
          </div>
      </li>
      {{/if}}
    </ul>    
    <div class="card-body">
      <a href="help/TermsOfService" class="card-link">{{$terms}}</a>
    </div>
    <div class="card-footer text-muted">
      {{if $admin_about}}{{$admin_about}}{{else}}--{{/if}}
    </div>    
  </div>

  <div class="card mb-3">
    <div class="card-header">{{$prj_header}}</div>
    <div class="card-body">
      <p class="card-text">{{$prj_name}} ({{$z_server_role}})</p>
      {{if $prj_version}}
      <p class="card-text">{{$prj_version}}</p>
      {{/if}}
    </div>
    <ul class="list-group list-group-flush">    
      <li class="list-group-item">
        <div class="d-flex border-top py-2 px-1">
          <div class="col-2">{{$prj_linktxt}}
          </div>
          <div class="col-10">
          </div>
      </li> 
      <li class="list-group-item">
        <div class="d-flex border-top py-2 px-1">
          <div class="col-2">{{$prj_srctxt}}
          </div>
          <div class="col-10">
          </div>
      </li>
      <li class="list-group-item">
        <div class="d-flex border-top py-2 px-1">{{$prj_transport}} {{$transport_link}}
      </li>
      <li class="list-group-item">
        <div class="d-flex border-top py-2 px-1">{{$additional_text}} {{$additional_fed}}
      </li>
    </ul>
  </div>

</div>    


