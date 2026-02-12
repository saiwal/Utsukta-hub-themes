<div class="generic-content-wrapper">
  <div class="section-title-wrapper app-content-header">
		<div class="lcars-text-bar"><span>{{$title}} -{{$page}}</span></div>
  </div>
      <!-- Static A–Z index -->
      <div class="list-group list-group-flush" id="pluginList">
        {{foreach $plugins as $p}}
        <div class="list-group-item d-flex justify-content-between align-items-start plugin-item"
          data-name="{{$p.2.name|escape:'html'}}">
          <div class="me-auto">
            <h6 class="mb-1">
              <a href="{{$baseurl}}/admin/{{$function}}/{{$p.0}}">{{$p.2.name}}</a>
              <small class="text-muted">v{{$p.2.version}}</small>
              {{if $p.2.disabled}} <span class="badge bg-secondary">{{$disabled}}</span>{{/if}}
              {{if $p.2.experimental}} <span class="badge bg-warning text-dark">{{$experimental}}</span>{{/if}}
              {{if $p.2.unsupported}} <span class="badge bg-danger">{{$unsupported}}</span>{{/if}}
            </h6>
            <p class="text-body-secondary mb-1">{{$p.2.description}}</p>
          </div>
          <div>
            {{if ! $p.2.disabled}}
            <a class="toggleplugin"
              href="{{$baseurl}}/admin/{{$function}}/{{$p.0}}?a=t&amp;t={{$form_security_token}}"
              title="{{if $p.1==on}}Disable{{else}}Enable{{/if}}">
              <i class="bi {{if $p.1==on}}bi-check-square{{else}}bi-square{{/if}}"></i>
            </a>
            {{else}}
            <span class="btn btn-sm btn-outline-secondary disabled">
              <i class="bi fa-stop"></i>
            </span>
            {{/if}}
          </div>
        </div>
        {{/foreach}}
      </div>
</div>
