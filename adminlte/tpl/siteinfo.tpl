
<div class="app-content">
<div class="card mb-3">
  <h3 class="card-header">Card header</h3>
  <div class="card-body">
    <h5 class="card-title">Special title treatment</h5>
    <h6 class="card-subtitle text-muted">Support card subtitle</h6>
  </div>
  <svg xmlns="http://www.w3.org/2000/svg" class="d-block user-select-none" width="100%" height="200" aria-label="Placeholder: Image cap" focusable="false" role="img" preserveAspectRatio="xMidYMid slice" viewBox="0 0 318 180" style="font-size:1.125rem;text-anchor:middle">
    <rect width="100%" height="100%" fill="#868e96"></rect>
    <text x="50%" y="50%" fill="#dee2e6" dy=".3em">Image cap</text>
  </svg>
  <div class="card-body">
    <p class="card-text">Some quick example text to build on the card title and make up the bulk of the card's content.</p>
  </div>
  <ul class="list-group list-group-flush">
    <li class="list-group-item">Cras justo odio</li>
    <li class="list-group-item">Dapibus ac facilisis in</li>
    <li class="list-group-item">Vestibulum at eros</li>
  </ul>
  <div class="card-body">
    <a href="#" class="card-link">Card link</a>
    <a href="#" class="card-link">Another link</a>
  </div>
  <div class="card-footer text-muted">
    2 days ago
  </div>
</div>
<div class="card">
  <div class="card-body">
    <h4 class="card-title">Card title</h4>
    <h6 class="card-subtitle mb-2 text-muted">Card subtitle</h6>
    <p class="card-text">Some quick example text to build on the card title and make up the bulk of the card's content.</p>
    <a href="#" class="card-link">Card link</a>
    <a href="#" class="card-link">Another link</a>
  </div>
</div>
  <div class="card mb-3">
    <div class="card-header">
      <h3 class="card-title">{{$title}}</h3>
      <div class="card-tools">
        <button type="button" class="btn btn-tool" data-lte-toggle="card-collapse">
          <i data-lte-icon="expand" class="bi bi-plus-lg"></i>
          <i data-lte-icon="collapse" class="bi bi-dash-lg"></i>
        </button>
       </div>
    </div>
    <div class="card-body">
      <h4>{{$sitename}}</h4>
      <p class="card-text">{{if $site_about}}{{$site_about}}{{else}}--{{/if}}</p>
    <ul class="list-group list-group-flush">
      <li class="list-group-item">
        <a href="help/TermsOfService" class="card-link">{{$terms}}</a>
      </li>
      {{if $addons.1}}
      <li class="list-group-item">
        <div class="d-flex py-2 px-1">
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
        <div class="d-flex py-2 px-1">
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
    </div>
    <div class="card-footer text-muted">
      {{if $admin_about}}{{$admin_about}}{{else}}--{{/if}}
    </div>    
  </div>

  <div class="card mb-3">
    <div class="card-header">
      <h3 class="card-title">{{$prj_header}}</h3>
      <div class="card-tools">
        <button type="button" class="btn btn-tool" data-lte-toggle="card-collapse">
          <i data-lte-icon="expand" class="bi bi-plus-lg"></i>
          <i data-lte-icon="collapse" class="bi bi-dash-lg"></i>
        </button>
      </div>
    </div>
    <div class="card-body">
      <p class="card-text">{{$prj_name}} ({{$z_server_role}})</p>
      {{if $prj_version}}
      <p class="card-text">{{$prj_version}}</p>
      {{/if}}
    </div>
    <div class="card-body">
    <ul class="list-group list-group-flush">    
      <li class="list-group-item">
        <div class="d-flex py-2 px-1">
          <div class="col-4">{{$prj_linktxt}}
          </div>
          <div class="col-8">    <a href="{{$prj_link}}" class="card-link">{{$prj_link}}</a>
          </div>
      </li> 
      <li class="list-group-item">
        <div class="d-flex py-2 px-1">
          <div class="col-4">{{$prj_srctxt}}
          </div>
          <div class="col-8">    <a href="{{$prj_src}}" class="card-link">{{$prj_src}}</a>
          </div>
      </li>
      <li class="list-group-item">
        <div class="d-flex py-2 px-1"><div>{{$prj_transport}} </div>{{$transport_link}}
      </li>
      <li class="list-group-item">
        <div class="d-flex py-2 px-1">{{$additional_text}} {{$additional_fed}}
      </li>
    </ul>
    </div>
  </div>

</div>    


