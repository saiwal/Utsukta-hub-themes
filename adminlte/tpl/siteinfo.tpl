
<h3 class="app-content-header">{{$title}}</h3>

<div class="app-content">

  <div class="card mb-3">
    <div class="card-header">{{$title}}</div>
    <div class="card-body">
      <h5 class="card-title">{{$sitename}}</h5>
    </div>
    <div class="card-body">
      <p class="card-text">{{if $site_about}}{{$site_about}}{{else}}--{{/if}}</p>
    </div>
    <ul class="list-group list-group-flush">
      {{if $addons.1}}
      <li class="list-group-item">
        {{$addons.0}}
          {{foreach $addons.1 as $addon}}
        <span class="badge text-bg-primary">{{$addon}}</span>
          {{/foreach}}
      </li>
      {{/if}}
      {{if $blocked_sites.1}}
      <li class="list-group-item">
        {{$blocked_sites.0}}
          {{foreach $blocked_sites.1 as $site}}
        <span class="badge text-bg-danger">{{$site}}</span>
          {{/foreach}}
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

  <div class="card border-info mb-3" style="max-width: 20rem;">
    <div class="card-header">{{$prj_header}}</div>
    <div class="card-body">
      <p class="card-text">{{$prj_name}} ({{$z_server_role}})</p>
      {{if $prj_version}}
      <p class="card-text">{{$prj_version}}</p>
      {{/if}}
    </div>
    <div class="card-body">
      <h4 class="card-title">{{$prj_linktxt}}</h4>
      <a href="{{$prj_link}}" class="card-link">{{$prj_link}}</a>
    </div>
     <div class="card-body">
      <h4 class="card-title">{{$prj_srctxt}}</h4>
      <a href="{{$prj_src}}" class="card-link">{{$prj_src}}</a>
    </div>
    <div class="card-body">
      <h4 class="card-title"></h4>
      <p class="card-text">{{$prj_transport}} {{$transport_link}}</p>
    </div>
    <div class="card-body">
      {{if $additional_fed}}
        <p class="card-text">{{$additional_text}} {{$additional_fed}}</p>
      {{/if}}
    </div>
  </div>

</div>    


